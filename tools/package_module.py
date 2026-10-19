"""Build a module ZIP accepted by ModulePackageInstaller (manifest at ZIP root)."""
import json
import pathlib
import re
import sys
import zipfile

root = pathlib.Path(__file__).resolve().parent.parent
code, destination = sys.argv[1:]
if not re.fullmatch(r'[a-z][a-z0-9._-]{0,79}', code):
    raise SystemExit('Invalid module code')
module = root / 'modules' / code
manifest = json.loads((module / 'module.json').read_text())
if manifest['code'] != code:
    raise SystemExit('Module code mismatch')
with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(module.rglob('*')):
        if path.is_symlink():
            raise SystemExit('Module package cannot contain symlinks')
        if path.is_file():
            if path.name == 'module.json' and path.parent == module:
                manifest['default_enabled'] = False
                info = zipfile.ZipInfo('module.json')
                info.external_attr = 0o100644 << 16
                archive.writestr(info, json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
            else:
                archive.write(path, path.relative_to(module).as_posix())
print(destination)
