"""Verify that clean installs and differential updates ship the same private site package."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from zipfile import ZipFile

if len(sys.argv) != 4:
    raise SystemExit("Usage: mechaniki_distribution_smoke.py <public.zip> <webroot.zip> <update.zip>")

root = Path(__file__).resolve().parent.parent
tracked = subprocess.check_output(
    ["git", "ls-files", "--", "modules/mechaniki/site"], cwd=root, text=True
).splitlines()
assert len(tracked) == 65, f"Unexpected site package inventory: {len(tracked)}"
assert all(Path(name).suffix in {".php", ".html", ".json", ".md"} for name in tracked)
for file in tracked:
    assert (root / file).is_file(), f"Missing source file: {file}"
for key in [
    "modules/mechaniki/site/activate.php",
    "modules/mechaniki/site/activate-infosystems.php",
    "modules/mechaniki/site/infosystems/services.json",
    "modules/mechaniki/site/infosystems/prices.json",
]:
    assert key in tracked, f"Required resource missing: {key}"

for archive_name, prefix, manifest_path in [
    (sys.argv[1], "", "release.json"),
    (sys.argv[2], "app/", "app/release.json"),
    (sys.argv[3], "payload/", "update.json"),
]:
    with ZipFile(archive_name) as archive:
        names = set(archive.namelist())
        manifest = json.loads(archive.read(manifest_path))
        packaged = set(manifest["files"])
        assert all(not item.startswith("sites/") for item in packaged), (
            "Unexpected site files bundled"
        )
        assert set(tracked) <= packaged, "Site package omitted from managed file manifest"
        for path in tracked:
            full_path = prefix + path
            assert full_path in names, f"{archive_name}: missing {full_path}"
            source = (root / path).read_bytes()
            assert archive.read(full_path) == source, f"{archive_name}: bytes changed for {path}"
            assert manifest["files"][path] == hashlib.sha256(source).hexdigest(), (
                f"{archive_name}: bad manifest checksum for {path}"
            )
        if prefix == "app/":
            assert not any(name.startswith("sites/") for name in names), (
                "Webroot archive leaked site source outside private app/"
            )
print("MECHANIKI DISTRIBUTION OK: site package in both ZIP layouts and updater")
