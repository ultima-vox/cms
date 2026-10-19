"""Targeted ownership guard; not a replacement for PHP type/static analysis."""
import argparse
import pathlib
import re


def violations(root):
    # Module-owned CREATE TABLE declarations, including legacy initial schemas.
    tables = set()
    for sql in (root / 'modules').glob('*/migrations/**/*.sql'):
        tables.update(re.findall(r'\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([a-z_][a-z_0-9]*)', sql.read_text(), re.I))
    failures = []
    for file in (root / 'core').rglob('*.php'):
        source = file.read_text()
        if re.search(r'UltimaVox\\+(?:Modules)\\+', source):
            failures.append(f'{file.relative_to(root)}: business module namespace')
        for table in sorted(tables):
            # Qualified/quoted names are covered; dynamically assembled SQL still needs review.
            pattern = r'\b(?:FROM|JOIN|UPDATE|INTO)\s+(?:[`"]?[a-z_][a-z_0-9]*[`"]?\.)?[`"]?' + re.escape(table) + r'(?![a-z_0-9])'
            if re.search(pattern, source, re.I):
                failures.append(f'{file.relative_to(root)}: module-owned SQL table {table}')
    return failures


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=pathlib.Path, default=pathlib.Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    failures = violations(args.root)
    if failures:
        raise SystemExit('\n'.join(failures))
    print('CORE BOUNDARIES OK: no concrete module imports or literal module-table queries in Core')


if __name__ == '__main__':
    main()
