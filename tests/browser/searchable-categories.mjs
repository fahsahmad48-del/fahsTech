// Run with: node tests/browser/searchable-categories.mjs
// Uses an installed Chrome (or CHROME_PATH), with no npm dependencies.
import { spawn } from "node:child_process";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { createServer } from "node:http";
import { tmpdir } from "node:os";
import { basename, dirname, join, resolve } from "node:path";

const scripts = await Promise.all(
    ["admin/common.js", "admin/products.js"].map((file) =>
        readFile(new URL(`../../public/js/${file}`, import.meta.url), "utf8"),
    ),
);
const css = await readFile(
    new URL("../../public/css/style.css", import.meta.url),
    "utf8",
);
const searchScript = await readFile(new URL('../../public/js/search.js', import.meta.url), 'utf8');
const options =
    '<option value="">All Categories</option><option value="4">Laptop</option><option value="5">Samsung</option>' +
    Array.from(
        { length: 30 },
        (_, i) => `<option value="${i + 10}">Category ${i}</option>`,
    ).join("");
const select = (id, form = false) =>
    `<label for="${id}">Category</label><select id="${id}" data-searchable-category ${form ? 'name="category_id" required' : ""}>${form ? options.replace('All Categories', 'Select Category') : options}</select>`;
const fields = [
    "_record",
    "filter_category",
    "filter_page",
    "name",
    "brand",
    "price",
    "description",
    "ram",
    "storage",
    "colors",
    "screen",
    "processor",
    "battery",
    "camera",
];
const form = (kind) => {
    const phone = kind === "product";
    return `<dialog id="${kind}Modal" class="modal-overlay"><div class="modal">
        <h3 id="${phone ? "modalTitle" : "deviceModalTitle"}"></h3>
        <button id="${phone ? "closeModal" : "closeDeviceModal"}">Close</button>
        <form id="${kind}Form" action="/admin/products" method="post">
        <div id="${phone ? "methodField" : "deviceMethodField"}"></div>
        ${fields.map((name) => `<input type="hidden" name="${name}">`).join("")}
        <div class="field">${select(phone ? "category_id" : "deviceCategory", true)}</div>
        <label id="${phone ? "imageLabel" : "deviceImageLabel"}">Image</label><input type="file" name="image">
        <p id="${phone ? "imageHint" : "deviceImageHint"}"></p>
        ${phone ? "" : '<img id="deviceImagePreview" hidden><div id="deviceSpecRows"></div><button id="addSpecification" type="button">Add spec</button>'}
        <button id="${phone ? "cancelModal" : "cancelDeviceModal"}" type="button">Cancel</button>
        <button id="${phone ? "submitButton" : "deviceSubmitButton"}" type="submit">Save</button>
        </form></div></dialog>`;
};
const html = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><main class="container">
    <div id="adminMessage" hidden></div><div id="adminProducts" data-filter-url="/admin/products/filter" data-index-url="http://localhost/admin/products" data-phone-store-url="/admin/products" data-device-store-url="/admin/products/devices" data-page="1">
    <div class="field admin-category-filter">${select("adminCategory")}</div><button id="addProductBtn">Add Phone</button><button id="addDeviceBtn">Add Device</button>
    <div id="adminProductsResults"><div id="adminProductRows"><button class="edit-product" data-id="1" data-type="phone" data-category="4" data-update-url="/admin/products/1">Edit Phone</button><button class="edit-product" data-id="2" data-type="laptop" data-category="5" data-update-url="/admin/products/2">Edit Device</button></div></div>
    <div id="adminProductsPagination"></div><p id="adminProductsCount"></p><div id="adminProductsLoading" hidden></div><div id="adminProductsError" hidden><span></span></div><button id="retryProducts">Retry</button></div>
    ${form("product")}${form("device")}
    <template id="deviceSpecTemplate"><div class="device-spec-row"><label data-spec-label="name"></label><input data-spec-input="name"><label data-spec-label="value"></label><input data-spec-input="value"><button class="remove-specification" type="button">Remove</button></div></template>
    <select id="perfumeCategory"><option>Men</option><option>Women</option><option>Unisex</option></select></main>
    <script>document.getElementById('adminProducts').dataset.indexUrl = location.origin + '/admin/products';
    window.requests = []; window.filterFails = false;
    const rows = document.getElementById('adminProductRows').innerHTML;
    window.fetch = async (url, init = {}) => {
        requests.push({url: String(url), category: init.body?.get('category_id')});
        if (init.method === 'POST') return new Response(JSON.stringify({message:'Validation failed',errors:{category_id:['Category is unavailable.']}}), {status:422,headers:{'content-type':'application/json'}});
        return new Response(JSON.stringify({html:rows,pagination:'',page:1,count:2}), {status:filterFails ? 500 : 200,headers:{'content-type':'application/json'}});
    };</script><script>${scripts.join("\n")}</script>`;

const browseHtml = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style>
    <main id="productSearch" data-endpoint="/search/products"><div class="search-bar"><input id="searchInput">${select('categorySelect')}</div>
    <div id="resultsGrid"></div><p id="resultCount"></p><div id="searchLoading"></div><nav id="searchPagination"></nav></main>
    <script>window.requests = []; window.fetch = async url => {
        requests.push(String(url)); return new Response(JSON.stringify({products:{data:[],total:0,current_page:1,last_page:1}}), {headers:{'content-type':'application/json'}});
    };</script><script>${scripts[1]}</script><script>${searchScript}</script>`;
