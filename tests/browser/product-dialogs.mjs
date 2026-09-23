import assert from 'node:assert/strict';

export async function verifyProductDialogs({ evaluate, until, navigate, fill, click, submit, upload }) {
    await navigate('/admin/login');
    await fill('#password', 'review-password');
    await submit('.admin-login form');
    await until("location.pathname === '/admin/products' && document.readyState === 'complete'");
    await evaluate("window.pageMarker = 'products document'");
    for (const [kind, button, modal, form, cancel] of [
        ['phone', 'addProductBtn', 'productModal', 'productForm', 'cancelModal'],
        ['device', 'addDeviceBtn', 'deviceModal', 'deviceForm', 'cancelDeviceModal'],
    ]) {
        await click(`#${button}`);
        assert.ok(await evaluate(`document.getElementById('${modal}').open`), `${kind} opens`);
        assert.equal(await evaluate(`new FormData(document.getElementById('${form}')).has('product_type')`), false);
        await click(`#${cancel}`);
        await click(`#${button}`);
        const prefix = kind === 'phone' ? '' : 'device';
        for (const [field, value] of [['name', `Dialog ${kind}`], ['brand', 'Review'], ['price', '42']]) {
            await fill(`#${prefix ? prefix + field[0].toUpperCase() + field.slice(1) : field}`, value);
        }
        await evaluate(`(() => { const select = document.querySelector('#${form} [name=category_id]'); select.value = '1'; select.dispatchEvent(new Event('change', {bubbles:true})); })()`);
        await upload(kind === 'phone' ? '#image' : '#deviceImage');
        await submit(`#${form}`);
        await until(`!window.MiniStoreAdmin.actionPending && !document.getElementById('${modal}').open && document.getElementById('adminProductRows').textContent.includes('Dialog ${kind}')`);
        assert.equal(await evaluate(`Array.from(document.querySelectorAll('.edit-product')).find(b => b.dataset.name === 'Dialog ${kind}').dataset.type`), kind);
        await evaluate(`Array.from(document.querySelectorAll('.edit-product')).find(b => b.dataset.name === 'Dialog ${kind}').click()`);
        assert.ok(await evaluate(`document.getElementById('${modal}').open`), `${kind} uses correct edit dialog`);
        assert.equal(await evaluate(`new FormData(document.getElementById('${form}')).has('product_type')`), false);
        await fill(kind === 'phone' ? '#name' : '#deviceName', `Edited ${kind}`);
        await submit(`#${form}`);
        await until(`!window.MiniStoreAdmin.actionPending && !document.getElementById('${modal}').open && document.getElementById('adminProductRows').textContent.includes('Edited ${kind}')`);
    }
    assert.equal(await evaluate('window.pageMarker'), 'products document');
    console.log('Passed: phone/device open, close, create with automatic type, correct edit dialog, save and no product_type form field');
}
