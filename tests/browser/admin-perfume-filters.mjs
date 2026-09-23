import assert from 'node:assert/strict';

export async function verifyAdminPerfumeFilters({ send, evaluate, until, navigate, fill, click, submit }) {
    await send('Emulation.setDeviceMetricsOverride', {width:1280, height:900, deviceScaleFactor:1, mobile:false});
    await navigate('/admin/login');
    await fill('#password', 'review-password');
    await submit('.admin-login form');
    await until("location.pathname === '/admin/products' && document.readyState === 'complete'");
    await navigate('/admin/perfumes?q=ignored');
    assert.equal(await evaluate("document.querySelector('[data-search-input], [name=q], [name=filter_q]') !== null"), false);
    assert.deepEqual(await evaluate("Array.from(document.querySelector('[data-category-select]').options).map(o => o.textContent)"),
        ['All Categories', 'Men', 'Women', 'Kids', 'Body Mists', 'Home Sprays']);
    await evaluate(`window.pageMarker = 'admin perfumes document'; window.requests = []; window.realFetch = window.fetch;
        window.fetch = (...args) => { requests.push(String(args[0])); return realFetch(...args); };`);
    const choose = async category => {
        await evaluate(`document.querySelector('[data-category-select]').value = ${JSON.stringify(category)}; document.querySelector('[data-category-select]').dispatchEvent(new Event('change'))`);
        await until(`document.querySelector('[data-results]').getAttribute('aria-busy') === 'false' && document.getElementById('perfumeCatalog').dataset.category === ${JSON.stringify(category)}`);
    };
    for (const [category, count] of [['',17], ['men',9], ['women',8], ['kids',0], ['body-mists',0], ['home-sprays',0]]) {
        await choose(category);
        assert.equal(await evaluate("document.querySelector('[data-count]').textContent"), `${count} perfumes found`);
        assert.equal(await evaluate("new URLSearchParams(location.search).has('q')"), false);
        assert.equal(await evaluate("document.querySelector('[data-pagination]').innerHTML.includes('q=')"), false);
    }
    await choose('');
    await click('[data-pagination] a[rel=next]');
    await until("new URLSearchParams(location.search).get('page') === '2' && document.querySelector('[data-results]').getAttribute('aria-busy') === 'false'");
    await choose('women');
    assert.equal(await evaluate("new URLSearchParams(location.search).has('page')"), false);
    await evaluate('history.back()');
    await until("document.querySelector('[data-category-select]').value === '' && document.getElementById('perfumeCatalog').dataset.page === '2'");
    await choose('women');
    await click('.edit-perfume');
    assert.equal(await evaluate("new FormData(document.getElementById('perfumeForm')).has('filter_q')"), false);
    await fill('#perfumeName', 'Renamed admin scent');
    await submit('#perfumeForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('perfumeModal').open");
    assert.ok(await evaluate("document.querySelector('[data-items]').textContent.includes('Renamed admin scent')"));
    assert.equal(await evaluate("new URLSearchParams(location.search).get('category')"), 'women');
    await evaluate("window.fetch = async () => new Response('', {status:500}); document.querySelector('[data-category-select]').value = 'kids'; document.querySelector('[data-category-select]').dispatchEvent(new Event('change'))");
    await until("!document.querySelector('[data-error]').hidden");
    await evaluate('window.fetch = (...args) => { requests.push(String(args[0])); return realFetch(...args); }');
    await click('[data-retry]');
    await until("document.querySelector('[data-count]').textContent === '0 perfumes found' && document.querySelector('[data-results]').getAttribute('aria-busy') === 'false'");
    assert.ok(await evaluate("requests.filter(url => url.includes('/filter')).every(url => !new URL(url).searchParams.has('q'))"));
    assert.equal(await evaluate('window.pageMarker'), 'admin perfumes document');
    console.log('Passed: admin category-only UI, all six options via AJAX, pagination, history, edit context, retry and no q or full refresh');
}
