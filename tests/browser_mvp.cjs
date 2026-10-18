const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const base = process.env.MVP_URL || 'http://127.0.0.1:8086';
(async () => {
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const context = await browser.newContext({storageState: process.env.MVP_BROWSER_STATE});
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 1024, 390]) {
        await page.setViewportSize({width, height: 1000});
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
    assert.equal(errors.length, 0, errors.join('\n'));
    await browser.close();
    console.log('Browser layout OK: 1440/1024/390px, bounded upload controls, pilot assets and mobile menu');
})().catch(error => { console.error(error); process.exit(1); });
