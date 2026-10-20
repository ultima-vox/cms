"""Verify the first fresh HTTP worker completes registration and preserves existing data."""
import http.cookiejar
import json
import os
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

base = 'http://127.0.0.1:8087'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
def request(path, data=None, status=200):
    try:
        response = client.open(base + path, None if data is None else urllib.parse.urlencode(data).encode(), timeout=60)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.code == status, (path, response.code, body[:500])
    return body
def token(body):
    return re.search(r'name="_csrf" value="([^"]+)"', body).group(1)
body = request('/')
assert 'LEGACY-LAYOUT' in body and 'Preserved published content 1' in body
body = request('/admin/login')
request('/admin/login', {'_csrf': token(body), 'email': 'upgrade@example.test', 'password': os.environ['CMS_ADMIN_PASSWORD']}, 303)
body = request('/admin/sites/create')
assert 'name="code"' in body, 'New permissions were not reconciled by the fresh worker'
body = request('/admin/documents')
assert 'Existing content 1' in body and 'Existing content 2' not in body
request('/admin/folders/documents', {'_csrf': token(body), 'name': 'After upgrade', 'parent_id': ''}, 302)
body = request('/admin/layouts')
assert 'Shared legacy layout' in body
result = subprocess.check_output(['psql', '-h', '127.0.0.1', '-U', 'cms', '-d', 'cms_upgrade_runtime', '-Atc',
    "SELECT count(*) FROM extension_sync_jobs; SELECT count(*) FROM layouts WHERE code = 'upgrade-custom'; SELECT count(*) FROM nodes n JOIN layouts l ON l.id = n.layout_id WHERE n.site_id <> l.site_id;"], text=True)
assert result.splitlines() == ['0', '2', '0'], result
print('Fresh-worker HTTP upgrade OK: content preserved, layouts split, new permissions registered, folders usable')

# Existing versions must survive the additive migration with unknown authors, then support guarded saves.
body = request('/admin/documents?folder=all')
editor = re.search(r'href="(/admin/documents/\d+/edit[^"]*)"', body).group(1).replace('&amp;', '&')
body = request(editor)
assert 'Не указан' in body and 'Автор' in body
revision = re.search(r'name="expected_revision" value="([^"]+)"', body).group(1)
path = editor.split('?')[0].removesuffix('/edit')
request(path, {'_csrf': token(body), 'expected_revision': revision, 'name': 'Existing content after upgrade',
    'content': '<p>After revision upgrade</p>', 'save_mode': 'draft'}, 302)
body = request(editor)
assert 'Upgrade operator' in body and 'After revision upgrade' in body
request(path, {'_csrf': token(body), 'expected_revision': revision, 'name': 'Stale after upgrade',
    'content': 'Must not replace', 'save_mode': 'publish'}, 409)
print('Existing populated upgrade revisions OK: nullable historic authors, new author attribution and stale rejection')
