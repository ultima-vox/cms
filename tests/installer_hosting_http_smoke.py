"""A reachable installer on unsafe Apache must refuse before creating database objects."""
import glob
import os
import subprocess
from pathlib import Path

# Reuse only the HTTP helper definitions, not the positive installation journey.
import http.cookiejar
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

base = os.environ['MVP_URL']
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
def request(path, data=None, expected=200):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = client.open(base + path, payload, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.code == expected, f'{path}: {response.code}, {body[:300]}'
    return body

def token(body):
    return re.search(r'name="_csrf" value="([^"]+)"', body).group(1)
body = request('/install/database')
request('/install/database', {'_csrf': token(body), 'host': '127.0.0.1', 'port': '5432',
    'database': 'cms_apache_unsafe', 'user': 'cms', 'password': 'cms'}, 302)
body = request('/install/site')
password = secrets.token_urlsafe(24)
result = request('/install/site', {'_csrf': token(body), 'site_name': 'Must not install', 'site_url': base,
    'email': 'unsafe@example.test', 'admin_password': password, 'admin_password_confirm': password}, 422)
assert os.environ.get('EXPECTED_PROBE_ERROR', 'Небезопасное размещение') in result, result[:500]
env = dict(os.environ, PGPASSWORD='cms')
count = subprocess.check_output(['psql', '-h', '127.0.0.1', '-U', 'cms', '-d', 'cms_apache_unsafe', '-Atc',
    "SELECT COUNT(*) FROM pg_tables WHERE schemaname='public'"], env=env, text=True).strip()
assert count == '0', f'Failed preflight changed database: {count} tables'
root = Path('/tmp/cms-apache-unsafe')
assert not (root / 'app/.env').exists()
assert not (root / 'app/storage/install.lock').exists()
assert not glob.glob(str(root / 'assets/cms-install-probe-*.txt')), 'Control file was not removed'
print('INSTALLER HOSTING REFUSAL OK: unsafe/unverifiable origin, unchanged DB/config, control file removed')
