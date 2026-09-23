import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

// node tests/browser/review-flows.mjs --perfumes
export async function verifyPerfumeSearch({ send, evaluate, until, navigate, root, fill, click, submit }) {
    const output = join(root, 'storage/app/review-screenshots');
    await mkdir(output, { recursive: true });
    for (const path of ['/perfumes']) {
        await navigate(path);
        await evaluate(`window.pageMarker = 'same document'; window.requests = [];
            window.realFetch = window.fetch;
            window.fetch = async (...args) => { requests.push(String(args[0])); return realFetch(...args); };`);
        assert.deepEqual(await evaluate("Array.from(document.querySelector('[data-category-select]').options).map(o=>o.textContent)"),
            ['All Categories', 'Men', 'Women', 'Kids', 'Body Mists', 'Home Sprays']);
        await evaluate(`(async () => {
            const input = document.querySelector('[data-search-input]');
            for (const value of ['R', 'Re', 'Review perfume']) {
                input.value = value; input.dispatchEvent(new Event('input'));
                await new Promise(resolve => setTimeout(resolve, 40));
            }
            if (requests.length) throw new Error('Search was not debounced');
        })()`);
        await until("requests.length === 1 && document.querySelector('[data-results]').getAttribute('aria-busy') === 'false'");
        await click('[data-pagination] a[rel=next]');
        await until("new URLSearchParams(location.search).get('page') === '2'");
        assert.ok(await evaluate("requests.at(-1).includes('q=Review+perfume')"));
        await evaluate("document.querySelector('[data-category-select]').value='women';document.querySelector('[data-category-select]').dispatchEvent(new Event('change'))");
        await until("document.querySelector('[data-count]').textContent === '8 perfumes found'");
        assert.ok(await evaluate("!location.search.includes('page=') && requests.at(-1).includes('q=Review+perfume')"));
        await evaluate('history.back()');
        await until("document.querySelector('[data-category-select]').value === '' && document.querySelector('#perfumeCatalog').dataset.page === '2'");
        assert.equal(await evaluate("document.querySelector('[data-search-input]').value"), 'Review perfume');

        // A slow response that ignores abort must not replace a newer search.
        await evaluate(`window.fetch = async (...args) => {
            requests.push(String(args[0]));
            const response = await realFetch(args[0], {headers: args[1].headers});
            if (String(args[0]).includes('q=Review+perfume+1&') || String(args[0]).endsWith('q=Review+perfume+1'))
                await new Promise(resolve => setTimeout(resolve, 800));
            return response;
        };`);
        await fill('[data-search-input]', 'Review perfume 1');
        await until("requests.at(-1).endsWith('q=Review+perfume+1')");
        await fill('[data-search-input]', 'Review perfume 17');
        await until("document.querySelector('[data-count]').textContent === '1 perfume found'");
        await evaluate('new Promise(resolve => setTimeout(resolve, 900))');
        assert.equal(await evaluate("document.querySelector('[data-count]').textContent"), '1 perfume found');
        assert.ok(await evaluate("document.querySelector('[data-items]').textContent.includes('Review perfume 17')"));

        await evaluate("window.fetch = async () => new Response('', {status:500})");
        await fill('[data-search-input]', 'no match');
        await until("!document.querySelector('[data-error]').hidden");
        await evaluate('window.fetch = window.realFetch');
        await click('[data-retry]');
        await until("document.querySelector('[data-count]').textContent === '0 perfumes found'");
        await fill('[data-search-input]', '');
        await until("document.querySelector('[data-count]').textContent === '17 perfumes found'");
        assert.equal(await evaluate('window.pageMarker'), 'same document');

        for (const width of [1280, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:width<768});
            const stats = await evaluate(`(() => {
                const input = document.querySelector('[data-search-input]').getBoundingClientRect();
                const select = document.querySelector('[data-category-select]').getBoundingClientRect();
                return {width:document.documentElement.scrollWidth, input:{y:input.y,bottom:input.bottom,right:input.right},select:{y:select.y,right:select.right}};
            })()`);
            assert.ok(stats.width <= width && stats.input.right <= width && stats.select.right <= width, `${path}: fits ${width}px`);
            if (width <= 600) assert.ok(stats.select.y >= stats.input.bottom, 'Mobile controls stack');
            else assert.equal(stats.select.y, stats.input.y, 'Desktop controls align');
            if (width === 1280 || width === 390) {
                const shot = await send('Page.captureScreenshot', {format:'png'});
                await writeFile(join(output, `perfume-public-${width}.png`), Buffer.from(shot.data, 'base64'));
            }
        }
        console.log(`Passed: ${path} debounce, combined filters, pagination, history, stale responses, retry, no refresh and four viewport widths`);
    }
    const { verifyAdminPerfumeFilters } = await import('./admin-perfume-filters.mjs');
    await verifyAdminPerfumeFilters({ send, evaluate, until, navigate, fill, click, submit });
}
