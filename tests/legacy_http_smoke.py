"""Fresh legacy webroot install, then real authenticated editor requests."""
import http.cookiejar
import os
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('LEGACY_URL', 'http://127.0.0.1:8089')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(path, data=None, expected=200):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = client.open(BASE + path, payload, timeout=90)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.code == expected, f'{path}: {response.code}, {body[:600]}'
    return body, response.headers

def token(body):
    match = re.search(r'name="_csrf" value="([^"]+)"', body)
    assert match, 'Missing CSRF'
    return match.group(1)

body, _ = request('/install/database')
assert re.search(r'<option value="pgsql"\s+selected', body), 'PostgreSQL must remain the default'
request('/install/database', {'_csrf': token(body), 'driver': 'mysql', 'host': '127.0.0.1',
    'port': os.environ.get('DB_PORT', '3306'), 'database': 'cms_legacy_http',
    'user': 'cms', 'password': 'cms'}, 302)
body, _ = request('/install/site')
password = secrets.token_urlsafe(24)
request('/install/site', {'_csrf': token(body), 'site_name': 'Legacy HTTP site', 'site_url': BASE,
    'email': 'legacy@example.test', 'admin_password': password, 'admin_password_confirm': password}, 302)
body, _ = request('/admin/login')
request('/admin/login', {'_csrf': token(body), 'email': 'legacy@example.test', 'password': password}, 303)
for path in ['/admin', '/admin/documents', '/admin/infosystems', '/admin/layouts', '/admin/sites', '/admin/modules', '/admin/updates']:
    request(path)
body, _ = request('/admin/documents/create')
_, headers = request('/admin/documents', {'_csrf': token(body), '_site_id': '1', 'code': 'legacy-http',
    'name': 'Legacy document', 'content': '<p>Legacy content</p>', 'save_mode': 'publish'}, 303)
edit = headers.get('Location')
body, _ = request(edit)
assert 'Legacy document' in body and 'Legacy content' in body
body, _ = request('/admin/documents')
request('/admin/folders/documents', {'_csrf': token(body), '_site_id': '1', 'name': 'Legacy folder'}, 303)
body, _ = request('/admin/documents')
assert 'Legacy folder' in body and 'Legacy document' in body
request('/admin/folders/documents', {'_csrf': 'invalid', 'name': 'Rejected'}, 419)
for path in ['/app', '/app/.env', '/app/composer.json', '/app/vendor/autoload.php', '/.env']:
    request(path, expected=404)
body, _ = request('/')
assert 'Legacy HTTP site' in body
print('LEGACY HTTP OK: PostgreSQL default, explicit MySQL install, login, all admin sections, document, folder, private files')
