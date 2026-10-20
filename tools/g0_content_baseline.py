#!/usr/bin/env python3
"""Disposable CI application-dispatch benchmark; never a production load generator."""
import argparse
import hashlib
import json
import math
import os
from pathlib import Path
import platform
import shutil
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]


def percentile(values, fraction):
    return sorted(values)[math.ceil(len(values) * fraction) - 1]


def run(output):
    if os.environ.get('APP_ENV') != 'testing' or os.environ.get('G0_DISPOSABLE') != '1' or os.environ.get('DB_NAME') != 'cms_g0_perf':
        raise RuntimeError('Dedicated disposable G0 database required')
    output.mkdir(parents=True, exist_ok=True)
    work = Path(tempfile.mkdtemp(prefix='uv-g0-content-'))
    fixture = work / 'fixture'
    report = {
        'schema': 1,
        'tested_commit': os.environ.get('GITHUB_SHA'),
        'head_commit': os.environ.get('G0_HEAD_SHA'),
        'runner': {'os': platform.platform(), 'cpu': platform.processor(), 'logical_cpus': os.cpu_count(), 'python': platform.python_version()},
        'protocol': {
            'dataset': 'Content-v1: primary 1000 nodes / 999 published documents / 10000 IS items; secondary 2 nodes / 1 document / 20 IS items',
            'concurrency': 1, 'samples_per_state': 40, 'warmup_requests': 5,
            'percentiles': 'nearest rank ceil(p*n); 40 samples, descriptive CI observations only',
            'cold': 'Fresh PHP process; remove only fixture application file-cache before every request',
            'warm': 'Fresh PHP process; preserve application cache after five warmups per scenario',
            'timing_scope': 'Application construction/run, routing/render/send to output buffer; PDO connect, autoloader initial load and session open excluded',
            'process_wall_scope': 'Whole PHP subprocess including connect, SQL-counter self-check, autoload and session setup',
            'sql_scope': 'PDO exec/query and prepared execute calls during application dispatch; transaction methods/internal server statements not counted',
            'memory_scope': 'PHP allocated peak after reset immediately before dispatch; includes resident worker baseline, separately reports delta',
            'not_reset': ['DB buffer cache', 'OS filesystem cache', 'generated layout template files'],
            'limitations': ['CLI Application dispatch, not network HTTP/FPM/Apache/FASTPANEL latency', 'Instrumented PDO overhead included', 'No concurrent load', 'Shared CI runner, no approved latency threshold', 'No Commerce/localization measurement'],
        },
        'scenarios': [], 'isolation_checks': [],
    }
    try:
        subprocess.run(['php', '-d', 'memory_limit=128M', str(ROOT / 'tests/g0_content_fixture.php'), str(fixture)], check=True, cwd=ROOT, timeout=120, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        metadata = json.loads((fixture / 'fixture.json').read_text())
        report['fixture_counts'] = metadata['counts']
        # Stable dataset identity excludes generated DB IDs, timestamps, credentials and session IDs.
        descriptor = {'version': 'Content-v1', 'counts': metadata['counts'], 'publication': 'i%5 draft; i%7 inactive; others published; fixed past publish_at', 'properties': ['kind', 'capacity', 'featured'], 'groups_per_site': 20, 'primary_sections': 10, 'document_payload': 'content x128', 'item_payload': 'item content x48'}
        report['fixture_descriptor'] = descriptor
        report['fixture_sha256'] = hashlib.sha256(json.dumps(descriptor, sort_keys=True).encode()).hexdigest()

        def dispatch(spec):
            start = time.perf_counter()
            result = subprocess.run(['php', '-d', 'memory_limit=128M', '-d', 'opcache.enable_cli=0', str(ROOT / 'tests/g0_dispatch_probe.php'), str(fixture), json.dumps(spec)], check=True, cwd=ROOT, capture_output=True, text=True, timeout=30)
            if result.stderr:
                raise RuntimeError('Probe emitted diagnostics: ' + result.stderr)
            sample = json.loads(result.stdout)
            sample['process_wall_ms'] = (time.perf_counter() - start) * 1000
            report['php_environment'] = sample.pop('environment')
            return sample

        # Same URL and cache directory on two hosts; assert warm hits remain isolated.
        for variant, other in [('primary', 'secondary'), ('secondary', 'primary')]:
            spec = {'uri': '/', 'site': variant, 'contains': [metadata['sites'][variant]['marker']], 'excludes': [metadata['sites'][other]['marker']]}
            dispatch(spec)
            hit = dispatch(dict(spec, pageCache='hit'))
            report['isolation_checks'].append({'case': variant + '-host-warm-cache', 'status': hit['status'], 'page_hits': hit['page_hits']})
        for name, uri in [('foreign-document-id', '/admin/documents/' + str(metadata['sites']['secondary']['document']) + '/edit'), ('foreign-infosystem-id', '/admin/infosystems/' + str(metadata['sites']['secondary']['infosystem']) + '/items')]:
            result = dispatch({'uri': uri, 'status': 404, 'excludes': ['SECONDARY_CONTENT']})
            report['isolation_checks'].append({'case': name, 'status': result['status']})

        specs = [
            ('public-document', {'uri': '/section-10/page-0500', 'contains': ['PRIMARY_CONTENT'], 'excludes': ['SECONDARY_CONTENT']}),
            ('public-infosystem-list', {'uri': '/news', 'contains': ['PRIMARY_CONTENT item-00001'], 'excludes': ['SECONDARY_CONTENT', 'PRIMARY_CONTENT item-00005', 'PRIMARY_CONTENT item-00007']}),
            ('admin-documents-page', {'uri': '/admin/documents?folder=all&q=PRIMARY_CONTENT&per_page=25', 'contains': ['PRIMARY_CONTENT'], 'excludes': ['SECONDARY_CONTENT']}),
            ('admin-infosystem-items-page', {'uri': '/admin/infosystems/' + str(metadata['sites']['primary']['infosystem']) + '/items?per_page=25', 'contains': ['PRIMARY_CONTENT'], 'excludes': ['SECONDARY_CONTENT']}),
            ('admin-structure-tree', {'uri': '/admin/structure', 'contains': ['PRIMARY_CONTENT'], 'excludes': ['SECONDARY_CONTENT']}),
        ]
        for name, spec in specs:
            scenario = {'name': name, 'uri': spec['uri'], 'states': {}}
            report['scenarios'].append(scenario)
            for state in ['cold', 'warm']:
                shutil.rmtree(fixture / 'cache', ignore_errors=True)
                if state == 'warm':
                    for _ in range(5):
                        dispatch(spec)
                samples = []
                for _ in range(40):
                    if state == 'cold':
                        shutil.rmtree(fixture / 'cache', ignore_errors=True)
                    expectation = {'pageCache': 'miss' if state == 'cold' else 'hit'} if name.startswith('public-') else {}
                    samples.append(dispatch(dict(spec, **expectation)))
                summaries = {}
                for metric in ['dispatch_ms', 'process_wall_ms', 'sql_executions', 'peak_allocated_bytes', 'peak_delta_bytes', 'body_bytes']:
                    values = [sample[metric] for sample in samples]
                    summaries[metric] = {'p50': percentile(values, .5), 'p95': percentile(values, .95), 'min': min(values), 'max': max(values)}
                scenario['states'][state] = {'summary': summaries, 'samples': samples}
                print(name, state, json.dumps(summaries['dispatch_ms']), flush=True)
        report['status'] = 'completed'
    except Exception as error:
        report['status'] = 'failed'
        # Fixture commands do not print credentials. Keep diagnostics for reproducibility.
        report['error'] = str(error)
        if isinstance(error, subprocess.CalledProcessError):
            report['diagnostics'] = {'stdout': error.stdout, 'stderr': error.stderr}
        raise
    finally:
        (output / 'report.json').write_text(json.dumps(report, indent=2) + '\n')
        shutil.rmtree(work)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('output', type=Path)
    run(parser.parse_args().output)
