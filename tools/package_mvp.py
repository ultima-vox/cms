"""Package source plus installed production dependencies, never runtime credentials/data."""
import pathlib
import sys
import zipfile

root = pathlib.Path(__file__).resolve().parent.parent
output = pathlib.Path(sys.argv[1]).resolve()
if not (root / 'vendor/autoload.php').is_file():
    raise SystemExit('Run composer install --no-dev --classmap-authoritative first.')
folders = ('core', 'modules', 'templates', 'public', 'database', 'installer', 'deploy', 'vendor', 'bin')
files = ('composer.json', 'composer.lock', '.env.example', 'README.md', 'docs/MVP.md', 'docs/architecture/infrastructure-scaling.md')
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for folder in folders:
        for path in sorted((root / folder).rglob('*')):
            if path.is_file() and not path.is_symlink():
                archive.write(path, path.relative_to(root))
    for name in files:
        archive.write(root / name, name)
    for folder in ('storage/cache', 'storage/logs', 'storage/templates/layouts'):
        archive.writestr(folder + '/.gitkeep', '')
with zipfile.ZipFile(output) as archive:
    names = set(archive.namelist())
    assert '.env' not in names and 'storage/install.lock' not in names
    assert not any(name.startswith(('storage/static/', 'storage/cache/data/', '.git/')) for name in names)
    assert 'vendor/autoload.php' in names
print(output)
