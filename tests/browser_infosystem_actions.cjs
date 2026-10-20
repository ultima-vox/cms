// Real owner forms on the disposable loopback fixture. No application handlers are mocked.
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
        await page.goto(base + '/admin/infosystems/create');
        assert.equal(await page.getByRole('group', {name: 'Действия инфосистемы', exact: true}).count(), 1);
        const fields = {
            _csrf: await page.locator('#infosystem-form input[name=_csrf]').inputValue(),
            _site_id: await page.locator('#infosystem-form input[name=_site_id]').inputValue(),
            name: `Action schema ${width} ${scripts}`, code: `action-schema-${width}-${scripts ? 'js' : 'native'}`, is_active: '1',
        };
        // Fixture setup through the real authenticated HTTP owner endpoint; 33 fields make a genuinely long form.
        for (let i = 0; i < 33; i++) {
            fields[`fields[${i}][code]`] = i === 32 ? 'price' : `detail_${i}`;
            fields[`fields[${i}][name]`] = i === 32 ? 'Цена' : `Параметр ${i}`;
            fields[`fields[${i}][type]`] = i === 32 ? 'money' : 'text';
        }
        const setup = await context.request.post(base + '/admin/infosystems', {form: fields, maxRedirects: 0});
        assert.equal(setup.status(), 302, await setup.text());
        const system = setup.headers().location.match(/\/infosystems\/(\d+)/)[1];
        if (width === 1440) {
            await page.goto(base + `/admin/infosystems/${system}/edit`);
            assert.equal(await page.locator('[data-field-row]').count(), 33);
            await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && r.status() === 302), page.getByRole('group', {name: 'Действия инфосистемы', exact: true}).getByRole('button', {name: 'Сохранить', exact: true}).click()]);
            assert.equal(await page.locator('[data-field-row]').count(), 33, 'Schema fields lost on native save');
            await page.goto(base + `/admin/infosystems/${system}/groups/create`);
            const groupBar = page.getByRole('group', {name: 'Действия группы', exact: true});
            assert.equal(await groupBar.count(), 1);
            await page.locator('input[name=name]').fill('Action group');
            await page.locator('input[name=slug]').fill('action-group');
            await Promise.all([page.waitForURL(url => url.pathname === `/admin/infosystems/${system}` && url.searchParams.get('saved') === '1'), groupBar.getByRole('button', {name: 'Сохранить', exact: true}).click()]);
        }
        await page.goto(base + `/admin/infosystems/${system}/items/create`);
        const form = page.locator('form.admin-form--actions');
        const bar = page.getByRole('group', {name: 'Действия элемента', exact: true});
        const save = bar.getByRole('button', {name: 'Сохранить', exact: true});
        assert.equal(await page.locator('.admin-actions--sticky').count(), 1);
        await page.locator('input[name=name]').fill(`Action item ${width}`);
        await page.locator('input[name=slug]').fill(`action-item-${width}`);
        if (scripts) await page.locator('[data-tab=properties]').click();
        const price = form.locator('[name="properties[price]"]');
        await price.fill('100.005');
        const bounds = await form.boundingBox();
        for (const y of [0, bounds.y + 500, bounds.y + bounds.height - height + 20]) {
            await page.evaluate(value => window.scrollTo(0, value), Math.max(0, y));
            const box = await bar.boundingBox();
            assert(box.y >= -1 && box.y + box.height <= height + 1, `Infosystem actions outside viewport at ${width}/${height}`);
        }
        await page.screenshot({path: `/tmp/cms-layout-infosystem-actions-${width}-${scripts ? 'js' : 'native'}.png`, fullPage: true});
        await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && r.status() === 422), save.click()]);
        assert.equal(await price.getAttribute('aria-invalid'), 'true');
        assert.equal(await price.inputValue(), '100.005');
        assert.equal(await form.getAttribute('aria-busy'), null);
        if (scripts) {
            assert.equal(await page.locator('[data-tab=properties]').getAttribute('aria-selected'), 'true');
            await page.waitForFunction(() => {
                const field = document.querySelector('[name="properties[price]"]').getBoundingClientRect();
                const actions = document.querySelector('.admin-actions--sticky').getBoundingClientRect();
                return field.bottom <= actions.top && field.top >= 0;
            });
        }
        await price.fill('100.05');
        await Promise.all([page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('saved') === '1'), save.click()]);
        assert.equal(await price.inputValue(), '100.05');
        for (const status of ['published', 'draft']) {
            if (scripts) await page.locator('[data-tab=publish]').click();
            await form.locator('select[name=status]').selectOption(status);
            const posted = page.waitForRequest(request => request.method() === 'POST' && /\/items\/\d+$/.test(new URL(request.url()).pathname));
            await save.click();
            assert.equal(new URLSearchParams((await posted).postData()).get('status'), status);
            assert.equal(await form.locator('select[name=status]').inputValue(), status);
            assert.equal(await form.getAttribute('aria-busy'), null);
            assert.equal(await save.getAttribute('aria-disabled'), null);
        }
        await context.close();
    }
    assert.deepEqual(errors, []);
    await browser.close();
    console.log('INFOSYSTEM ACTIONS OK: native schema/group/item forms, 33-field geometry, 422 focus, preserved publication status, no-JS, 3 widths/effective zoom');
})().catch(error => { console.error(error); process.exit(1); });
