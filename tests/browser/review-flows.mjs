// node tests/browser/review-flows.mjs (Chrome and PHP required; no npm packages)
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { request as httpRequest } from 'node:http';
import { dirname, basename, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const parent = join(root, 'storage/framework/testing');
await mkdir(parent, { recursive: true });
const directory = await mkdtemp(join(parent, 'browser-review-'));
const reserve = createServer();
await new Promise(resolve => reserve.listen(0, '127.0.0.1', resolve));
const port = reserve.address().port;
await new Promise(resolve => reserve.close(resolve));
const origin = `http://127.0.0.1:${port}`;
const env = { ...process.env, MINISTORE_REVIEW_DIR: directory, MINISTORE_REVIEW_PORT: String(port) };
const fixture = join(root, 'tests/browser/review-fixture.php');
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
// Consume HTTP responses fully; PHP's development server closes each connection.
const fetch = (url, options = {}) => new Promise((resolve, reject) => {
    const request = httpRequest(url, options, response => {
        const chunks = [];
        response.on('data', chunk => chunks.push(chunk));
        response.on('end', () => resolve({
            status: response.statusCode, ok: response.statusCode >= 200 && response.statusCode < 300,
            headers: { get: name => response.headers[name] },
            text: async () => Buffer.concat(chunks).toString(),
            json: async () => JSON.parse(Buffer.concat(chunks).toString()),
        }));
        response.on('error', reject);
    });
    request.on('error', reject);
    request.setTimeout(10000, () => request.destroy(new Error('HTTP timeout')));
    request.end(options.body);
});
const processes = [];
function start(command, args, extra = {}) {
    const child = spawn(command, args, { cwd: root, env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'], ...extra });
    processes.push(child);
    let output = '';
    child.stdout?.on('data', data => output += data);
    child.stderr?.on('data', data => output += data);
    child.output = () => output;
    return child;
}
let socket;
try {
    const seed = start('php', [fixture, '--seed']);
    const code = await new Promise((resolve, reject) => { seed.on('exit', resolve); seed.on('error', reject); });
    assert.equal(code, 0, seed.output());
    console.log(seed.output().trim());
    const server = start('php', ['-S', `127.0.0.1:${port}`, '-t', join(root, 'public'), fixture]);
    for (let i = 0; i < 100; i++) {
        try { if ((await fetch(origin)).ok) break; } catch {}
        if (i === 99) throw new Error('Local PHP server did not start: ' + server.output());
        await wait(100);
    }
    start(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--no-sandbox', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--remote-debugging-port=0', `--user-data-dir=${join(directory, 'chrome')}`, 'about:blank',
    ]);
    let chromePort;
    for (let i = 0; i < 100; i++) {
        try { chromePort = (await readFile(join(directory, 'chrome/DevToolsActivePort'), 'utf8')).split('\n')[0]; break; } catch {}
        await wait(100);
    }
    assert.ok(chromePort, 'Chrome debugging port');
    const targets = await (await fetch(`http://127.0.0.1:${chromePort}/json`)).json();
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = event => reject(new Error(event.message || 'Chrome WebSocket connection failed')); });
    let sequence = 0;
    const pending = new Map();
    const errors = [];
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
        if (pending.has(message.id)) {
            const { resolve, reject, timer } = pending.get(message.id);
            clearTimeout(timer); pending.delete(message.id);
            if (message.error) reject(new Error(message.error.message)); else resolve(message.result);
        }
    };
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout: ${method}`)); }, 15000);
        pending.set(id, { resolve, reject, timer }); socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    };
    const until = async (expression, label = expression) => {
        for (let i = 0; i < 150; i++) {
            if (await evaluate(expression)) return;
            await wait(100);
        }
        throw new Error(`Timed out: ${label}`);
    };
    const navigate = async path => {
        await send('Page.navigate', { url: origin + path });
        await until(`location.pathname === ${JSON.stringify(path.split('?')[0])} && document.readyState === 'complete'`);
    };
    const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill = (selector, value) => evaluate(`(() => { const input = document.querySelector(${JSON.stringify(selector)}); input.value = ${JSON.stringify(value)}; input.dispatchEvent(new Event('input', {bubbles:true})); })()`);
    const upload = async selector => {
        const { root: document } = await send('DOM.getDocument');
        const { nodeId } = await send('DOM.querySelector', { nodeId: document.nodeId, selector });
        await send('DOM.setFileInputFiles', { nodeId, files: [join(directory, 'upload.png')] });
    };
    const submit = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).requestSubmit()`);
    const layout = async label => {
        for (const width of [1280, 768, 390, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
            await wait(400);
            if (!await evaluate('document.documentElement.scrollWidth <= innerWidth + 1')) {
                console.log(await evaluate("Array.from(document.querySelectorAll('body *')).filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right})).slice(0,12)"));
                const shot = await send('Page.captureScreenshot');
                await writeFile(join(parent, 'review-overflow.png'), Buffer.from(shot.data, 'base64'));
                assert.fail(`${label}: horizontal overflow at ${width}px`);
            }
        }
    };
    await send('Runtime.enable'); await send('Page.enable');
    await navigate('/'); await layout('Home');
    await navigate('/search');
    await until("document.getElementById('resultCount').textContent === '17 products found'");
    await click('#searchPagination button:last-child');
    await until("document.getElementById('searchPagination').textContent.includes('Page 2 of 3')");
    await fill('#searchInput', 'nothing matches');
    await until("document.getElementById('resultCount').textContent === '0 products found'");
    assert.ok(await evaluate("document.getElementById('resultsGrid').textContent.includes('No products match')"));
    await layout('Empty search');
    await send('Network.enable');
    await send('Network.emulateNetworkConditions', {offline:true, latency:0, downloadThroughput:0, uploadThroughput:0});
    await fill('#searchInput', 'Review');
    await until("document.querySelector('#resultsGrid [role=alert]') && document.getElementById('searchLoading').hidden");
    await send('Network.emulateNetworkConditions', {offline:false, latency:0, downloadThroughput:-1, uploadThroughput:-1});
    await click('#resultsGrid button');
    await until("document.getElementById('resultCount').textContent === '17 products found'");
    await fill('#searchInput', 'Review phone 17');
    await until("document.getElementById('resultCount').textContent === '1 product found'");
    await click('#resultsGrid a'); await until("location.pathname === '/products/review-phone-17' && document.readyState === 'complete'");
    await layout('Product');
    await navigate('/perfumes'); await layout('Perfumes');
    await click('[data-pagination] a[rel=next]');
    await until("document.querySelector('[data-pagination]').textContent.includes('Page 2 of 2')");
    await click('[data-category=women]');
    await until("document.querySelector('[data-count]').textContent.includes('8 perfumes')");
    await click('[data-items] a'); await until("location.pathname.startsWith('/perfumes/review-') && document.readyState === 'complete'");
    await layout('Perfume detail');
    console.log('Passed: public search, pagination, empty results, product/perfume pages, category filters and four viewport widths');

    await send('Page.navigate', {url: origin + '/admin/products'});
    await until("location.pathname === '/admin/login' && document.readyState === 'complete'");
    await layout('Login');
    await fill('#password', 'wrong-password'); await submit('.admin-login form');
    await until("document.querySelector('.admin-login .note')?.textContent.includes('Incorrect password')");
    await fill('#password', 'review-password'); await submit('.admin-login form');
    await until("location.pathname === '/admin/products' && document.getElementById('addProductBtn') && document.readyState === 'complete'");
    await layout('Admin products');
    // CSRF must remain enabled in the actual HTTP fixture.
    const { cookies } = await send('Network.getCookies', {urls:[origin]});
    assert.equal((await fetch(origin + '/admin/categories', {method:'POST',headers:{Accept:'application/json',Cookie:cookies.map(c=>`${c.name}=${c.value}`).join('; ')}})).status, 419);
    await click('#addProductBtn'); await layout('Phone modal');
    await fill('#name', 'Browser phone'); await fill('#brand', 'Browser'); await fill('#price', '75.50');
    await evaluate("document.getElementById('category_id').value='1'; document.getElementById('category_id').dispatchEvent(new Event('change',{bubbles:true}))");
    await upload('#image'); await submit('#productForm');
    await until("!document.getElementById('productModal').open && document.getElementById('adminProductRows').textContent.includes('Browser phone')");
    await until("!window.MiniStoreAdmin.actionPending");
    await click('#adminProductRows .edit-product'); await fill('#name', 'Browser phone edited');
    await submit('#productForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('productModal').open");
    assert.ok(await evaluate("document.getElementById('adminProductRows').textContent.includes('Browser phone edited')"));
    const imageUrl = await evaluate("document.querySelector('#adminProductRows img').src");
    const imageResponse = await fetch(imageUrl);
    assert.equal(imageResponse.status, 200); assert.equal(imageResponse.headers.get('content-type'), 'image/webp');
    await submit('#adminProductRows form'); await until("document.querySelector('.category-delete-warning')?.open");
    await click('.category-delete-warning [value=delete]');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('adminProductRows').textContent.includes('Browser phone edited')");
    assert.equal((await fetch(imageUrl)).status, 404);
    console.log('Passed: admin protection, wrong/correct login, real CSRF rejection, phone create/edit/delete and stored WebP cleanup');

    await click('#addDeviceBtn'); await layout('Device modal');
    await fill('#deviceType', 'laptop'); await fill('#deviceName', 'Browser laptop');
    await fill('#deviceBrand', 'Browser'); await fill('#devicePrice', '200');
    await evaluate("document.getElementById('deviceCategory').value='1'; document.getElementById('deviceCategory').dispatchEvent(new Event('change',{bubbles:true}))");
    await fill('#deviceSpecRows [data-spec-input=name]', 'Processor');
    await upload('#deviceImage'); await submit('#deviceForm');
    await until("document.querySelector('#deviceSpecRows [aria-invalid=true]') && !window.MiniStoreAdmin.actionPending");
    assert.ok(await evaluate("document.getElementById('deviceModal').open"));
    await fill('#deviceSpecRows [data-spec-input=value]', 'Test chip'); await submit('#deviceForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('deviceModal').open");
    assert.ok(await evaluate("document.getElementById('adminProductRows').textContent.includes('Browser laptop')"));
    await click('#adminProductRows .edit-product');
    assert.equal(await evaluate("document.querySelector('#deviceSpecRows [data-spec-input=value]').value"), 'Test chip');
    await fill('#deviceName', 'Browser laptop edited'); await submit('#deviceForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('deviceModal').open");
    await submit('#adminProductRows form'); await until("document.querySelector('.category-delete-warning[open] [value=delete]')");
    await click('.category-delete-warning[open] [value=delete]');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('adminProductRows').textContent.includes('Browser laptop')");

    await navigate('/admin/perfumes'); await layout('Admin perfumes');
    await click('#addPerfume'); await layout('Perfume modal');
    await fill('#perfumeName', 'Browser perfume'); await fill('#perfumePrice', '25');
    await upload('#perfumeImage'); await submit('#perfumeForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('perfumeModal').open");
    assert.ok(await evaluate("document.querySelector('[data-items]').textContent.includes('Browser perfume')"));
    await click('.edit-perfume'); await fill('#perfumeName', 'Browser perfume edited'); await submit('#perfumeForm');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('perfumeModal').open");
    await submit('[data-items] form'); await until("document.querySelector('.category-delete-warning[open] [value=delete]')");
    await click('.category-delete-warning[open] [value=delete]');
    await until("!window.MiniStoreAdmin.actionPending && !document.querySelector('[data-items]').textContent.includes('Browser perfume')");
    console.log('Passed: device specification errors/retry, device and perfume create/edit/delete, and modal layouts');

    await navigate('/admin/categories'); await layout('Categories');
    await fill('#categoryName', 'Phones'); await submit('#addCategoryForm');
    await until("!document.getElementById('categoryNameError').hidden");
    await until('!window.MiniStoreAdmin.actionPending');
    await fill('#categoryName', 'Browser category'); await submit('#addCategoryForm');
    await until("!window.MiniStoreAdmin.actionPending && document.getElementById('categoryRows').textContent.includes('Browser category')");
    await evaluate("Array.from(document.querySelectorAll('.edit-category')).find(b=>b.dataset.name==='Browser category').click()");
    await fill('#editCategoryName', 'Renamed browser category'); await submit('#editCategoryForm');
    await until("!window.MiniStoreAdmin.actionPending && document.getElementById('categoryRows').textContent.includes('Renamed browser category')");
    await evaluate("Array.from(document.querySelectorAll('.chip')).find(c=>c.textContent.includes('Renamed browser category')).querySelector('form').requestSubmit()");
    await until("document.querySelector('.category-delete-warning[open] [value=delete]')");
    await click('.category-delete-warning[open] [value=delete]');
    await until("!window.MiniStoreAdmin.actionPending && !document.getElementById('categoryRows').textContent.includes('Renamed browser category')");
    console.log('Passed: category duplicate feedback and create/rename/delete');

    await navigate('/admin/settings'); await layout('Settings');
    await fill('#store_name', 'Browser updated store'); await submit('#settingsForm');
    await until("!window.MiniStoreAdmin.actionPending && document.getElementById('adminMessage').textContent.includes('Settings updated')");
    assert.ok((await (await fetch(origin)).text()).includes('Browser updated store'));
    await fill('#current_password', 'review-password'); await fill('#new_password', '  changed password  '); await fill('#new_password_confirmation', '  changed password  ');
    await submit('#passwordForm');
    await until("!window.MiniStoreAdmin.actionPending && document.getElementById('adminMessage').textContent.includes('Password changed')");
    await fill('#store_name', 'Saved after password change'); await submit('#settingsForm');
    await until("!window.MiniStoreAdmin.actionPending && document.getElementById('adminMessage').textContent.includes('Settings updated')");
    await submit('.admin-bar form'); await until("location.pathname === '/admin/login' && document.readyState === 'complete'");
    await fill('#password', '  changed password  '); await submit('.admin-login form');
    await until("location.pathname === '/admin/products' && document.readyState === 'complete'");
    console.log('Passed: settings cache invalidation, password whitespace, CSRF rotation, subsequent save, logout and login');
    assert.deepEqual(errors, [], 'Uncaught browser JavaScript errors');
    console.log('Passed: no uncaught browser JavaScript errors');
} finally {
    socket?.close();
    for (const child of processes.reverse()) {
        if (child.exitCode === null && child.signalCode === null) {
            child.kill();
            await new Promise(resolve => child.once('exit', resolve));
        }
    }
    if (dirname(resolve(directory)) !== resolve(parent) || !basename(directory).startsWith('browser-review-')) throw new Error('Unexpected cleanup path');
    await rm(directory, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
}
