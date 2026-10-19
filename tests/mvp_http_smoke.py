"""Exercise a fresh install and an editor journey against a real PHP/PostgreSQL app."""
import html
import http.cookiejar
import json
import os
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('MVP_URL', 'http://127.0.0.1:8086')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def browser():
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())
    opener.cookie_jar = jar
    return opener


def request(client, path, data=None, expected=200):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = client.open(BASE + path, payload, timeout=90)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8')
    if response.code != expected:
        raise AssertionError(f'{path}: expected {expected}, received {response.code}; {body[:500]}')
    return body, response.headers


def token(body):
    match = re.search(r'name="_csrf" value="([^"]+)"', body)
    assert match, 'CSRF field absent'
    return match.group(1)


def query(sql):
    env = os.environ.copy()
    env['PGPASSWORD'] = os.environ['MVP_DB_PASS']
    return subprocess.check_output([
        'psql', '-h', os.environ.get('MVP_DB_HOST', '127.0.0.1'), '-p', '5432',
        '-U', os.environ['MVP_DB_USER'], '-d', os.environ['MVP_DB_NAME'], '-Atc', sql,
    ], env=env, text=True).strip()


def check_private_files():
    if os.environ.get('MVP_LAYOUT') == 'webroot':
        for path in ('/app', '/app/.env', '/app/composer.json', '/app/vendor/autoload.php',
                     '/app/public/index.php', '/%61pp/composer.json', '/app%2f.env', '/.env'):
            request(browser(), path, expected=404)


check_private_files()
client = browser()
request(client, '/', expected=302)
css, headers = request(client, '/assets/installer.css')
assert 'text/css' in headers.get('Content-Type', '') and len(css) > 100
body, _ = request(client, '/install/database')
request(client, '/install/database', {
    '_csrf': token(body), 'host': os.environ.get('MVP_DB_HOST', '127.0.0.1'), 'port': '5432',
    'database': os.environ['MVP_DB_NAME'], 'user': os.environ['MVP_DB_USER'], 'password': os.environ['MVP_DB_PASS'],
}, expected=302)
body, _ = request(client, '/install/site')
password = secrets.token_urlsafe(24)
setup = {'_csrf': token(body), 'site_name': 'MVP test site', 'site_url': BASE,
         'email': 'mvp@example.test', 'admin_password': password, 'admin_password_confirm': password}
request(client, '/install/site', dict(setup, _csrf='invalid'), expected=419)
_, headers = request(client, '/install/site', setup, expected=302)
assert headers.get('Location') == '/admin/login?installed=1'
assert query('SELECT COUNT(*) FROM users') == '1'
request(client, '/install/site', setup, expected=405)
assert query('SELECT COUNT(*) FROM users') == '1'
check_private_files()
public = browser()
body, _ = request(public, '/')
assert 'MVP test site' in body
body, _ = request(client, '/admin/login')
request(client, '/admin/login', {'_csrf': token(body), 'email': 'mvp@example.test', 'password': password}, expected=303)
request(client, '/admin')
updates, _ = request(client, '/admin/updates')
assert 'Проверить обновления' in updates
request(client, '/admin/updates/check', {'_csrf': 'invalid'}, expected=419)
request(client, '/admin/updates/apply', {'_csrf': 'invalid'}, expected=419)
request(public, '/admin/updates', expected=302)
assert 'Готовность сервера' in updates and 'История обновлений' in updates
# Permissions apply independently of module management and CSRF validation.
query("DELETE FROM role_permissions WHERE permission_id = (SELECT id FROM permissions WHERE code = 'updates.apply') AND role_id = (SELECT id FROM roles WHERE code = 'superadmin')")
request(client, '/admin/updates')
request(client, '/admin/updates/apply', {'_csrf': 'invalid'}, expected=403)
query("DELETE FROM role_permissions WHERE permission_id = (SELECT id FROM permissions WHERE code = 'updates.view') AND role_id = (SELECT id FROM roles WHERE code = 'superadmin')")
request(client, '/admin/updates', expected=403)
request(client, '/admin/updates/check', {'_csrf': 'invalid'}, expected=403)
body, _ = request(client, '/admin/modules')
csrf = token(body)
request(client, '/admin/modules/sync', {'_csrf': csrf}, expected=302)
request(client, '/admin/modules/updater/disable', {'_csrf': csrf}, expected=302)
body, _ = request(client, '/admin')
assert 'href="/admin/updates"' not in body
request(client, '/admin/updates', expected=404)
request(client, '/admin/modules/updater/enable', {'_csrf': csrf}, expected=302)
request(client, '/admin/updates')
print('Updater HTTP OK: independent permissions and standard module enable/disable')

