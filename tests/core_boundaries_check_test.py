"""Prove the ownership guard fails on real imports and SQL variants."""
import importlib.util
import pathlib
import tempfile

spec = importlib.util.spec_from_file_location('guard', pathlib.Path(__file__).resolve().parents[1] / 'tools/check_core_boundaries.py')
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)
with tempfile.TemporaryDirectory() as directory:
    root = pathlib.Path(directory)
    (root / 'core').mkdir()
    schema = root / 'modules/example/migrations/mysql'
    schema.mkdir(parents=True)
    (schema / '001.sql').write_text('CREATE TABLE example_records (id BIGINT);')
    source = root / 'core/Test.php'
    source.write_text('<?php $sql = "SELECT * FROM sites";')
    assert guard.violations(root) == []
    for text in ['SELECT * FROM example_records', 'SELECT * FROM "public"."example_records"',
                 'SELECT * FROM `db`.`example_records`', 'UPDATE example_records SET id=1',
                 'INSERT INTO example_records(id) VALUES(1)', 'SELECT * FROM sites JOIN example_records r ON r.id=1',
                 'use UltimaVox\\Modules\\Example\\Repository;', r'"UltimaVox\\Modules\\Example\\Repository"']:
        source.write_text('<?php ' + text)
        assert guard.violations(root), text
print('CORE OWNERSHIP GUARD SELF-TEST OK')
