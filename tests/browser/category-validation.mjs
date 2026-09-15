// Run with: node tests/browser/category-validation.mjs
// Renders the actual Blade page using an in-memory database; Chrome mocks AJAX responses.
import { spawn, execFileSync } from "node:child_process";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { createServer } from "node:http";
import { tmpdir } from "node:os";
import { basename, dirname, join, resolve } from "node:path";

const php = `<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'cache.default' => 'array', 'session.driver' => 'array']);
Illuminate\\Support\\Facades\\Artisan::call('migrate', ['--force' => true]);
App\\Models\\Category::create(['name' => 'Samsung']);
App\\Models\\Category::create(['name' => 'Phones']);
$request = app('request');
$request->setLaravelSession(app('session')->driver());
echo view('admin.categories.index', ['categories' => App\\Models\\Category::withCount('products')->get(), 'errors' => new Illuminate\\Support\\ViewErrorBag])->render();
`;
const markup = execFileSync("php", { input: php, encoding: "utf8" });
const mock = `<script>
window.requests = [];
window.nextResult = 'duplicate';
window.documentToken = Math.random();
window.pageErrors = [];
window.addEventListener('error', event => pageErrors.push(event.message));
window.fetch = async (url, init = {}) => {
    requests.push({url: String(url), method: init.method, name: init.body?.get('name')});
    if (!init.method) return new Response(JSON.stringify({html: '<span>Refreshed categories</span>'}), {headers: {'content-type': 'application/json'}});
    if (nextResult === 'network') throw new Error('Connection lost');
    if (nextResult === 'pending') await new Promise(resolve => window.finishRequest = resolve);
    const body = nextResult === 'success' ? {message: 'Category added successfully.'}
        : nextResult === 'delete' ? {message: 'This category contains products and cannot be deleted.', errors: {category: ['This category contains products and cannot be deleted.']}}
        : {message: 'A category with this name already exists.', errors: {name: ['A category with this name already exists.', 'Second name error must not appear.']}};
    return new Response(JSON.stringify(body), {status: nextResult === 'success' ? 201 : 422, headers: {'content-type': 'application/json'}});
};
</script>`;
const html = markup.replace('</head>', mock + '</head>');
const assets = new Map(await Promise.all([
    ['css/style.css', 'text/css'], ['js/store.js', 'text/javascript'],
    ['js/admin/common.js', 'text/javascript'], ['js/admin/categories.js', 'text/javascript'],
].map(async ([path, type]) => ['/' + path, {type, body: await readFile(resolve('public', path))}])));
const server = createServer((req, res) => {
    const asset = assets.get(req.url);
    res.setHeader('Content-Type', asset?.type || 'text/html');
    res.end(asset?.body || html.replaceAll('http://localhost', `http://127.0.0.1:${server.address().port}`));
});
await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
const profile = await mkdtemp(join(tmpdir(), "ministore-category-validation-"));
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
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate('Boolean(window.MiniStoreAdmin && document.getElementById("categoryNameError"))')) break;
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    for (const width of [1280, 390, 320]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: false});
        console.log(await evaluate(`(async () => {
            const assert = (condition, message) => { if (!condition) throw new Error(message); };
            const tick = () => new Promise(resolve => setTimeout(resolve, 30));
            const form = document.getElementById('addCategoryForm');
            const input = document.getElementById('categoryName');
            const error = document.getElementById('categoryNameError');
            const button = form.querySelector('button');
            const token = documentToken;
            const originalWidth = input.getBoundingClientRect().width;
            input.defaultValue = 'Samsung'; input.value = 'Samsung'; nextResult = 'duplicate';
            form.requestSubmit(); await tick();
            assert(!error.hidden && error.textContent === 'A category with this name already exists.', 'first name error under input');
            assert(document.getElementById('adminMessage').hidden, 'no global field error');
            assert(!form.querySelector('[data-validation-error], .note'), 'no duplicate summary');
            assert(input.value === 'Samsung' && input.classList.contains('input-error') && input.getAttribute('aria-invalid') === 'true', 'text and error styling retained');
            assert(input.getAttribute('aria-describedby') === error.id, 'accessible error association');
            assert(Math.abs(originalWidth - input.getBoundingClientRect().width) < 1, 'input width stays stable');
            assert(error.getBoundingClientRect().top >= input.getBoundingClientRect().bottom, 'error below input');
            if (innerWidth <= 480) assert(button.getBoundingClientRect().top >= error.getBoundingClientRect().bottom, 'mobile button below field error');
            else assert(Math.abs(button.getBoundingClientRect().top - input.getBoundingClientRect().top) < 1, 'desktop input/button aligned');
            assert(document.documentElement.scrollWidth <= innerWidth, 'no horizontal overflow');
            nextResult = 'pending'; input.value = 'New category'; form.requestSubmit(); await tick();
            assert(error.hidden && !error.textContent && !input.classList.contains('input-error') && !input.hasAttribute('aria-invalid'), 'old error cleared before response');
            nextResult = 'success'; finishRequest(); await tick();
            assert(input.value === '' && error.hidden, 'successful add clears input and error');
            assert(document.getElementById('categoryRows').textContent.includes('Refreshed categories'), 'list refreshed');
            assert(document.getElementById('adminMessage').textContent === 'Category added successfully.', 'single success area');
            assert(document.querySelectorAll('#adminMessage').length === 1, 'one global message area');
            assert(documentToken === token && !pageErrors.length, 'no navigation or script errors');
            return 'Passed: duplicate/add/retry/success and layout at ' + innerWidth + 'px';
        })()`));
    }
    console.log(await evaluate(`(async () => {
        const assert = (condition, message) => { if (!condition) throw new Error(message); };
        const tick = () => new Promise(resolve => setTimeout(resolve, 30));
        const rows = document.getElementById('categoryRows');
        rows.innerHTML = '<button class="edit-category" data-id="1" data-name="Phones">Edit</button><form method="POST" action="/admin/categories/1"><input type="hidden" name="_method" value="DELETE"><button type="submit">Delete</button></form>';
        rows.querySelector('.edit-category').click();
        const form = document.getElementById('editCategoryForm');
        const input = document.getElementById('editCategoryName');
        const error = document.getElementById('editCategoryNameError');
        input.value = 'Samsung'; nextResult = 'duplicate'; form.requestSubmit(); await tick();
        assert(!error.hidden && error.textContent === 'A category with this name already exists.', 'rename field error');
        assert(document.getElementById('adminMessage').hidden && !form.querySelector('.note'), 'no rename duplicate alerts');
        assert(document.getElementById('categoryModal').open && input.value === 'Samsung', 'rename stays open with entered name');
        document.getElementById('cancelCategoryModal').click(); rows.querySelector('.edit-category').click();
        assert(error.hidden && !input.classList.contains('input-error') && input.value === 'Phones', 'reopening edit clears old error');
        nextResult = 'network'; form.requestSubmit(); await tick();
        assert(!document.getElementById('adminMessage').hidden && error.hidden && !form.querySelector('.note'), 'network failure only in global area');
        document.getElementById('cancelCategoryModal').click();
        nextResult = 'delete'; rows.querySelector('form').requestSubmit(); await tick();
        assert(document.getElementById('adminMessage').textContent.includes('cannot be deleted'), 'delete error uses global area');
        assert(!rows.querySelector('.note, [data-validation-error]'), 'no duplicate delete error');
        rows.querySelector('.edit-category').click(); nextResult = 'success'; form.requestSubmit(); await tick();
        assert(!document.getElementById('categoryModal').open && error.hidden, 'rename success closes modal and clears error');
        assert(!pageErrors.length, 'no script errors');
        return 'Passed: rename, reopen, network failure, blocked deletion and rename recovery';
    })()`));
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
        !basename(profile).startsWith("ministore-category-validation-")
    )
        throw new Error("Unexpected browser profile path");
    await rm(profile, {
        recursive: true,
        force: true,
        maxRetries: 5,
        retryDelay: 200,
    });
}
