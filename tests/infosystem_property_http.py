"""Owner validation through real disposable PG and legacy HTTP installations."""
from html.parser import HTMLParser
import re
from document_context_http import DocumentPage


class PropertyPage(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.inputs = {}
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and 'name' in attrs:
            self.inputs[attrs['name']] = attrs


def verify_infosystem_properties(request):
    body, _ = request('/admin/infosystems/create')
    data = dict(DocumentPage(body).hidden, name='Property validation', code='property-validation', is_active='1')
    for index, (code, name, kind) in enumerate((('event_date', 'Дата события', 'date'), ('amount', 'Число', 'number'), ('price', 'Стоимость, ₽', 'money'))):
        data.update({f'fields[{index}][code]': code, f'fields[{index}][name]': name,
                     f'fields[{index}][type]': kind, f'fields[{index}][required]': '1'})
    data['fields[2][filterable]'] = '1'
    _, headers = request('/admin/infosystems', data, expected=302)
    system = int(re.search(r'/infosystems/(\d+)', headers['Location']).group(1))
    body, _ = request('/admin/sites/create')
    _, headers = request('/admin/sites', dict(DocumentPage(body).hidden, name='Property other site',
        code='property-other-site', permalink_style='clean'), expected=302)
    other_site = int(re.search(r'/sites/(\d+)', headers['Location']).group(1))
    body, _ = request('/admin')
    request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'], 'site_id': str(other_site)}, expected=302)
    body, _ = request('/admin/infosystems/create')
    _, headers = request('/admin/infosystems', dict(DocumentPage(body).hidden, name='Foreign property system',
        code='property-validation', is_active='1'), expected=302)
    foreign_system = int(re.search(r'/infosystems/(\d+)', headers['Location']).group(1))
    body, _ = request('/admin')
    request('/admin/sites/select', {'_csrf': DocumentPage(body).hidden['_csrf'], 'site_id': '1'}, expected=302)
    create = f'/admin/infosystems/{system}/items'
    body, _ = request(create + '/create')
    valid = dict(DocumentPage(body).hidden, name='Validated item', slug='validated-item',
                 content='<p>Must survive rejected update</p>', status='draft', is_active='1',
                 **{'properties[event_date]': '2000-02-29', 'properties[amount]': '12.5', 'properties[price]': '100,05'})

    def assert_error(body, field, raw):
        attrs = PropertyPage(body).inputs[f'properties[{field}]']
        assert attrs['aria-invalid'] == 'true'
        assert attrs['aria-describedby'] == 'property-error-' + field
        assert attrs['value'] == raw
        assert 'id="property-error-' + field + '" role="alert"' in body
        assert 'Must survive rejected update' in body

    invalid = dict(valid, **{'properties[event_date]': '2026-02-31'})
    body, _ = request(create, invalid, expected=422)
    assert_error(body, 'event_date', '2026-02-31')
    # A rejected create must leave the slug free.
    _, headers = request(create, valid, expected=302)
    edit = headers['Location'].split('?')[0]
    update = edit.removesuffix('/edit')
    for field, raw in [('event_date', '2026-02-31'), ('event_date', '1900-02-29'),
                       ('amount', '1e309'), ('amount', '-1e309'), ('amount', '1e-9999'), ('price', '100.005'), ('price', '1 23'), ('price', '1e3')]:
        bad = dict(valid, name='Must not persist', **{f'properties[{field}]': raw})
        body, _ = request(update, bad, expected=422)
        assert_error(body, field, raw)
        body, _ = request(edit)
        actual = PropertyPage(body).inputs
        assert actual['name']['value'] == 'Validated item'
        assert actual['properties[event_date]']['value'] == '2000-02-29'
        assert actual['properties[amount]']['value'] == '12.5'
        assert actual['properties[price]']['value'] == '100.05'
        assert 'Must survive rejected update' in body
    body, _ = request(f'/admin/infosystems/{system}/items?properties[price]=100,05')
    assert 'Validated item' in body and 'value="100.05"' in body
    body, _ = request(f'/admin/infosystems/{system}/items?properties[price]=100.06')
    assert 'Validated item' not in body
    request(f'/admin/infosystems/{system}/items?properties[price]=100.005', expected=422)
    malformed = {key: value for key, value in valid.items() if key != 'properties[event_date]'}
    body, _ = request(update, dict(malformed, **{'properties[event_date][]': 'bad'}), expected=422)
    assert PropertyPage(body).inputs['properties[event_date]']['aria-invalid'] == 'true'
    request(update, dict(valid, _csrf='invalid'), expected=419)
    request(update, dict(valid, _site_id='999999'), expected=409)
    request(f'/admin/infosystems/{foreign_system}/items', valid, expected=404)
    request(f'/admin/infosystems/{foreign_system}/items/create', expected=404)
    # Corrected boundary values are accepted after rejection, without schema changes.
    _, headers = request(update, dict(valid, **{'properties[event_date]': '2024-02-29', 'properties[amount]': '-1250.5'}), expected=302)
    body, _ = request(headers['Location'])
    actual = PropertyPage(body).inputs
    assert actual['properties[event_date]']['value'] == '2024-02-29'
    assert actual['properties[amount]']['value'] == '-1250.5'
    assert 'aria-invalid="true"' not in body
    for raw, expected in [('35 000,50', '35 000.50'), ('800', '800'), ('0.01', '0.01'), ('0', '0')]:
        _, headers = request(update, dict(valid, **{'properties[price]': raw}), expected=302)
        body, _ = request(headers['Location'])
        assert PropertyPage(body).inputs['properties[price]']['value'] == expected
    print('INFOSYSTEM PROPERTY HTTP OK: calendar/numeric rejection, field errors, create/update atomicity, correction, CSRF and site guards')
    return edit
