// Real native form POSTs on the disposable loopback MVP fixture, never a live site.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const base = process.env.MVP_URL || 'http://127.0.0.1:8086';
(async () => {
    assert.equal(new URL(base).hostname, '127.0.0.1');
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const errors = [];
    for (const [width, height, scripts] of [[1440, 1000, true], [1024, 1000, true], [390, 1000, true], [720, 500, true], [390, 1000, false]]) {
        const context = await browser.newContext({storageState: process.env.MVP_BROWSER_STATE, javaScriptEnabled: scripts, viewport: {width, height}});
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(base + '/admin/documents/create');
        const form = page.locator('form.admin-form--actions');
        const bar = page.getByRole('group', {name: 'Действия документа', exact: true});
        const draft = bar.getByRole('button', {name: 'Сохранить черновик', exact: true});
        const publish = bar.getByRole('button', {name: 'Опубликовать новую версию', exact: true});
        assert.equal(await page.locator('.admin-actions--sticky').count(), 1);
        assert.equal(await draft.count(), 1);
        assert.equal(await publish.count(), 1);
        // Native required validation must not leave the form pending.
        await draft.click();
        assert.equal(await form.getAttribute('aria-busy'), null);
        await page.getByLabel('Название', {exact: true}).fill('   ');
        await form.locator('input[name=code]').fill(`actions-${width}-${scripts ? 'js' : 'native'}`);
        await page.locator('textarea[name=content]').fill('<p>Action bar original content</p>');
        // Simulate a user-resized long textarea; no application DOM or logic is mocked.
        await page.locator('textarea[name=content]').evaluate(field => { field.style.height = '1600px'; });
        const formBounds = await form.boundingBox();
        for (const scroll of [0, formBounds.y + 500, formBounds.y + formBounds.height - height + 20]) {
            await page.evaluate(y => window.scrollTo(0, y), Math.max(0, scroll));
            const box = await bar.boundingBox();
            assert(box.y >= -1 && box.y + box.height <= height + 1, `Action bar outside viewport at ${width}/${height}/${scroll}`);
            assert.equal(await draft.isVisible(), true);
            assert.equal(await publish.isVisible(), true);
            const cancel = await bar.getByRole('link', {name: 'Отмена', exact: true}).boundingBox();
            assert(cancel.y >= box.y && cancel.y + cancel.height <= box.y + box.height + 1);
        }
        await page.getByLabel('Название', {exact: true}).focus();
        await page.waitForFunction(() => {
            const field = document.querySelector('input[name=name]').getBoundingClientRect();
            const actions = document.querySelector('.admin-actions--sticky').getBoundingClientRect();
            return field.bottom <= actions.top && field.top >= 0;
        });
        await page.screenshot({path: `/tmp/cms-layout-document-actions-${width}-${scripts ? 'js' : 'native'}.png`, fullPage: true});
        // Browser-valid whitespace reaches server validation; a 422 must retain inputs and unlock actions.
        await Promise.all([page.waitForResponse(response => response.request().method() === 'POST' && response.status() === 422), draft.click()]);
        assert.equal(await page.getByRole('alert').count(), 1);
        assert.equal(await form.getAttribute('aria-busy'), null);
        assert.equal(await page.locator('textarea[name=content]').inputValue(), '<p>Action bar original content</p>');
        await page.getByLabel('Название', {exact: true}).fill(`Action bar ${width} ${scripts}`);
        if (scripts) {
            // Two real requestSubmit calls in one task: a late cancellation leaves the pending guard
            // in place until event dispatch finishes, then recovery must unlock the form.
            const attempts = await page.evaluate(() => {
                const owner = document.querySelector('form.admin-form--actions');
                const states = [];
                const cancel = event => {
                    states.push({rejected: event.defaultPrevented, busy: owner.getAttribute('aria-busy')});
                    event.preventDefault();
                };
                document.addEventListener('submit', cancel);
                owner.requestSubmit(owner.querySelector('button[value=publish]'));
                owner.requestSubmit(owner.querySelector('button[value=draft]'));
                document.removeEventListener('submit', cancel);
                return states;
            });
            assert.deepEqual(attempts, [{rejected: false, busy: 'true'}, {rejected: true, busy: 'true'}]);
            await page.waitForFunction(() => !document.querySelector('form.admin-form--actions').hasAttribute('aria-busy'));
            let pending;
            await page.exposeFunction('__recordPending', state => { pending = state; });
            await page.evaluate(() => document.addEventListener('submit', event => {
                const owner = event.target;
                window.__recordPending({busy: owner.getAttribute('aria-busy'),
                    ariaDisabled: event.submitter.getAttribute('aria-disabled'), disabled: event.submitter.disabled,
                    message: owner.querySelector('[role=status]').textContent});
            }, {once: true}));
            const posted = page.waitForRequest(request => request.method() === 'POST' && new URL(request.url()).pathname === '/admin/documents');
            await publish.focus();
            await page.keyboard.press('Enter');
            const request = await posted;
            assert.equal(new URLSearchParams(request.postData()).get('save_mode'), 'publish', 'Successful submitter was lost');
            await page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('published') === '1');
            assert.deepEqual(pending, {busy: 'true', ariaDisabled: 'true', disabled: false, message: 'Сохранение выполняется…'});
        } else {
            await Promise.all([page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('published') === '1'), publish.click()]);
        }
        await page.locator('textarea[name=content]').fill('<p>Action bar next draft</p>');
        await Promise.all([page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('saved') === '1'), draft.click()]);
        assert.equal(await form.getAttribute('aria-busy'), null);
        assert.equal(await draft.getAttribute('aria-disabled'), null);
        assert.equal(await page.locator('textarea[name=content]').inputValue(), '<p>Action bar next draft</p>');
        if (scripts && width === 390) {
            await page.setViewportSize({width, height: 320});
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.admin-actions--sticky')).position === 'static');
            // Small visual/layout viewport uses in-flow controls, never an overlay over the keyboard/field.
            await page.getByLabel('Название', {exact: true}).focus();
            assert.equal(await page.getByLabel('Название', {exact: true}).evaluate(node => node === document.activeElement), true);
        }
        await context.close();
    }
    assert.deepEqual(errors, []);
    await browser.close();
    console.log('DOCUMENT ACTIONS OK: one reachable action set, native draft/publish POST, duplicate guard, canceled submit, 422, no-JS, 3 widths/effective zoom, short viewport');
})().catch(error => { console.error(error); process.exit(1); });
