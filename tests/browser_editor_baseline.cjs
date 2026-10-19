// G0 observation harness: existing disposable MVP fixture, never a live site.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {performance} = require('node:perf_hooks');
const base = process.env.MVP_URL || 'http://127.0.0.1:8086';
const output = '/tmp/cms-editor-baseline';
const report = {
    schema: 1,
    commit: process.env.GITHUB_SHA || null,
    environment: {node: process.version, platform: process.platform, browser: null},
    protocol: {
        fixture: 'Existing disposable MVP fixture: 26 Browser list documents; NOT Content 1k/10k',
        widths: [1440, 1024, 390], height: 1000, concurrency: 1,
        cache: 'Uncontrolled application cache; fresh browser context per width; no cold/warm claim',
        timing: 'Automation wall time and browser navigation timing only; not human time or server profiling',
        counts: 'Explicit click/fill/select calls; scroll and keyboard events are not counted as clicks',
        limitations: ['Superadmin only', 'No SQL count/peak PHP memory', 'No statistically valid performance percentiles', 'No human usability validation'],
    },
    flows: [], observations: [], errors: [],
};
let browser;
(async () => {
    assert.equal(process.env.MVP_EDITOR_BASELINE_DISPOSABLE, '1', 'Explicit disposable-fixture opt-in required');
    const origin = new URL(base);
    assert.equal(origin.hostname, '127.0.0.1', 'Observation harness is limited to the loopback MVP fixture');
    assert.equal(origin.protocol, 'http:');
    fs.mkdirSync(output, {recursive: true});
    browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    report.environment.browser = browser.version();
    for (const width of report.protocol.widths) {
        const context = await browser.newContext({
            storageState: process.env.MVP_BROWSER_STATE,
            viewport: {width, height: report.protocol.height},
        });
        const page = await context.newPage();
        page.on('pageerror', error => report.errors.push({width, message: error.message}));
        let current;
        const start = id => {
            current = {id, width, clicks: 0, fills: 0, selects: 0, navigations: [], started: performance.now()};
            report.flows.push(current);
        };
        const click = async locator => { current.clicks++; await locator.click(); };
        const fill = async (locator, value) => { current.fills++; await locator.fill(value); };
        const select = async (locator, value) => { current.selects++; await locator.selectOption(value); };
        const navigation = async () => {
            const timing = await page.evaluate(() => {
                const entry = performance.getEntriesByType('navigation')[0];
                return entry ? {
                    path: location.pathname + location.search,
                    ttfbMs: entry.responseStart - entry.requestStart,
                    domContentLoadedMs: entry.domContentLoadedEventEnd,
                    loadMs: entry.loadEventEnd,
                } : null;
            });
            if (timing) current.navigations.push(timing);
        };
        const finish = () => {
            current.automationElapsedMs = performance.now() - current.started;
            delete current.started;
        };
        const screenshot = name => page.screenshot({path: path.join(output, `${name}-${width}.png`), fullPage: true});

        start('find-document-and-return');
        // Starting URL is setup, not an editor action; all subsequent transitions use UI.
        await page.goto(base + '/admin/documents?folder=all');
        await fill(page.getByLabel('Название или код'), 'Browser list');
        await select(page.getByLabel('Публикация', {exact: true}), 'unpublished');
        await Promise.all([
            page.waitForURL(url => url.searchParams.get('q') === 'Browser list'),
            click(page.getByRole('button', {name: 'Применить', exact: true})),
        ]);
        await navigation();
        const listContext = url => Object.fromEntries(['folder', 'q', 'status', 'sort', 'per_page', 'page'].map(key => [
            key, url.searchParams.get(key) ?? ({page: '1', sort: 'name', per_page: '25'}[key] || ''),
        ]));
        const originalList = listContext(new URL(page.url()));
        const documentLink = page.locator('tbody tr').first().locator('a').first();
        const documentUrl = await documentLink.getAttribute('href');
        assert.match(new URL(documentUrl, base).pathname, /^\/admin\/documents\/\d+\/edit$/);
        await screenshot('documents-filtered');
        await Promise.all([page.waitForURL(url => /^\/admin\/documents\/\d+\/edit$/.test(url.pathname)), click(documentLink)]);
        await navigation();
        await Promise.all([
            page.waitForURL(url => url.pathname === '/admin/documents'),
            click(page.getByRole('link', {name: 'К документам', exact: true})),
        ]);
        current.returnContextPreserved = JSON.stringify(listContext(new URL(page.url()))) === JSON.stringify(originalList);
        await navigation();
        finish();

        start('save-document-draft');
        await page.goto(base + documentUrl);
        await fill(page.locator('textarea[name=content]'), `<p>G0 disposable UI observation ${width}</p>`);
        await screenshot('document-editor');
        await Promise.all([
            page.waitForURL(url => url.pathname.endsWith('/edit') && url.searchParams.get('saved') === '1'),
            click(page.getByRole('button', {name: 'Сохранить черновик', exact: true})),
        ]);
        assert.equal(await page.locator('textarea[name=content]').inputValue(), `<p>G0 disposable UI observation ${width}</p>`);
        await navigation();
        finish();

        start('format-document-source');
        await page.goto(base + documentUrl);
        const sourceEditor = page.locator('textarea[name=content]');
        await fill(sourceEditor, '<section><div><p>Тест</p></div></section>');
        const formatButton = page.getByRole('button', {name: 'Форматировать код без сохранения'});
        await click(formatButton);
        assert.match(await sourceEditor.inputValue(), /<section>\n\s+<div>\n\s+<p>Тест<\/p>/);
        finish();
        start('create-document-page');
        await page.goto(base + '/admin/structure');
        await Promise.all([
            page.waitForURL('**/structure/create'),
            click(page.getByRole('link', {name: 'Новый узел', exact: true})),
        ]);
        await fill(page.locator('input[name=name]'), `G0 UX ${width}`);
        const root = await page.locator('select[name=parent_id] option').evaluateAll(options =>
            options.find(option => option.value !== '' && /^\/\s+—/.test(option.textContent.trim()))?.value);
        assert(root, 'Disposable fixture root missing');
        await select(page.locator('select[name=parent_id]'), root);
        await fill(page.locator('input[name=slug]'), `g0-ux-${width}`);
        await select(page.locator('select[name=page_type]'), 'documents.page');
        await select(page.locator('select[name=status]'), 'draft');
        await Promise.all([
            page.waitForURL(/\/admin\/structure\/\d+\/edit\?saved=1$/),
            click(page.getByRole('button', {name: 'Сохранить', exact: true})),
        ]);
        await navigation();
        current.contentEditorLinks = await page.locator('a[href^="/admin/documents/"]').count();
        current.outcome = 'Structure saved; content editing not completed by this flow';
        await screenshot('page-created');
        finish();

        // Observe accessible-name risks without asserting that known defects are fixed.
        await page.goto(base + '/admin');
        await page.evaluate(() => document.body.classList.add('admin-shell--collapsed'));
        report.observations.push(await page.evaluate(width => {
            const candidates = [...document.querySelectorAll('.admin-nav__item, [data-admin-collapse], [data-command-open]')];
            return {
                width,
                note: 'DOM name heuristic; not a full accessibility-tree/WCAG audit',
                controlsWithoutVisibleTextOrAriaLabel: candidates.filter(element =>
                    !element.innerText.trim() && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby')
                ).map(element => ({tag: element.tagName, href: element.getAttribute('href')})),
            };
        }, width));
        await context.close();
    }
    assert.equal(report.errors.length, 0, JSON.stringify(report.errors));
    report.status = 'completed';
})().catch(error => {
    report.status = 'failed';
    report.errors.push({message: error.message});
    process.exitCode = 1;
}).finally(async () => {
    fs.mkdirSync(output, {recursive: true});
    fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2) + '\n');
    if (browser) await browser.close();
    console.log(`Editor baseline ${report.status}: ${report.flows.length} flow observations; ${output}/report.json`);
});
