"""Revision conflicts through real owner routes on each supported database."""
import re
from document_context_http import DocumentPage


def verify_document_revisions(request, query):
    body, _ = request('/admin/documents/create')
    _, folder_headers = request('/admin/folders/documents', dict(DocumentPage(body).hidden, name='Conflict move target'), expected=302)
    folder = int(re.search(r'folder=(\d+)', folder_headers['Location']).group(1))
    _, headers = request('/admin/documents', dict(DocumentPage(body).hidden, name='Revision HTTP',
        code='revision-http', content='<p>Original</p>', save_mode='publish', author_user_id='0'), expected=302)
    path = headers['Location'].split('?')[0].removesuffix('/edit')
    doc = int(re.search(r'/documents/(\d+)', path).group(1))
    editor = path + '/edit'
    body_a, _ = request(editor)
    body_b, _ = request(editor)
    a, b = DocumentPage(body_a), DocumentPage(body_b)
    assert a.hidden['expected_revision'] == b.hidden['expected_revision']
    author = int(query(f'SELECT author_user_id FROM document_versions WHERE document_id={doc}'))
    assert author > 0  # The forged POST author=0 was ignored; attribution came from authentication.
    payload = dict(a.hidden, name='Winner HTTP', content='<p>Winner</p>', save_mode='publish')
    request(path, payload, expected=302)

    def state():
        return [query(sql) for sql in [
            f'SELECT name FROM documents WHERE id={doc}',
            f'SELECT COALESCE(folder_id,0) FROM documents WHERE id={doc}',
            f'SELECT COUNT(*) FROM document_versions WHERE document_id={doc}',
            f"SELECT content FROM document_versions WHERE document_id={doc} AND status='published'",
            f"SELECT COUNT(*) FROM audit_log WHERE entity_type='document' AND entity_id={doc}",
        ]]
    winner = state()
    assert winner[:4] == ['Winner HTTP', '0', '2', '<p>Winner</p>']
    loser = dict(b.hidden, name='Losing HTTP', content='<p>Keep my edits & changes</p>', save_mode='draft', folder_id=str(folder))
    body, _ = request(path, loser, expected=409)
    form = DocumentPage(body)
    assert form.hidden['expected_revision'] == b.hidden['expected_revision']
    assert 'Keep my edits &amp; changes' in body and 'role="alert"' in body
    assert form.link('Открыть актуальную версию').startswith(editor)
    assert state() == winner, 'Conflict mutated metadata/history/publication/audit'
    # Old pre-update forms and malformed tokens must never be silently rebased to the winner.
    for revision in (None, '', '-1', '9' * 100, ['bad']):
        data = dict(loser)
        data.pop('expected_revision')
        if revision is not None:
            data['expected_revision[]' if isinstance(revision, list) else 'expected_revision'] = revision
        body, _ = request(path, data, expected=409)
        hidden = DocumentPage(body).hidden['expected_revision']
        assert hidden != query(f'SELECT MAX(id) FROM document_versions WHERE document_id={doc}')
        assert state() == winner
    request(path, dict(loser, _csrf='invalid'), expected=419)
    request(path, dict(loser, _site_id='999999999'), expected=409)
    assert state() == winner
    current, _ = request(editor)
    latest = DocumentPage(current)
    request(path, dict(loser, **latest.hidden), expected=302)
    assert query(f'SELECT COUNT(*) FROM document_versions WHERE document_id={doc}') == '3'
    assert query(f'SELECT COUNT(*) FROM document_versions WHERE document_id={doc} AND author_user_id={author}') == '3'
    print('DOCUMENT REVISION HTTP OK: stale/missing/malformed 409 retains text/token; CSRF/site; server author; unchanged conflict audit/history/publication; explicit reload saves')
