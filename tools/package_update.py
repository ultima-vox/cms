"""Build layout-neutral code update; public release is published only after CI."""
import hashlib
import json
import os
import pathlib
import subprocess
import sys
import zipfile

root = pathlib.Path(__file__).resolve().parent.parent
output = pathlib.Path(sys.argv[1])
version = os.environ['CMS_VERSION']
folders = ('core', 'modules', 'templates', 'public', 'database', 'installer', 'deploy', 'vendor', 'bin')
paths = sorted(p for folder in folders for p in (root / folder).rglob('*') if p.is_file() and not p.is_symlink())
paths += [root / p for p in ('composer.json', 'composer.lock')]
files = {p.relative_to(root).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in paths}
schema = {k: v for k, v in files.items() if k.endswith('.sql') and (k.startswith('database/migrations/') or '/migrations/' in k)}
manifest = {'version': version, 'files': files, 'schema': schema}
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    archive.writestr('update.json', json.dumps(manifest, ensure_ascii=False))
    for p in paths:
        archive.write(p, 'payload/' + p.relative_to(root).as_posix())
try:
    previous = subprocess.check_output(['git', 'describe', '--tags', '--abbrev=0'], cwd=root, text=True, stderr=subprocess.DEVNULL).strip()
except subprocess.CalledProcessError:
    previous = ''
revision = previous + '..HEAD' if previous else 'HEAD'
changes = subprocess.check_output(['git', 'log', '--format=%s', '-20', revision], cwd=root, text=True).splitlines()
feed = {'version': version, 'sha256': hashlib.sha256(output.read_bytes()).hexdigest(),
        'url': f'https://github.com/ultima-vox/cms/releases/download/v{version}/cms-update.zip', 'changes': changes}
output.with_name('update.json').write_text(json.dumps(feed, ensure_ascii=False, indent=2) + '\n')
print(output)