const server = createServer((req, res) => {
    res.setHeader("Content-Type", "text/html");
    res.end(req.url.startsWith('/browse') ? browseHtml : html);
});
await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const profile = await mkdtemp(join(tmpdir(), "ministore-selectors-"));
const chrome = spawn(
    process.env.CHROME_PATH ||
        "C:/Program Files/Google/Chrome/Application/chrome.exe",
    [
        "--headless=new",
        "--no-sandbox",
        "--disable-gpu",
        "--disable-background-timer-throttling",
        "--no-first-run",
        "--no-default-browser-check",
        "--remote-debugging-port=0",
        `--user-data-dir=${profile}`,
        "about:blank",
    ],
    { windowsHide: true, stdio: "ignore" },
);
let socket;
let launchError;
chrome.on("error", (error) => {
    launchError = error;
});
try {
    let port;
    for (let attempt = 0; attempt < 100 && !port; attempt++) {
        if (launchError) throw launchError;
        try {
            port = (
                await readFile(join(profile, "DevToolsActivePort"), "utf8")
            ).split("\n")[0];
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
    }
    if (!port) throw new Error("Chrome did not expose its debugging port.");
    const targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    socket = new WebSocket(
        targets.find((target) => target.type === "page").webSocketDebuggerUrl,
    );
    await new Promise((resolve, reject) => {
        socket.onopen = resolve;
        socket.onerror = reject;
    });
    let sequence = 0;
    const pending = new Map();
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === "Runtime.consoleAPICalled")
            console.log(...message.params.args.map((arg) => arg.value));
        if (message.method === "Page.javascriptDialogOpening")
            console.log("Unexpected dialog:", message.params);
        if (pending.has(message.id)) {
            const { resolve, reject, timer } = pending.get(message.id);
            clearTimeout(timer);
            pending.delete(message.id);
            if (message.error) reject(new Error(message.error.message));
            else resolve(message.result);
        }
    };
    const send = (method, params = {}) =>
        new Promise((resolve, reject) => {
            const id = ++sequence;
            const timer = setTimeout(() => {
                pending.delete(id);
                reject(new Error(`Timeout: ${method}`));
            }, 10000);
            pending.set(id, { resolve, reject, timer });
            socket.send(JSON.stringify({ id, method, params }));
        });
    const evaluate = async (expression) => {
        const result = await send("Runtime.evaluate", {
            expression,
            awaitPromise: true,
            returnByValue: true,
        });
        if (result.exceptionDetails)
            throw new Error(
                result.exceptionDetails.exception?.description ||
                    result.exceptionDetails.text,
            );
        return result.result.value;
    };
    await send("Runtime.enable");
    await send("Page.enable");
    await send("Page.navigate", {
        url: `http://127.0.0.1:${server.address().port}/`,
    });
    console.log("Browser fixture loaded; checking selectors...");
    for (let attempt = 0; attempt < 100; attempt++) {
        if (
            await evaluate(
                'document.querySelectorAll(".searchable-select").length === 3',
            )
        )
            break;
        await new Promise((resolve) => setTimeout(resolve, 50));
    }
    const checks = await evaluate(`(async () => {
        const assert = (value, message) => { if (!value) throw new Error(message); };
        const tick = () => new Promise(resolve => setTimeout(resolve, 50));
        const select = id => document.getElementById(id);
        const trigger = id => select(id + '-trigger');
        const search = id => select(id).closest('.searchable-select').querySelector('input');
        const menu = id => select(id + '-menu');
        const choose = (id, query) => {
            trigger(id).click(); search(id).value = query; search(id).dispatchEvent(new Event('input'));
            search(id).dispatchEvent(new KeyboardEvent('keydown', {key:'Enter', bubbles:true, cancelable:true}));
        };
        assert(document.querySelectorAll('.searchable-select').length === 3, 'three independent instances');
        for (const id of ['productForm', 'deviceForm']) {
            assert(!new FormData(select(id)).has('product_type'), id + ' does not submit product_type');
        }
        assert(!select('perfumeCategory').hidden, 'perfumes retain native select');
        choose('adminCategory', 'LaP'); await tick();
        assert(requests.at(-1).url.includes('category=4'), 'existing AJAX category parameter');
        assert(trigger('adminCategory').textContent === 'Laptop', 'filter label');
        window.filterFails = true; choose('adminCategory', 'samsung'); await tick();
        assert(select('adminCategory').value === '4' && trigger('adminCategory').textContent === 'Laptop', 'failed request restores value and label');
        window.filterFails = false;
        choose('adminCategory', 'All Categories'); await tick();
        assert(select('adminCategory').value === '' && !requests.at(-1).url.includes('category='), 'all categories clears filter');
        history.replaceState(null, '', '?category=5'); window.dispatchEvent(new PopStateEvent('popstate')); await tick();
        assert(trigger('adminCategory').textContent === 'Samsung', 'history updates label');
        select('addProductBtn').click(); choose('category_id', 'lap');
        assert(new FormData(select('productForm')).get('category_id') === '4', 'phone FormData category');
        assert(select('deviceCategory').value === '', 'device selection independent');
        select('productForm').dispatchEvent(new Event('submit', {bubbles:true,cancelable:true})); await tick();
        assert(select('productModal').open && select('category_id').value === '4', '422 retains modal and category');
        assert(trigger('category_id').getAttribute('aria-invalid') === 'true', 'category error marks visible trigger');
        assert(document.getElementById(trigger('category_id').getAttribute('aria-describedby')).textContent.includes('unavailable'), 'category error beside control');
        select('cancelModal').click(); select('addProductBtn').click(); await tick();
        assert(select('category_id').value === '' && trigger('category_id').textContent === 'Select Category', 'add resets previous selection');
        assert(!select('category_id').checkValidity() && select('category_id-required-error').textContent, 'required category has visible validation');
        search('category_id').value = 'lap'; search('category_id').dispatchEvent(new Event('input'));
        search('category_id').dispatchEvent(new KeyboardEvent('keydown', {key:'Enter',bubbles:true,cancelable:true}));
        assert(!select('category_id-required-error'), 'selection clears required error');
        select('cancelModal').click(); document.querySelector('[data-type="phone"]').click(); await tick();
        assert(trigger('category_id').textContent === 'Laptop' && new FormData(select('productForm')).get('category_id') === '4', 'phone edit prefills current category');
        trigger('category_id').click();
        search('category_id').dispatchEvent(new KeyboardEvent('keydown', {key:'Escape',bubbles:true,cancelable:true}));
        assert(menu('category_id').hidden && select('productModal').open, 'Escape closes dropdown and retains modal');
        trigger('category_id').click(); search('category_id').value = 'no matching category'; search('category_id').dispatchEvent(new Event('input'));
        assert(!menu('category_id').querySelector('[role="status"]').hidden, 'no results feedback');
        select('modalTitle').dispatchEvent(new PointerEvent('pointerdown', {bubbles:true}));
        assert(menu('category_id').hidden, 'outside pointer closes dropdown');
        select('cancelModal').click(); document.querySelector('[data-type="laptop"]').click(); await tick();
        assert(trigger('deviceCategory').textContent === 'Samsung', 'device edit prefills category');
        assert(select('deviceModal').open && new FormData(select('deviceForm')).get('_method') === 'PUT', 'legacy device opens edit modal');
        select('cancelDeviceModal').click();
        const deviceButton = document.querySelector('[data-type="laptop"]');
        deviceButton.dataset.type = 'device'; deviceButton.click(); await tick();
        assert(select('deviceModal').open && !new FormData(select('deviceForm')).has('product_type'), 'device edit opens without submitting type');
        choose('deviceCategory', 'lap');
        assert(new FormData(select('deviceForm')).get('category_id') === '4', 'device FormData category');
        trigger('deviceCategory').click();
        const options = select('deviceCategory-options');
        assert(options.scrollHeight > options.clientHeight && getComputedStyle(options).overflowY === 'auto', 'many categories scroll vertically');
        return 'Passed: instances, local search, AJAX, rollback, history, FormData, edit/reset, 422 errors, Escape, outside click, empty results, scrolling, perfumes';
    })()`);
    console.log(checks);
    const point = await evaluate(`(() => {
        const rect = document.querySelector('#deviceCategory-options [data-value="5"]').getBoundingClientRect();
        return {x: rect.x + rect.width / 2, y: rect.y + rect.height / 2};
    })()`);
    await send("Input.dispatchMouseEvent", {type:"mousePressed", button:"left", clickCount:1, ...point});
    await send("Input.dispatchMouseEvent", {type:"mouseReleased", button:"left", clickCount:1, ...point});
    await evaluate(`(() => {
        if (document.getElementById('deviceCategory').value !== '5') throw new Error('Mouse selection failed');
        document.getElementById('deviceCategory-trigger').click();
    })()`);
    console.log("Passed: real mouse selection");
    await send("Emulation.setDeviceMetricsOverride", {
        width: 375,
        height: 667,
        deviceScaleFactor: 1,
        mobile: true,
    });
    await evaluate(`(() => {
        const menu = document.getElementById('deviceCategory-menu').getBoundingClientRect();
        if (menu.left < 0 || menu.right > innerWidth || document.documentElement.scrollWidth > innerWidth) throw new Error('Mobile horizontal overflow');
    })()`);
    console.log("Passed: mobile dropdown fits viewport");
    await send("Emulation.setTouchEmulationEnabled", {enabled:true});
    const touch = await evaluate(`(() => {
        const rect = document.querySelector('#deviceCategory-options [data-value="4"]').getBoundingClientRect();
        return {x: rect.x + rect.width / 2, y: rect.y + rect.height / 2};
    })()`);
    await send("Input.dispatchTouchEvent", {type:"touchStart", touchPoints:[touch]});
    await send("Input.dispatchTouchEvent", {type:"touchEnd", touchPoints:[]});
    await evaluate(`(() => {
        if (document.getElementById('deviceCategory').value !== '4') throw new Error('Touch selection failed');
    })()`);
    console.log("Passed: real touch selection");
    await send("Page.navigate", {url: `http://127.0.0.1:${server.address().port}/browse?category=4`});
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate("Boolean(document.getElementById('categorySelect-trigger') && window.requests?.length)")) break;
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    await evaluate(`(async () => {
        const trigger = document.getElementById('categorySelect-trigger');
        if (trigger.textContent !== 'Laptop' || !requests[0].includes('category=4')) throw new Error('Public URL category initialization failed');
        trigger.click();
        const search = document.querySelector('.searchable-select-search');
        search.value = 'samsung'; search.dispatchEvent(new Event('input'));
        search.dispatchEvent(new KeyboardEvent('keydown', {key:'Enter',bubbles:true,cancelable:true}));
        await new Promise(resolve => setTimeout(resolve, 50));
        if (!requests.at(-1).includes('category=5') || trigger.textContent !== 'Samsung') throw new Error('Public AJAX category selection failed');
    })()`);
    console.log("Passed: Browse Products URL initialization and existing AJAX selection");
} finally {
    socket?.close();
    chrome.kill();
    await new Promise((resolve) => {
        if (chrome.exitCode !== null) resolve();
        else chrome.once("exit", resolve);
    });
    server.close();
    if (
        dirname(resolve(profile)) !== resolve(tmpdir()) ||
        !basename(profile).startsWith("ministore-selectors-")
    )
        throw new Error("Unexpected browser profile path");
    await rm(profile, {
        recursive: true,
        force: true,
        maxRetries: 5,
        retryDelay: 200,
    });
}
