"""Verify that Apache itself protects static private files, before/after install."""
import argparse
import os
from pathlib import Path
import urllib.error
import urllib.request

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--expect-unsafe', action='store_true', help='Negative fixture: overrides deliberately disabled')
parser.add_argument('--before-install', action='store_true')
args = parser.parse_args()
base = os.environ.get('MVP_URL', 'http://127.0.0.1:8086')
layout = os.environ['MVP_LAYOUT']

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

client = urllib.request.build_opener(NoRedirect())

def get(path):
    try:
        response = client.open(base + path, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read()

if args.expect_unsafe:
    assert layout == 'webroot'
    status, body = get('/app/composer.json')
    assert status == 200 and b'"require"' in body, 'Negative fixture did not expose a real private static file'
    print('APACHE NEGATIVE FIXTURE OK: disabled overrides expose private static files')
else:
    paths = ['/app', '/app/', '/app/composer.json', '/%61pp/composer.json', '/app%2fcomposer.json',
             '/app/.env', '/app/.env.example', '/app/vendor/autoload.php', '/app/public/index.php',
             '/app/storage/logs/private-proof.txt', '/app/modules/mechaniki/site/activate.php', '/.env', '/.env.example', '/.htaccess']
    if layout == 'public':
        # These live outside DocumentRoot. A fallback/redirect may exist pre-install;
        # require the installed app to return its actual 404 rather than private data.
        paths += ['/composer.json', '/composer.lock', '/vendor/autoload.php', '/storage/logs/private-proof.txt', '/modules/mechaniki/site/activate.php']
    for path in paths:
        status, body = get(path)
        allowed = (302, 403, 404) if args.before_install and layout == 'public' else (403, 404)
        assert status in allowed, f'{path}: expected denial, got {status}'
        assert b'CMS_PRIVATE_PROOF' not in body and b'"require"' not in body, f'Private contents exposed at {path}'
    assets = Path('/tmp/cms-apache') / ('public/assets' if layout == 'public' else 'assets')
    assert not list(assets.glob('cms-install-probe-*.txt')), 'Installer left a public control file'
    status, body = get('/assets/installer.css')
    assert status == 200 and len(body) > 100, 'Apache did not serve public assets'
    print(f'APACHE PRIVATE FILES OK: {layout}, {len(paths)} paths denied, static assets available')
