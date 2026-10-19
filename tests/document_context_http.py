"""Shared authenticated HTTP contract, called only by disposable install smoke tests."""
from html.parser import HTMLParser
from urllib.parse import parse_qs, urlencode, urlsplit


class DocumentPage(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.links = []
        self.hidden = {}
        self.anchor = None
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'a':
            self.anchor = [attrs.get('href', ''), '']
        if tag == 'input' and attrs.get('type') == 'hidden':
            name = attrs.get('name', '')
            if name in ('_csrf', '_site_id', '_list_site') or name.startswith('_list['):
                self.hidden[name] = attrs.get('value', '')

    def handle_data(self, value):
        if self.anchor is not None:
            self.anchor[1] += value

    def handle_endtag(self, tag):
        if tag == 'a' and self.anchor is not None:
            self.links.append(tuple(self.anchor))
            self.anchor = None

    def link(self, label):
        matches = [url for url, text in self.links if text.strip() == label]
        assert len(matches) == 1, (label, matches)
        return matches[0]


def verify_document_return_context(request, site_id, folder, foreign_folder=None, foreign_site=None):
    """request(path, data=None, expected=200) must retain the existing site's login."""
    body, _ = request('/admin/documents/create')
    hidden = DocumentPage(body).hidden
    for number in range(1, 27):
        request('/admin/documents', dict(hidden, code=f'ctx-list-{number:02}',
            name=f'Return context {number:02}', folder_id=str(folder),
            content='<p>Disposable context fixture</p>', save_mode='draft'), expected=302)

    expected = dict(folder=str(folder), q='Return context', status='unpublished',
                    sort='code', per_page='25', page='2')
    body, _ = request('/admin/documents?' + urlencode(expected))
    listing = DocumentPage(body)
    editor = next(url for url, _ in listing.links if urlsplit(url).path.endswith('/edit')
                  and urlsplit(url).path.startswith('/admin/documents/'))
    path = urlsplit(editor).path

    def assert_context(body, context=expected):
        page = DocumentPage(body)
        for label in ('К документам', 'Отмена'):
            url = urlsplit(page.link(label))
            assert not url.scheme and not url.netloc and url.path == '/admin/documents'
            actual = {key: values[0] for key, values in parse_qs(url.query, keep_blank_values=True).items()}
            assert actual == context, (actual, context)
        return page

    body, _ = request(editor)
    page = assert_context(body)
    post = dict(page.hidden, name='Return context 26', folder_id=str(folder),
                content='<p>Saved context</p>', save_mode='draft')
    _, headers = request(path.removesuffix('/edit'), post, expected=302)
    saved = headers['Location']
    assert urlsplit(saved).path == path and parse_qs(urlsplit(saved).query)['saved'] == ['1']
    body, _ = request(saved)
    assert_context(body)
    body, _ = request(path.removesuffix('/edit'), dict(post, name=''), expected=422)
    assert_context(body)
    # A second tab has its own independently encoded context.
    other = dict(expected, q='different tab', status='draft', page='1', per_page='50')
    other_url = path + '?' + urlencode({'_list_site': str(site_id), **{
        f'_list[{key}]': value for key, value in other.items()}})
    body, _ = request(other_url)
    assert_context(body, other)
    body, _ = request(editor)
    assert_context(body)

    create = listing.link('Новый документ')
    body, _ = request(create)
    page = assert_context(body)
    create_post = dict(page.hidden, code='invalid code', name='Extra context document',
                       content='<p>Extra context</p>', folder_id=str(folder), save_mode='draft')
    body, _ = request('/admin/documents', create_post, expected=422)
    assert_context(body)
    _, headers = request('/admin/documents', dict(create_post, code='ctx-created'), expected=302)
    body, _ = request(headers['Location'])
    page = assert_context(body)
    new_path = urlsplit(headers['Location']).path.removesuffix('/edit')
    _, headers = request(new_path, dict(page.hidden, name='Extra context document',
        folder_id=str(folder), content='<p>Published context</p>', save_mode='publish'), expected=302)
    assert parse_qs(urlsplit(headers['Location']).query)['published'] == ['1']
    body, _ = request(headers['Location'])
    assert_context(body)

    bad_contexts = [
        {'return': 'https://example.test/redirect'},
        {'_list_site': str(site_id), '_list': 'https://example.test/redirect'},
        {'_list_site': str(foreign_site or site_id + 1), '_list[folder]': str(folder)},
        {'_list_site[]': str(site_id), '_list[folder]': str(folder)},
        {'_list_site': str(site_id), '_list[folder][]': 'bad'},
        {'_list_site': str(site_id), '_list[folder]': '999999999999999999'},
    ]
    if foreign_folder is not None:
        bad_contexts.append({'_list_site': str(site_id), '_list[folder]': str(foreign_folder)})
    for params in bad_contexts:
        body, _ = request(path + '?' + urlencode(params))
        page = DocumentPage(body)
        assert page.link('К документам') == '/admin/documents'
        assert page.link('Отмена') == '/admin/documents'
        assert '_list_site' not in page.hidden
    for fields in ({'_list_site': str(foreign_site or site_id + 1)},
                   {'_list[folder]': str(foreign_folder or 999999999999999999)}):
        _, headers = request(path.removesuffix('/edit'), dict(post, **fields), expected=302)
        assert parse_qs(urlsplit(headers['Location']).query) == {'saved': ['1']}
        body, _ = request(headers['Location'])
        assert DocumentPage(body).link('К документам') == '/admin/documents'
    encoded = dict(expected, q='<script>&" тест')
    body, _ = request(path + '?' + urlencode({'_list_site': str(site_id), **{
        f'_list[{key}]': value for key, value in encoded.items()}}))
    page = assert_context(body, encoded)
    _, headers = request(path.removesuffix('/edit'), dict(post, **page.hidden), expected=302)
    body, _ = request(headers['Location'])
    assert_context(body, encoded)
    body, _ = request(path + '?' + urlencode({'_list_site': str(site_id), '_list[folder]': 'all',
        '_list[q][]': 'bad', '_list[page][]': 'bad', '_list[status][]': 'bad',
        '_list[sort]': 'name; DROP TABLE documents', '_list[per_page]': '999999'}))
    assert_context(body, dict(folder='all', q='', status='', sort='name', per_page='25', page='1'))
    body, _ = request(path)
    page = DocumentPage(body)
    assert page.link('К документам') == '/admin/documents'
    request(path.removesuffix('/edit'), dict(post, _csrf='invalid'), expected=419)
    request(path.removesuffix('/edit'), dict(post, _site_id=str(foreign_site or site_id + 1)), expected=409)
    # Links use the actual bounded page, not the arbitrary page requested by the client.
    body, _ = request('/admin/documents?' + urlencode(dict(expected, page='999999')))
    link = next(url for url, _ in DocumentPage(body).links if urlsplit(url).path == path)
    assert parse_qs(urlsplit(link).query)['_list[page]'] == ['2']
    print('DOCUMENT CONTEXT HTTP OK: folder/page2, save/publish/create/422, tabs, malformed/stale/foreign context, CSRF and site checks')
