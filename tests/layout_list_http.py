"""Real layout owner routes on PostgreSQL and legacy installations."""
import re
from urllib.parse import urlencode
from document_context_http import DocumentPage


def verify_layout_lists(request):
    def choose(site):
        body, _ = request('/admin')
        request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'],
                'site_id': str(site)}, expected=302)

    choose(1)
    body, _ = request('/admin/layouts')
    _, headers = request('/admin/folders/layouts', dict(DocumentPage(body).hidden,
        name='Bounded layout folder', parent_id=''), expected=302)
    folder = int(re.search(r'folder=(\d+)', headers['Location']).group(1))
    body, _ = request('/admin/layouts/create')
    hidden = DocumentPage(body).hidden
    source = '<!doctype html><html lang="ru"><title>List fixture</title><body>Fixture</body></html>'
    first = None
    for n in range(1, 27):
        _, headers = request('/admin/layouts', dict(hidden, name=f'HTTP layout {n:02}',
            code=f'http-layout-{n:02}', folder_id=str(folder), source=source), expected=302)
        first = first or re.search(r'/layouts/(\d+)', headers['Location']).group(1)
    request('/admin/layouts', dict(hidden, name='HTTP layout root',
        code='http-layout-root', source=source), expected=302)
    request('/admin/layouts', dict(hidden, name='HTTP literal 100%_!',
        code='http-layout-literal', folder_id=str(folder), source=source), expected=302)
    filters = {'folder': str(folder), 'q': 'HTTP layout', 'sort': 'code', 'per_page': '25'}
    body, _ = request('/admin/layouts?' + urlencode(filters))
    assert body.count('class="layout-row"') == 25
    assert 'HTTP layout 01' in body and 'HTTP layout 26' not in body and 'HTTP layout root' not in body
    body, _ = request(DocumentPage(body).link('Далее'))
    assert body.count('class="layout-row"') == 1 and 'HTTP layout 26' in body
    assert 'HTTP layout 01' not in body and 'HTTP layout root' not in body
    body, _ = request(DocumentPage(body).link('Назад'))
    assert body.count('class="layout-row"') == 25
    body, _ = request('/admin/layouts?' + urlencode(dict(filters, page='999999')))
    assert body.count('class="layout-row"') == 1 and 'HTTP layout 26' in body
    body, _ = request('/admin/layouts?' + urlencode({'q': 'HTTP layout', 'folder': ''}))
    assert body.count('class="layout-row"') == 1 and 'HTTP layout root' in body
    body, _ = request('/admin/layouts?' + urlencode({'q': '100%_!', 'folder': str(folder)}))
    assert body.count('class="layout-row"') == 1 and 'HTTP literal 100%_!' in body
    body, _ = request('/admin/layouts?' + urlencode({'q': '<script>&"', 'folder': 'all'}))
    assert '<script>&"' not in body and 'class="layout-row"' not in body
    request('/admin/layouts?q[]=bad&page[]=2&sort[]=bad&per_page[]=100')
    body, _ = request('/admin/sites/create')
    _, headers = request('/admin/sites', dict(DocumentPage(body).hidden, name='Layout foreign site',
        code='layout-http-foreign', permalink_style='clean'), expected=302)
    other = int(re.search(r'/sites/(\d+)', headers['Location']).group(1))
    choose(other)
    body, _ = request('/admin/layouts?folder=all&q=HTTP+layout')
    assert 'class="layout-row"' not in body
    request(f'/admin/layouts?folder={folder}', expected=404)
    request(f'/admin/layouts/{first}/edit', expected=404)
    body, _ = request('/admin/layouts/create')
    request('/admin/layouts', dict(DocumentPage(body).hidden, name='HTTP layout FOREIGN',
        code='http-layout-01', source=source), expected=302)
    choose(1)
    body, _ = request('/admin/layouts?folder=all&q=HTTP+layout&per_page=100')
    assert body.count('class="layout-row"') == 27 and 'HTTP layout FOREIGN' not in body
    # Leave the authenticated site exactly as the caller expects.
    print('LAYOUT LIST HTTP OK: bounded pagination, scoped folders/sites/IDs, literal search, malformed input, escaping')
