import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

// Run through: node tests/browser/review-flows.mjs --cards
export async function verifyCards({ send, evaluate, until, navigate, root }) {
    const output = join(root, 'storage/app/review-screenshots');
    await mkdir(output, { recursive: true });
    await send('Emulation.setEmulatedMedia', {features:[{name:'prefers-reduced-motion', value:'reduce'}]});
    const measurements = [];
    for (const width of [360, 390, 768, 1280]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height:960, deviceScaleFactor:1, mobile:width<768});
        for (const page of ['home', 'search']) {
            await navigate(page === 'home' ? '/' : '/search');
            const selector = page === 'home' ? '#latest .grid' : '#resultsGrid';
            await until(`document.querySelectorAll('${selector} .card').length === 8`);
            await evaluate(`Promise.all(Array.from(document.querySelectorAll('${selector} img')).map(img=>{img.loading='eager';return img.decode()}))`);
            if (page === 'home') await evaluate("scrollTo({top:document.getElementById('latest').getBoundingClientRect().top+scrollY-document.querySelector('.site-header').getBoundingClientRect().height-16,behavior:'instant'})");
            else await evaluate("scrollTo({top:0,behavior:'instant'})");
            const stats = await evaluate(`(() => {
                const grid = document.querySelector('${selector}');
                const rect = element => {const r = element.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom,right:r.right}};
                return {
                    viewport:innerWidth, pageWidth:document.documentElement.scrollWidth,
                    columns:getComputedStyle(grid).gridTemplateColumns.split(' ').length,
                    cards:Array.from(grid.querySelectorAll('.card')).map(card=>({
                        card:rect(card), area:rect(card.querySelector('.card-image')),
                        name:rect(card.querySelector('.card-name')), brand:rect(card.querySelector('.card-brand')), price:rect(card.querySelector('.card-price')),
                        nameLineHeight:parseFloat(getComputedStyle(card.querySelector('.card-name')).lineHeight),
                        clamp:getComputedStyle(card.querySelector('.card-name')).webkitLineClamp,
                        fit:getComputedStyle(card.querySelector('img')).objectFit,
                        natural:[card.querySelector('img').naturalWidth,card.querySelector('img').naturalHeight],
                    })),
                    filter: ${page === 'search'} ? (()=>{
                        const input = document.getElementById('searchInput'), select = document.getElementById('categorySelect');
                        const style = el => {const s=getComputedStyle(el);return [s.height,s.border,s.borderRadius,s.fontFamily,s.fontSize]};
                        return {input:rect(input),select:rect(select),inputStyle:style(input),selectStyle:style(select),native:!select.hidden && !select.closest('.searchable-select'),inputs:document.querySelectorAll('.search-bar input').length};
                    })() : null,
                };
            })()`);
            assert.ok(stats.pageWidth <= width, `${page} at ${width}: no page overflow`);
            assert.equal(stats.columns, width < 768 ? 2 : width === 768 ? 3 : 4);
            const first = stats.cards[0];
            for (const card of stats.cards) {
                assert.ok(Math.abs(card.area.width-card.area.height)<1, 'Square image area');
                assert.ok(Math.abs(card.area.height-first.area.height)<1, 'Same image area across all image dimensions');
                assert.ok(Math.abs(card.card.height-first.card.height)<1, 'Equal card heights');
                assert.equal(card.fit, 'contain');
                assert.equal(card.clamp, '2');
                assert.ok(Math.abs(card.name.height-card.nameLineHeight*2)<1, 'Two lines reserved for every name');
                assert.ok(card.card.x>=0 && card.card.right<=width, 'Cards fit without hidden horizontal overflow');
                const sameRow = stats.cards.filter(other=>Math.abs(other.card.y-card.card.y)<1);
                for (const other of sameRow) {
                    assert.ok(Math.abs(card.brand.y-other.brand.y)<1, 'Brands aligned');
                    assert.ok(Math.abs(card.name.y-other.name.y)<1, 'Names aligned');
                    assert.ok(Math.abs(card.price.bottom-other.price.bottom)<1, 'Prices aligned');
                }
            }
            assert.deepEqual(new Set(stats.cards.map(c=>Math.sign(c.natural[0]-c.natural[1]))), new Set([-1,0,1]), 'Portrait, landscape and square images loaded');
            if (stats.filter) {
                assert.ok(stats.filter.native, 'Native select is visible');
                assert.equal(stats.filter.inputs, 1, 'No extra category search input');
                assert.deepEqual(stats.filter.inputStyle, stats.filter.selectStyle, 'Input and select matching styles');
                assert.ok(stats.filter.select.right<=width);
                if (width<768) assert.ok(stats.filter.select.y>=stats.filter.input.bottom+9, 'Fields stack with a gap');
                else assert.equal(stats.filter.select.y, stats.filter.input.y);
            }
            const shot = await send('Page.captureScreenshot', {format:'png'});
            await writeFile(join(output, `${page}-${width}.png`), Buffer.from(shot.data,'base64'));
            measurements.push({page,width,columns:stats.columns,imageSize:first.area.width,cardHeight:first.card.height});
            console.log(`Passed: ${page} at ${width}px, square image areas, aligned text/prices, no overflow${stats.filter ? ', styled native filter' : ''}`);
        }
    }
    for (const width of [360,390,768,1280]) {
        const [home,search] = measurements.filter(row=>row.width===width);
        assert.equal(home.cardHeight,search.cardHeight, 'Home and search share card dimensions');
    }
    await navigate('/search?category=3');
    await until("document.getElementById('categorySelect').value==='3' && document.getElementById('resultCount').textContent==='6 products found'");
    await evaluate("document.getElementById('categorySelect').value='2';document.getElementById('categorySelect').dispatchEvent(new Event('change'))");
    await until("document.getElementById('resultCount').textContent==='0 products found' && document.getElementById('resultsGrid').textContent.includes('No products match')");
    await evaluate("document.getElementById('categorySelect').value='';document.getElementById('categorySelect').dispatchEvent(new Event('change'))");
    await until("document.getElementById('resultCount').textContent==='17 products found'");
    await evaluate("document.querySelector('#searchPagination button:last-child').click()");
    await until("document.getElementById('searchPagination').textContent.includes('Page 2 of 3')");
    await evaluate("const field=document.getElementById('searchInput');field.value='Watch';field.dispatchEvent(new Event('input'))");
    await until("document.getElementById('resultCount').textContent==='4 products found'");
    assert.ok(await evaluate("!location.search.includes('page=') && document.getElementById('searchLoading').hidden"));
    console.log('Passed: native category URL initialization/change, empty results, clearing category, pagination and text search');
    await writeFile(join(output,'measurements.json'),JSON.stringify(measurements,null,2));
    console.log(`Screenshots: ${output}`);
}
