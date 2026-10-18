"""Package source plus installed production dependencies, never runtime credentials/data."""
import argparse
import pathlib
import zipfile

root = pathlib.Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=pathlib.Path)
parser.add_argument('--webroot', action='store_true', help='Install directly in an existing web root')
args = parser.parse_args()
output = args.output.resolve()
prefix = 'app/' if args.webroot else ''
if not (root / 'vendor/autoload.php').is_file():
    raise SystemExit('Run composer install --no-dev --classmap-authoritative first.')
folders = ('core', 'modules', 'templates', 'public', 'database', 'installer', 'deploy', 'vendor', 'bin')
files = ('composer.json', 'composer.lock', '.env.example', 'README.md', 'docs/MVP.md', 'docs/architecture/infrastructure-scaling.md')
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for folder in folders:
        for path in sorted((root / folder).rglob('*')):
            if path.is_file() and not path.is_symlink():
                archive.write(path, prefix + path.relative_to(root).as_posix())
    for name in files:
        archive.write(root / name, prefix + name)
    for folder in ('storage/cache', 'storage/logs', 'storage/templates/layouts'):
        archive.writestr(prefix + folder + '/.gitkeep', '')
    if args.webroot:
        archive.write(root / 'deploy/webroot/index.php', 'index.php')
        archive.write(root / 'deploy/webroot/.htaccess', '.htaccess')
        archive.writestr('app/.htaccess', 'Require all denied\n')
        for path in sorted((root / 'public/assets').rglob('*')):
            if path.is_file() and not path.is_symlink():
                archive.write(path, path.relative_to(root / 'public').as_posix())
with zipfile.ZipFile(output) as archive:
    names = set(archive.namelist())
    assert prefix + '.env' not in names and prefix + 'storage/install.lock' not in names
    assert not any(name.startswith(tuple(prefix + part for part in ('storage/static/', 'storage/cache/data/', '.git/'))) for name in names)
    assert prefix + 'vendor/autoload.php' in names
    if args.webroot:
        assert {'index.php', '.htaccess', 'app/.htaccess'} <= names
print(output)
