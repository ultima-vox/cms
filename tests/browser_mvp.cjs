const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const base = process.env.MVP_URL || 'http://127.0.0.1:8086';
(async () => {
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const context = await browser.newContext({storageState: process.env.MVP_BROWSER_STATE});
    const errors = [];
    await context.exposeFunction('__recordCspViolation', (directive, blocked) => errors.push(`CSP ${directive}: ${blocked}`));
    await context.addInitScript(() => window.addEventListener('securitypolicyviolation', event => window.__recordCspViolation(event.violatedDirective, event.blockedURI)));
    const page = await context.newPage();
    const fixtures = JSON.parse(require('node:fs').readFileSync('/tmp/cms-folder-browser.json', 'utf8'));
    page.on('pageerror', error => errors.push(error.message));
    const listContext = url => Object.fromEntries(['folder', 'q', 'status', 'sort', 'per_page', 'page'].map(key => [
        key, url.searchParams.get(key) ?? ({page: '1', sort: 'name', per_page: '25'}[key] || ''),
    ]));
    const documentRoundTrip = async width => {
        const expected = listContext(new URL(page.url()));
        for (const label of ['К документам', 'Отмена']) {
            await Promise.all([
                page.waitForURL(url => /^\/admin\/documents\/\d+\/edit$/.test(url.pathname)),
                page.locator('tbody tr').first().locator('a').first().click(),
            ]);
            if (label === 'К документам') {
                await page.locator('textarea[name=content]').fill(`<p>List context ${width}px</p>`);
                await Promise.all([
                    page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('saved') === '1'),
                    page.getByRole('button', {name: 'Сохранить черновик', exact: true}).click(),
                ]);
            }
            await Promise.all([
                page.waitForURL(url => url.pathname === '/admin/documents'),
                page.getByRole('link', {name: label, exact: true}).click(),
            ]);
            assert.deepEqual(listContext(new URL(page.url())), expected, `Lost list context at ${width}px`);
            assert.equal(await page.locator('tbody tr').count(), 1);
            assert.equal(await page.getByLabel('Название или код').inputValue(), expected.q);
            assert.equal(await page.getByLabel('Публикация', {exact: true}).inputValue(), expected.status);
            assert.equal(await page.getByLabel('Сортировка', {exact: true}).inputValue(), expected.sort);
            assert.equal(await page.getByLabel('На странице', {exact: true}).inputValue(), expected.per_page);
        }
    };
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto(base + '/admin/modules');
        const navigation = page.getByRole('navigation', {name: 'Основная навигация'});
        const openNavigation = async () => {
            if (width <= 800) {
                await page.locator('.admin-sections > summary').focus();
                await page.keyboard.press('Space');
                await page.locator('.admin-local > summary').focus();
                await page.keyboard.press('Space');
            }
        };
        await openNavigation();
        assert.deepEqual(await navigation.getByRole('link').allTextContents(), ['Обзор', 'Контент', 'Оформление', 'Система']);
        const local = page.getByRole('navigation', {name: 'Меню раздела', exact: true});
        assert.equal(await local.locator('a[aria-current="page"]').getAttribute('href'), '/admin/modules');
        assert.equal(await local.locator('a[href="/admin/documents"]').count(), 0);
        assert.equal(await page.getByRole('button', {name: 'Поиск и команды', exact: true}).isVisible(), true);
        // An old desktop collapse preference must never hide the new labels.
        await page.evaluate(() => localStorage.setItem('uv-admin-sidebar-collapsed', '1'));
        await page.reload();
        await openNavigation();
        await navigation.getByRole('link', {name: 'Контент', exact: true}).click();
        if (width <= 800) await page.locator('.admin-local > summary').click();
        await local.getByRole('link', {name: 'Документы', exact: true}).click();
        assert.equal(new URL(page.url()).pathname, '/admin/documents');
        if (width <= 800) await page.locator('.admin-sections > summary').click();
        assert.equal(await navigation.getByRole('link', {name: 'Контент', exact: true}).getAttribute('aria-current'), 'true');
        // Global palette retains allowed destinations outside the active section.
        await page.keyboard.press('Control+k');
        const palette = page.getByRole('dialog', {name: 'Поиск и команды', exact: true});
        await palette.locator('[data-command-input]').fill('Макеты');
        await page.keyboard.press('Enter');
        await page.waitForURL(base + '/admin/layouts');
        if (width <= 800) {
            const summary = page.locator('.admin-local > summary');
            await summary.focus();
            await page.keyboard.press('Space');
            await local.getByRole('link').first().focus();
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('.admin-local').getAttribute('open'), null);
            assert.equal(await summary.evaluate(node => node === document.activeElement), true);
        }
        await page.goto(base + '/admin/modules');
        await page.screenshot({path: `/tmp/cms-layout-admin-${width}.png`, fullPage: true});
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Admin overflow at ${width}px`);
        for (const panel of await page.locator('.module-upload-panel').all()) {
            const bounds = await panel.boundingBox();
            for (const input of await panel.locator('input[type=file], button').all()) {
                const field = await input.boundingBox();
                assert(field.x >= bounds.x && field.x + field.width <= bounds.x + bounds.width + 1, `Upload control escapes panel at ${width}px`);
            }
        }
        await page.goto(base + '/admin/documents?folder=all');
        assert.equal(await page.locator('.admin-folder-create').getAttribute('open'), null);
        await page.locator('.admin-folder-create summary').click();
        assert.equal(await page.locator('.admin-folder-create input[name=name]').isVisible(), true);
        await page.locator('.admin-folder-create summary').click();
        await page.getByLabel('Название или код').fill('Browser list');
        await page.getByLabel('Публикация', {exact: true}).selectOption('unpublished');
        await page.getByLabel('Сортировка', {exact: true}).selectOption('code');
        await Promise.all([page.waitForURL(url => url.searchParams.get('q') === 'Browser list'), page.getByRole('button', {name: 'Применить', exact: true}).click()]);
        assert.equal(await page.locator('tbody tr').count(), 25);
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Document list overflow at ${width}px`);
        await page.screenshot({path: `/tmp/cms-layout-documents-${width}.png`, fullPage: true});
        await Promise.all([page.waitForURL(url => url.searchParams.get('page') === '2'), page.getByRole('link', {name: 'Далее', exact: true}).click()]);
        assert.equal(await page.locator('tbody tr').count(), 1);
        assert.equal(await page.getByLabel('Название или код').inputValue(), 'Browser list');
        assert.equal(await page.getByLabel('Публикация', {exact: true}).inputValue(), 'unpublished');
        assert.equal(new URL(page.url()).searchParams.get('folder'), 'all');
        await documentRoundTrip(width);
        await page.goto(base + '/admin/documents?' + new URLSearchParams({
            folder: String(fixtures.folder), q: 'Return context', status: 'unpublished', sort: 'code', per_page: '25', page: '2',
        }));
        assert.equal(await page.locator('tbody tr').count(), 1);
        await documentRoundTrip(width);
        await page.goto(base + '/admin/infosystems?folder=all');
        await page.getByLabel('Название или код').fill('Browser catalog');
        await page.getByLabel('Активность', {exact: true}).selectOption('inactive');
        await page.getByLabel('Сортировка', {exact: true}).selectOption('code');
        await Promise.all([page.waitForURL(url => url.searchParams.get('q') === 'Browser catalog'), page.getByRole('button', {name: 'Применить', exact: true}).click()]);
        assert.equal(await page.locator('.infosystem-row').count(), 25);
        const metrics = page.locator('.infosystem-row').first().locator('.infosystem-row__metric');
        assert.deepEqual(await metrics.locator('.infosystem-row__metric-label').allTextContents(), ['Группы', 'Элементы', 'Узлы']);
        if (width <= 1050) {
            const boxes = await metrics.evaluateAll(nodes => nodes.map(node => ({top: node.getBoundingClientRect().top, labelWidth: node.firstElementChild.getBoundingClientRect().width})));
            assert(boxes.every(box => box.labelWidth > 20 && Math.abs(box.top - boxes[0].top) < 1), 'Mobile counters must have readable labels in one row');
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Infosystem list overflow at ${width}px`);
        await page.screenshot({path: `/tmp/cms-layout-infosystems-${width}.png`, fullPage: true});
        await Promise.all([page.waitForURL(url => url.searchParams.get('page') === '2'), page.getByRole('link', {name: 'Далее', exact: true}).click()]);
        assert.equal(await page.locator('.infosystem-row').count(), 1);
        assert.equal(await page.getByLabel('Активность', {exact: true}).inputValue(), 'inactive');
        assert.equal(await page.getByLabel('Название или код').inputValue(), 'Browser catalog');
        assert.equal(new URL(page.url()).searchParams.get('folder'), 'all');
        const failed = [];
        page.on('response', response => { if (response.status() >= 400) failed.push(response.url()); });
        await page.goto(base + '/mechaniki');
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({path: `/tmp/cms-layout-mechaniki-${width}.png`, fullPage: true});
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Mechaniki overflow at ${width}px`);
        assert.equal(await page.locator('h1').count(), 1);
        assert.equal(await page.locator('img').evaluateAll(images => images.every(img => img.complete && img.naturalWidth > 0)), true, 'Broken pilot image');
        assert.equal(failed.length, 0, `Failed resources: ${failed}`);
        if (width === 390) {
            const toggle = page.locator('.mk-menu-toggle');
            await toggle.click();
            assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
            assert.equal(await page.locator('#mk-navigation').isVisible(), true);
            await page.keyboard.press('Escape');
            assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        }
    }
    await page.goto(base + `/admin/folders/documents/${fixtures.folder}/edit`);
    let deletes = 0;
    page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith(`/folders/documents/${fixtures.folder}/delete`)) deletes++; });
    const dialogEvent = page.waitForEvent('dialog');
    const deletionClick = page.locator('form[action$="/delete"] button').click();
    const confirm = await dialogEvent;
    assert.equal(confirm.type(), 'confirm');
    await confirm.dismiss();
    await deletionClick;
    assert.equal(deletes, 0, 'Cancelling deletion still submitted the form');
    assert(page.url().endsWith(`/folders/documents/${fixtures.folder}/edit`));
    await page.goto(base + `/admin/infosystems/${fixtures.infosystem}/edit`);
    await page.locator('#add-field').click();
    await page.locator('#add-field').click();
    assert.equal(await page.locator('[data-field-row]').count(), 2);
    await page.locator('[data-field-row]').first().locator('[data-remove-field]').click();
    assert.equal(await page.locator('[data-field-row]').count(), 1);
    await page.locator('[name="fields[0][name]"]').fill('Поле CSP');
    await page.locator('[name="fields[0][code]"]').fill('csp_field');
    await Promise.all([page.waitForURL(`**/infosystems/${fixtures.infosystem}/edit?saved=1`), page.locator('#infosystem-form button[type="submit"]').click()]);
    assert.equal(await page.locator('[name="fields[0][name]"]').inputValue(), 'Поле CSP');
    // HostCMS-style group browser and tabbed editor.
    await page.goto(base + "/admin/infosystems/" + fixtures.infosystem);
    assert.equal(await page.locator(".content-section").count(), 1);
    assert.equal(await page.locator(".is-browser__list, .admin-empty").count(), 1);
    assert.equal(await page.locator(".is-breadcrumbs").count(), 1);
    await page.getByRole("link", {name: /Новый элемент/}).click();
    assert.equal(await page.locator('[data-tab="main"]').getAttribute("aria-selected"), "true");
    await page.locator('[data-tab="description"]').click();
    assert.equal(await page.locator('[data-tab-panel="description"]').isVisible(), true);
    assert.equal(await page.locator('[data-tab-panel="main"]').isVisible(), false);
    assert.equal(await page.locator('textarea[data-rich-editor="content"]').count(), 1);
    assert.equal(await page.locator('.uv-rich__toolbar').count(), 2);
    await page.locator('[data-rich-editor="content"]').fill('<p>HTML source remains editable</p>');
    await page.locator('[data-tab="seo"]').click();
    assert.equal(await page.locator('textarea[name="meta_description"]').isVisible(), true);
    await page.locator('[data-tab="description"]').click();
    const contentEditor = page.locator('.uv-rich').filter({has: page.locator('textarea[data-rich-editor="content"]')});
    await contentEditor.locator('[data-editor-mode="preview"]').click();
    assert.equal(await contentEditor.locator('iframe[title^="Предпросмотр"]').isVisible(), true);
    assert.equal(await page.locator('textarea[data-rich-editor="content"]').inputValue(), '<p>HTML source remains editable</p>');
    await contentEditor.locator('[data-editor-mode="source"]').click();
    assert.equal(await page.locator('textarea[data-rich-editor="content"]').isVisible(), true);
    const handoffFixture = JSON.parse(require('node:fs').readFileSync('/tmp/cms-page-editor-browser.json', 'utf8'));
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({width, height: 1000});
        for (const [settings, editor, isItem] of [[handoffFixture.page, handoffFixture.editor, false], [handoffFixture.is_page, handoffFixture.is_editor, true]]) {
            await page.goto(base + settings);
            for (const back of ['К странице', 'Отмена']) {
                await page.getByRole('link', {name: 'Редактировать содержимое', exact: true}).focus();
                await Promise.all([page.waitForURL(url => url.pathname === editor), page.keyboard.press('Enter')]);
                assert.equal(await page.getByRole('link', {name: 'К странице', exact: true}).getAttribute('href'), settings);
                if (back === 'К странице') {
                    if (isItem) await page.locator('[data-tab="description"]').click();
                    await page.locator('textarea[name=content]').fill(`<p>Page handoff ${width}px</p>`);
                    await Promise.all([
                        page.waitForURL(url => url.pathname === editor && (url.searchParams.has('saved') || url.searchParams.has('published'))),
                        page.getByRole('button', {name: isItem ? 'Сохранить' : 'Сохранить черновик', exact: true}).click(),
                    ]);
                }
                await page.getByRole('link', {name: back, exact: true}).focus();
                await Promise.all([page.waitForURL(url => url.pathname === settings), page.keyboard.press('Enter')]);
                assert.equal(await page.getByRole('link', {name: 'Редактировать содержимое', exact: true}).isVisible(), true);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Page settings overflow at ${width}px`);
            }
        }
    }
    const propertyFixture = JSON.parse(require('node:fs').readFileSync('/tmp/cms-property-browser.json', 'utf8'));
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({width, height: 1000});
        for (const [code, invalid, corrected] of [['event_date', '2026-02-31', '2024-02-29'], ['amount', '1e309', '-1250.5'], ['price', '100.005', '35000,50']]) {
            await page.goto(base + propertyFixture.edit + '#main');
            await page.locator('[data-tab="properties"]').click();
            const field = page.locator(`[name="properties[${code}]"]`);
            // Bypass native input constraints to verify server validation and its actual 422 page.
            await field.evaluate(input => { input.type = 'text'; input.form.noValidate = true; });
            await field.fill(invalid);
            await Promise.all([
                page.waitForNavigation({waitUntil: 'domcontentloaded'}),
                page.getByRole('button', {name: 'Сохранить', exact: true}).click(),
            ]);
            assert.equal(await page.locator('[data-tab="properties"]').getAttribute('aria-selected'), 'true');
            assert.equal(await field.getAttribute('aria-invalid'), 'true');
            assert.equal(await field.inputValue(), invalid);
            assert.equal(await field.getAttribute('aria-describedby'), `property-error-${code}`);
            assert.equal(await page.locator(`#property-error-${code}[role="alert"]`).isVisible(), true);
            assert.equal(await field.evaluate(input => input === document.activeElement), true);
            await field.fill(corrected);
            if (code === 'price') {
                assert.equal(await field.inputValue(), '35000.50', 'Comma was not normalized during input');
                await field.blur();
                assert.equal(await field.inputValue(), '35 000.50', 'Thousands grouping was not applied');
            }
            await Promise.all([
                page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('saved') === '1'),
                page.getByRole('button', {name: 'Сохранить', exact: true}).click(),
            ]);
            assert.equal(await page.locator('[aria-invalid="true"]').count(), 0);
            if (code === 'price') {
                await page.locator('[data-tab="properties"]').click();
                assert.equal(await field.inputValue(), '35 000.50', 'Saved ruble amount changed');
            }
        }
    }
    // Native disclosure links also work without scripts; denied storage is harmless.
    for (const javaScriptEnabled of [false, true]) {
        const fallback = await browser.newContext({storageState: process.env.MVP_BROWSER_STATE, javaScriptEnabled, viewport: {width: 390, height: 900}});
        if (javaScriptEnabled) await fallback.addInitScript(() => Object.defineProperty(window, 'localStorage', {get() { throw new Error('Storage unavailable'); }}));
        const fallbackPage = await fallback.newPage();
        fallbackPage.on('pageerror', error => errors.push(error.message));
        await fallbackPage.goto(base + '/admin/modules');
        if (javaScriptEnabled) await fallbackPage.locator('.admin-sections > summary').click();
        await fallbackPage.getByRole('navigation', {name: 'Основная навигация'}).getByRole('link', {name: 'Контент', exact: true}).click();
        if (javaScriptEnabled) await fallbackPage.locator('.admin-local > summary').click();
        await fallbackPage.getByRole('navigation', {name: 'Меню раздела'}).getByRole('link', {name: 'Документы', exact: true}).click();
        assert.equal(new URL(fallbackPage.url()).pathname, '/admin/documents');
        assert(await fallbackPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await fallback.close();
    }
    // 1440px at 200% browser zoom has an effective CSS viewport of 720px.
    await page.setViewportSize({width: 720, height: 500});
    await page.goto(base + '/admin/modules');
    assert.equal(await page.locator('.admin-sections > summary').isVisible(), true);
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.goto(base + '/admin/updates');
    await page.screenshot({path: '/tmp/cms-layout-updates.png', fullPage: true});
    assert.equal(errors.length, 0, errors.join('\n'));
    await browser.close();
    console.log('Browser layout OK: 1440/1024/390px, bounded upload controls, pilot assets and mobile menu');
})().catch(error => { console.error(error); process.exit(1); });