_, headers = request(client, '/assets/admin.css')
assert 'text/css' in headers.get('Content-Type', '')
root_id = query("SELECT id FROM nodes WHERE site_id = 1 AND path = '/'")
body, _ = request(client, '/admin/structure/create')
_, headers = request(client, '/admin/structure', {
    '_csrf': token(body), 'name': 'MVP page', 'title': 'MVP page', 'parent_id': root_id,
    'slug': 'poc', 'page_type': 'documents.page', 'status': 'published', 'is_active': '1',
    'sorting': '0', 'layout_id': '', 'meta_description': '', 'publish_at': '',
}, expected=302)
match = re.search(r'/admin/structure/(\d+)/edit', headers.get('Location', ''))
assert match, 'Created page edit URL absent'
node_id = int(match.group(1))
document_id = query(f"SELECT id FROM documents WHERE site_id = 1 AND code = 'node-{node_id}'")
assert document_id.isdigit(), 'Provisioned document absent'
path = f'/admin/documents/{document_id}'
body, _ = request(client, path + '/edit')
csrf = token(body)
request(client, path, {'_csrf': csrf, 'name': 'MVP page', 'content': '<p>Published MVP content</p>', 'save_mode': 'publish'}, expected=302)
body, _ = request(public, '/poc')
assert 'Published MVP content' in body
request(client, path, {'_csrf': 'invalid', 'name': 'MVP page', 'content': '<p>Must not publish</p>', 'save_mode': 'publish'}, expected=419)
request(client, path, {'_csrf': csrf, 'name': 'MVP page', 'content': '<p>Draft MVP content</p>', 'save_mode': 'draft'}, expected=302)
body, _ = request(public, '/poc')
assert 'Published MVP content' in body and 'Draft MVP content' not in body
request(client, path, {'_csrf': csrf, 'name': 'MVP page', 'content': '<p>Updated MVP content</p>', 'save_mode': 'publish'}, expected=302)
body, _ = request(public, '/poc')
assert 'Updated MVP content' in body and 'Published MVP content' not in body
print('MVP HTTP OK: install -> login -> create page -> publish -> anonymous read -> draft -> republish')

# Install the optional pilot through the same controls available to an administrator.
body, _ = request(client, '/admin/modules')
csrf = token(body)
request(client, '/admin/modules/sync', {'_csrf': csrf}, expected=302)
request(client, '/admin/modules/mechaniki/enable', {'_csrf': csrf}, expected=302)
body, _ = request(client, '/admin/mechaniki')
csrf = token(body)
request(public, '/admin/mechaniki', expected=302)
request(client, '/admin/mechaniki/import', {'_csrf': 'invalid'}, expected=419)
request(client, '/admin/mechaniki/import', {'_csrf': csrf}, expected=302)
count = query("SELECT COUNT(*) FROM nodes WHERE site_id = 1 AND (path = '/mechaniki' OR path LIKE '/mechaniki/%')")
assert count == '6', count
request(client, '/admin/mechaniki/import', {'_csrf': csrf}, expected=422)
assert query("SELECT COUNT(*) FROM nodes WHERE site_id = 1 AND (path = '/mechaniki' OR path LIKE '/mechaniki/%')") == count
for path in ('/mechaniki', '/mechaniki/services', '/mechaniki/services/engine', '/mechaniki/services/suspension', '/mechaniki/about', '/mechaniki/contacts'):
    body, _ = request(public, path)
    assert 'noindex, nofollow' in body and '/assets/mechaniki/site.css' in body
    assert '+7 (926) 815-09-24' in body
for name in ('site.css', 'site.js', 'logo.svg', 'OFL.txt'):
    request(public, '/assets/mechaniki/' + name)
# Verify that imported pages remain editable documents and invalidate public HTML.
contact_node = query("SELECT id FROM nodes WHERE site_id = 1 AND path = '/mechaniki/contacts'")
contact_document = query(f"SELECT id FROM documents WHERE site_id = 1 AND code = 'node-{int(contact_node)}'")
body, _ = request(client, f'/admin/documents/{contact_document}/edit')
request(client, f'/admin/documents/{contact_document}', {'_csrf': token(body), 'name': 'Контакты', 'content': '<p>Редактирование пилотного сайта работает.</p>', 'save_mode': 'publish'}, expected=302)
body, _ = request(public, '/mechaniki/contacts')
assert 'Редактирование пилотного сайта работает.' in body
body, _ = request(public, '/poc')
assert 'Updated MVP content' in body, 'Pilot import overwrote existing content'
if os.environ.get('MVP_BROWSER_STATE'):
    cookies = [{'name': c.name, 'value': c.value, 'domain': urllib.parse.urlparse(BASE).hostname,
                'path': c.path, 'secure': c.secure, 'httpOnly': True, 'sameSite': 'Lax', 'expires': c.expires or -1}
               for c in client.cookie_jar]
    with open(os.environ['MVP_BROWSER_STATE'], 'w') as handle:
        json.dump({'cookies': cookies, 'origins': []}, handle)
print('Mechaniki pilot OK: authenticated import, replay guard, six pages, editable documents, existing content preserved')

# The layout editor must generate the same native PHP format accepted by the renderer.
body, _ = request(client, '/admin/layouts/create')
source = html.unescape(re.search(r'<textarea[^>]*name="source"[^>]*>(.*?)</textarea>', body, re.S).group(1))
assert '$page->title()' in source and '$page->execute()' in source
_, headers = request(client, '/admin/layouts', {'_csrf': token(body), 'name': 'Native editor layout', 'code': 'mvp-native', 'description': '', 'source': source}, expected=302)
layout_id = query("SELECT id FROM layouts WHERE code = 'mvp-native' AND template_path = 'layouts/mvp-native.php'")
assert layout_id.isdigit(), 'Editor did not create a native PHP layout'
request(client, f'/admin/layouts/{layout_id}/edit')
body, _ = request(client, '/admin/structure/create')
request(client, '/admin/structure', {'_csrf': token(body), 'name': 'Native layout page', 'title': 'Native layout page',
    'parent_id': root_id, 'slug': 'native-layout', 'page_type': 'documents.page', 'status': 'published',
    'is_active': '1', 'sorting': '0', 'layout_id': layout_id, 'meta_description': '', 'publish_at': ''}, expected=302)
body, _ = request(public, '/native-layout')
assert '<h1>Native layout page</h1>' in body
mechaniki_layout = query("SELECT id FROM layouts WHERE code = 'mechaniki'")
request(client, f'/admin/layouts/{mechaniki_layout}/edit')
print('Native layout editor OK: create -> assign -> public render -> edit pilot template')
