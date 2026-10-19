"""Real page → owner editor → page journey for disposable supported-DB installs."""
import re
from urllib.parse import urlencode, urlsplit, parse_qs
from document_context_http import DocumentPage


def verify_page_editor_handoff(request, query):
    body, _ = request('/admin/structure/create')
    parents = re.search(r'<select[^>]*name="parent_id"[^>]*>(.*?)</select>', body, re.S).group(1)
    root = re.search(r'<option value="([1-9][0-9]*)"', parents).group(1)
    data = dict(DocumentPage(body).hidden, name='Handoff page', title='Handoff page', parent_id=root,
                slug='editor-handoff', page_type='documents.page', status='draft', is_active='1', sorting='0')
    _, headers = request('/admin/structure', data, expected=302)
    page_path = urlsplit(headers['Location']).path
    node = re.search(r'/structure/(\d+)/', page_path).group(1)
    body, _ = request(headers['Location'])
    editor = DocumentPage(body).link('Редактировать содержимое')
    query = parse_qs(urlsplit(editor).query)
    assert query == {'_page': [node], '_page_site': ['1']}
    edit_path = urlsplit(editor).path
    assert re.fullmatch(r'/admin/documents/\d+/edit', edit_path)
    body, _ = request(editor)
    form = DocumentPage(body)
    assert form.link('К странице') == page_path and form.link('Отмена') == page_path
    post = dict(form.hidden, name='Handoff document', content='<p>Handoff content</p>', save_mode='draft')
    update = edit_path.removesuffix('/edit')
    for mode in ('draft', 'publish'):
        _, headers = request(update, dict(post, save_mode=mode), expected=302)
        assert parse_qs(urlsplit(headers['Location']).query)['_page'] == [node]
        body, _ = request(headers['Location'])
        assert DocumentPage(body).link('К странице') == page_path
        assert '<p>Handoff content</p>' in body or '&lt;p&gt;Handoff content&lt;/p&gt;' in body
    body, _ = request(update, dict(post, name=''), expected=422)
    assert DocumentPage(body).link('Отмена') == page_path
    body, _ = request(page_path)
    assert 'value="draft" selected' in body, 'Editing content silently changed Structure publication'
    assert DocumentPage(body).link('Редактировать содержимое') == editor
    body, _ = request(edit_path)
    assert all(label != 'К странице' for _, label in DocumentPage(body).links), 'Direct editor unexpectedly gained page state'
    for params in ({'_page': root, '_page_site': '1'}, {'_page': node, '_page_site': '999999'},
                   {'_page[]': node, '_page_site': '1'}, {'_page': 'https://example.test', '_page_site': '1'},
                   {'return': 'https://example.test'}):
        body, _ = request(edit_path + '?' + urlencode(params))
        form = DocumentPage(body)
        assert all(label != 'К странице' for _, label in form.links)
        assert form.link('Отмена') == '/admin/documents'
    request(update, dict(post, _csrf='invalid'), expected=419)
    body, _ = request('/admin/sites/create')
    _, headers = request('/admin/sites', dict(DocumentPage(body).hidden, name='Handoff other site',
        code='handoff-other-site', permalink_style='clean'), expected=302)
    other = re.search(r'/sites/(\d+)/', headers['Location']).group(1)
    body, _ = request('/admin')
    request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'], 'site_id': other}, expected=302)
    body, _ = request('/admin/documents/create')
    _, headers = request('/admin/documents', dict(DocumentPage(body).hidden, name='Other handoff document',
        code='node-' + node, content='Other site', save_mode='draft'), expected=302)
    foreign_edit = urlsplit(headers['Location']).path
    request(page_path, expected=404)
    request(editor, expected=404)
    body, _ = request(foreign_edit + '?' + urlencode({'_page': node, '_page_site': '1'}))
    assert all(label != 'К странице' for _, label in DocumentPage(body).links)
    request(foreign_edit.removesuffix('/edit'), post, expected=409)
    body, _ = request('/admin')
    request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'], 'site_id': '1'}, expected=302)
    request(foreign_edit, expected=404)
    body, _ = request(editor)
    assert DocumentPage(body).link('К странице') == page_path
    # Existing binding/page configuration has no general browser editor yet. Seed only
    # that configuration in the disposable fixture; exercise all owner routes via HTTP.
    body, _ = request('/admin/infosystems/create')
    _, headers = request('/admin/infosystems', dict(DocumentPage(body).hidden, name='Handoff IS',
        code='editor-handoff-system', is_active='1'), expected=302)
    system = re.search(r'/infosystems/(\d+)', headers['Location']).group(1)
    body, _ = request(f'/admin/infosystems/{system}/items/create')
    _, headers = request(f'/admin/infosystems/{system}/items', dict(DocumentPage(body).hidden,
        name='Linked draft item', slug='linked-item', content='<p>IS handoff</p>', status='draft', is_active='1'), expected=302)
    item_edit = urlsplit(headers['Location']).path
    body, _ = request('/admin/structure/create')
    _, headers = request('/admin/structure', dict(DocumentPage(body).hidden, name='Handoff IS page',
        title='Handoff IS page', parent_id=root, slug='editor-is-handoff', page_type='documents.page',
        status='draft', is_active='1', sorting='0'), expected=302)
    is_page = urlsplit(headers['Location']).path
    is_node = int(re.search(r'/structure/(\d+)/', is_page).group(1))
    query(f"UPDATE nodes SET page_type='infosystem.list',page_config='{{}}' WHERE site_id=1 AND id={is_node}")
    query(f"INSERT INTO node_module_bindings(node_id,module_code,binding_code,target_key) VALUES({is_node},'infosystem','primary','editor-handoff-system')")
    body, _ = request(is_page)
    is_editor = DocumentPage(body).link('Редактировать содержимое')
    assert urlsplit(is_editor).path == f'/admin/infosystems/{system}'
    body, _ = request(is_editor)
    assert DocumentPage(body).link('К странице') == is_page
    query(f"UPDATE nodes SET page_type='infosystem.item',page_config='{{\"item_path\":\"/linked-item\"}}' WHERE site_id=1 AND id={is_node}")
    body, _ = request(is_page)
    is_editor = DocumentPage(body).link('Редактировать содержимое')
    assert urlsplit(is_editor).path == item_edit
    body, _ = request(is_editor)
    item_post = dict(DocumentPage(body).hidden, name='Linked draft item', slug='linked-item',
        content='<p>IS handoff</p>', status='draft', is_active='1')
    assert DocumentPage(body).link('Отмена') == is_page
    body, _ = request(item_edit.removesuffix('/edit'), dict(item_post, name=''), expected=422)
    assert DocumentPage(body).link('К странице') == is_page
    _, headers = request(item_edit.removesuffix('/edit'), item_post, expected=302)
    body, _ = request(headers['Location'])
    assert DocumentPage(body).link('К странице') == is_page
    query(f"UPDATE nodes SET page_config='{{\"item_path\":\"/missing-item\"}}' WHERE site_id=1 AND id={is_node}")
    body, _ = request(is_page)
    assert all(label != 'Редактировать содержимое' for _, label in DocumentPage(body).links)
    assert 'Редактор содержимого недоступен' in body
    query(f"UPDATE nodes SET page_config='{{\"item_path\":\"/linked-item\"}}' WHERE site_id=1 AND id={is_node}")
    print('PAGE EDITOR HTTP OK: generated document handoff, draft/publish/422, unchanged page status, same-code site isolation, stale/forged context and CSRF')
    return {'page': page_path, 'editor': edit_path, 'is_page': is_page, 'is_editor': item_edit}
