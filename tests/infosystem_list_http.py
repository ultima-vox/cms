"""Bounded Infosystem list through real authenticated PG/legacy HTTP routes."""
import re
from urllib.parse import urlencode
from document_context_http import DocumentPage


def verify_infosystem_lists(request):
    def choose(site):
        body, _ = request('/admin')
        request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'],
                'site_id': str(site)}, expected=302)

    body, _ = request('/admin/infosystems')
    _, headers = request('/admin/folders/infosystems', dict(DocumentPage(body).hidden,
        name='Bounded list folder', parent_id=''), expected=302)
    folder = int(re.search(r'folder=(\d+)', headers['Location']).group(1))
    body, _ = request('/admin/infosystems/create')
    hidden = DocumentPage(body).hidden
    for n in range(1, 27):
        request('/admin/infosystems', dict(hidden, name=f'HTTP list {n:02}',
            code=f'http-list-{n:02}', folder_id=str(folder), is_active='0'), expected=302)
    request('/admin/infosystems', dict(hidden, name='HTTP list root',
        code='http-list-root', is_active='1'), expected=302)
    request('/admin/infosystems', dict(hidden, name='HTTP literal 100%_!',
        code='http-list-literal', folder_id=str(folder), is_active='1'), expected=302)
    filters = {'folder': str(folder), 'q': 'HTTP list', 'status': 'inactive', 'sort': 'code', 'per_page': '25'}
    body, _ = request('/admin/infosystems?' + urlencode(filters))
    assert body.count('class="infosystem-row"') == 25
    assert 'HTTP list 01' in body and 'HTTP list 26' not in body and 'HTTP list root' not in body
    next_url = DocumentPage(body).link('Далее')
    body, _ = request(next_url)
    assert body.count('class="infosystem-row"') == 1 and 'HTTP list 26' in body
    assert 'HTTP list 01' not in body and 'HTTP list root' not in body
    body, _ = request('/admin/infosystems?' + urlencode(dict(filters, page='999999')))
    assert body.count('class="infosystem-row"') == 1 and 'HTTP list 26' in body
    body, _ = request('/admin/infosystems?' + urlencode({'q': 'HTTP list', 'folder': ''}))
    assert body.count('class="infosystem-row"') == 1 and 'HTTP list root' in body
    body, _ = request('/admin/infosystems?' + urlencode({'q': '100%_!', 'folder': str(folder)}))
    assert body.count('class="infosystem-row"') == 1 and 'HTTP literal 100%_!' in body
    body, _ = request('/admin/infosystems?' + urlencode({'q': '<script>&"', 'folder': 'all'}))
    assert '<script>&"' not in body and 'class="infosystem-row"' not in body
    request('/admin/infosystems?q[]=bad&status[]=inactive&page[]=2&sort[]=bad&per_page[]=100')
    body, _ = request('/admin/sites/create')
    _, headers = request('/admin/sites', dict(DocumentPage(body).hidden, name='List foreign site',
        code='list-http-foreign', permalink_style='clean'), expected=302)
    other = int(re.search(r'/sites/(\d+)', headers['Location']).group(1))
    choose(other)
    body, _ = request('/admin/infosystems?folder=all&q=HTTP+list')
    assert 'class="infosystem-row"' not in body
    request(f'/admin/infosystems?folder={folder}', expected=404)
    body, _ = request('/admin/infosystems/create')
    request('/admin/infosystems', dict(DocumentPage(body).hidden, name='HTTP list FOREIGN',
        code='http-list-01', is_active='0'), expected=302)
    choose(1)
    body, _ = request('/admin/infosystems?folder=all&q=HTTP+list&per_page=100')
    assert body.count('class="infosystem-row"') == 27 and 'HTTP list FOREIGN' not in body
    print('INFOSYSTEM LIST HTTP OK: SQL-scoped folders/site, bounded pagination/filter links, literal search, malformed input and XSS escaping')
