// Real disposable owner HTTP forms: two tabs keep independent expected revisions.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const base = process.env.MVP_URL || 'http://127.0.0.1:8086';
(async () => {
    assert.equal(new URL(base).hostname, '127.0.0.1');
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    for (const [width, scripts] of [[1440, true], [1024, true], [390, true], [390, false]]) {
        const context = await browser.newContext({storageState: process.env.MVP_BROWSER_STATE, javaScriptEnabled: scripts, viewport: {width, height: 1000}});
        const errors = [];
        context.on('page', page => page.on('pageerror', error => errors.push(error.message)));
        const first = await context.newPage();
        await first.goto(base + '/admin/documents/create');
        const created = await context.request.post(base + '/admin/documents', {maxRedirects: 0, form: {
            _csrf: await first.locator('form.admin-form input[name=_csrf]').inputValue(),
            _site_id: await first.locator('form.admin-form input[name=_site_id]').inputValue(),
            code: `revision-browser-${width}-${scripts}`, name: 'Revision browser', content: '<p>Original</p>', save_mode: 'draft',
        }});
        assert.equal(created.status(), 302, await created.text());
        const edit = new URL(created.headers().location, base);
        edit.search = new URLSearchParams({_list_site: '1', '_list[folder]': 'all', '_list[q]': 'Revision browser', '_list[sort]': 'code', '_list[per_page]': '25', '_list[page]': '1'});
        const second = await context.newPage();
        await first.goto(edit.href);
        await second.goto(edit.href);
        const originalRevision = await second.locator('[name=expected_revision]').inputValue();
        await first.locator('textarea[name=content]').fill('<p>Winner from first tab</p>');
        await Promise.all([first.waitForURL(url => url.searchParams.get('published') === '1'), first.getByRole('button', {name: 'Опубликовать новую версию', exact: true}).click()]);
        const losingText = '<p>Unmerged second tab &amp; edits</p>';
        await second.locator('textarea[name=content]').fill(losingText);
        await second.locator('input[name=name]').fill('Second tab changes');
        for (let retry = 0; retry < 2; retry++) {
            await Promise.all([second.waitForResponse(r => r.request().method() === 'POST' && r.status() === 409), second.getByRole('button', {name: 'Сохранить черновик', exact: true}).click()]);
            assert.equal(await second.locator('textarea[name=content]').inputValue(), losingText);
            assert.equal(await second.locator('input[name=name]').inputValue(), 'Second tab changes');
            assert.equal(await second.locator('[name=expected_revision]').inputValue(), originalRevision);
            assert.equal(await second.locator('form.admin-form').getAttribute('aria-busy'), null);
            assert.match(await second.getByRole('link', {name: 'К документам', exact: true}).getAttribute('href'), /q=Revision%20browser/);
        }
        await second.evaluate(() => window.scrollTo(0, 0));
        assert(await second.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await second.screenshot({path: `/tmp/cms-layout-document-conflict-${width}-${scripts ? 'js' : 'native'}.png`});
        const reload = second.getByRole('link', {name: 'Открыть актуальную версию', exact: true});
        assert.equal(await reload.getAttribute('target'), '_blank');
        const popup = context.waitForEvent('page');
        await reload.click();
        const current = await popup;
        await current.waitForLoadState();
        assert.equal(await current.locator('textarea[name=content]').inputValue(), '<p>Winner from first tab</p>');
        assert.notEqual(await current.locator('[name=expected_revision]').inputValue(), originalRevision);
        // Transfer only after explicitly opening/reviewing the current version; no automatic rebase/overwrite.
        await current.locator('textarea[name=content]').fill(losingText);
        await Promise.all([current.waitForURL(url => url.searchParams.get('saved') === '1'), current.getByRole('button', {name: 'Сохранить черновик', exact: true}).click()]);
        assert.equal(await second.locator('textarea[name=content]').inputValue(), losingText);
        assert.equal(await current.locator('tbody tr').count(), 3);
        assert.deepEqual(errors, []);
        await context.close();
    }
    await browser.close();
    console.log('DOCUMENT CONFLICT BROWSER OK: three widths, two tabs, repeated 409 retains text/token/context, explicit review/recovery, no-JS');
})().catch(error => { console.error(error); process.exit(1); });
