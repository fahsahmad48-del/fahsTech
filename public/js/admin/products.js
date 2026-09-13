(() => {
    const root = document.getElementById("adminProducts");
    if (!root) return;
    const admin = window.MiniStoreAdmin;
    if (!admin) return;
    const { clearErrors, interceptForms } = admin;

    const category = document.getElementById("adminCategory");
    const results = document.getElementById("adminProductsResults");
    const rows = document.getElementById("adminProductRows");
    const pagination = document.getElementById("adminProductsPagination");
    const count = document.getElementById("adminProductsCount");
    const loading = document.getElementById("adminProductsLoading");
    const errorBox = document.getElementById("adminProductsError");
    let appliedCategory = category.value,
        page = Number(root.dataset.page),
        version = 0,
        controller;
    let retry = () => {};

    function syncContext() {
        for (const form of document.querySelectorAll(
            "#productForm, #deviceForm",
        )) {
            form.elements.filter_category.value = appliedCategory;
            form.elements.filter_page.value = page;
        }
    }

    async function loadProducts(
        selectedCategory,
        requestedPage = 1,
        updateHistory = true,
    ) {
        controller?.abort();
        controller = new AbortController();
        const requestController = controller;
        const requestVersion = ++version;
        loading.hidden = false;
        errorBox.hidden = true;
        results.inert = true;
        results.setAttribute("aria-busy", "true");
        const params = new URLSearchParams({ page: String(requestedPage) });
        if (selectedCategory) params.set("category", selectedCategory);
        const timeout = setTimeout(() => requestController.abort(), 15000);
        try {
            const response = await fetch(
                `${root.dataset.filterUrl}?${params}`,
                {
                    signal: requestController.signal,
                    headers: { Accept: "application/json" },
                },
            );
            if (!response.ok)
                throw new Error(
                    response.status === 401
                        ? "Your session expired. Please sign in again."
                        : "Unable to load products. Please try again.",
                );
            const data = await response.json();
            if (requestVersion !== version) return;
            rows.innerHTML = data.html;
            pagination.innerHTML = data.pagination;
            page = data.page;
            appliedCategory = selectedCategory;
            category.value = selectedCategory;
            count.textContent = `${data.count} product${data.count === 1 ? "" : "s"} found`;
            syncContext();
            const url = new URL(root.dataset.indexUrl);
            if (selectedCategory)
                url.searchParams.set("category", selectedCategory);
            if (page > 1) url.searchParams.set("page", String(page));
            if (updateHistory) history.pushState(null, "", url);
            else history.replaceState(null, "", url);
        } catch (error) {
            if (requestVersion !== version) return;
            category.value = appliedCategory;
            errorBox.querySelector("span").textContent =
                error.name === "AbortError"
                    ? "Loading took too long. Please try again."
                    : error.message;
            errorBox.hidden = false;
            retry = () =>
                loadProducts(selectedCategory, requestedPage, updateHistory);
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) {
                loading.hidden = true;
                results.inert = false;
                results.setAttribute("aria-busy", "false");
            }
        }
    }

    category.addEventListener("change", () => loadProducts(category.value));
    document
        .getElementById("retryProducts")
        .addEventListener("click", () => retry());
    pagination.addEventListener("click", (event) => {
        const link = event.target.closest("a");
        if (
            !link ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        event.preventDefault();
        const next = Number(new URL(link.href).searchParams.get("page")) || 1;
        loadProducts(appliedCategory, next);
    });
    window.addEventListener("popstate", () => {
        const params = new URLSearchParams(location.search);
        const selected = params.get("category") ?? "";
        category.value = selected;
        loadProducts(selected, Number(params.get("page")) || 1, false);
    });

    const phoneFields = [
        "name",
        "brand",
        "category_id",
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
    const deviceFields = [
        "product_type",
        "name",
        "brand",
        "category_id",
        "price",
        "description",
        "colors",
    ];
    const forms = {
        product: {
            modal: "productModal",
            form: "productForm",
            method: "methodField",
            title: "modalTitle",
            submit: "submitButton",
            hint: "imageHint",
            close: "closeModal",
            cancel: "cancelModal",
            fields: phoneFields,
            label: "Phone",
            store: root.dataset.phoneStoreUrl,
        },
        device: {
            modal: "deviceModal",
            form: "deviceForm",
            method: "deviceMethodField",
            title: "deviceModalTitle",
            submit: "deviceSubmitButton",
            hint: "deviceImageHint",
            close: "closeDeviceModal",
            cancel: "cancelDeviceModal",
            fields: deviceFields,
            label: "Device",
            store: root.dataset.deviceStoreUrl,
        },
    };
    const specs = document.getElementById("deviceSpecRows");
    const addSpec = document.getElementById("addSpecification");
    let specIndex = 0;

    function addSpecification(name = "", value = "") {
        if (specs.children.length >= 30) return;
        const row = document
            .getElementById("deviceSpecTemplate")
            .content.cloneNode(true);
        for (const [field, text] of Object.entries({ name, value })) {
            const input = row.querySelector(`[data-spec-input="${field}"]`);
            input.name = `specs[${specIndex}][${field}]`;
            input.id = `device-spec-${specIndex}-${field}`;
            input.value = text ?? "";
            row.querySelector(`[data-spec-label="${field}"]`).htmlFor =
                input.id;
        }
        specs.append(row);
        specIndex++;
        addSpec.disabled = specs.children.length >= 30;
    }
    addSpec.addEventListener("click", () => {
        addSpecification();
        specs.lastElementChild?.querySelector("input").focus();
    });
    specs.addEventListener("click", (event) => {
        const remove = event.target.closest(".remove-specification");
        if (!remove) return;
        remove.closest(".device-spec-row").remove();
        addSpec.disabled = false;
        addSpec.focus();
    });

    function openForm(
        kind,
        data = {},
        updateUrl = null,
        specRows = [],
        restoreErrors = false,
    ) {
        if (admin.actionPending) return;
        const config = forms[kind];
        const form = document.getElementById(config.form);
        if (!restoreErrors) clearErrors(form);
        form.reset();
        form.elements._record.value = data.id ?? "";
        form.action = updateUrl ?? config.store;
        const method = document.getElementById(config.method);
        method.replaceChildren();
        if (updateUrl) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "_method";
            input.value = "PUT";
            method.append(input);
        }
        for (const field of config.fields)
            form.elements.namedItem(field).value = data[field] ?? "";
        document.getElementById(config.title).textContent =
            `${updateUrl ? "Edit" : "Add"} ${config.label}`;
        document.getElementById(config.submit).textContent = updateUrl
            ? "Save Changes"
            : `Add ${config.label}`;
        form.elements.image.required = !updateUrl;
        document.getElementById(config.hint).textContent = updateUrl
            ? "Leave empty to keep the current image."
            : "JPEG, PNG or WebP, up to 8 MB and 4096 × 4096 pixels.";
        if (kind === "device") {
            document.getElementById("deviceImageLabel").textContent = updateUrl
                ? "Image"
                : "Image *";
            const preview = document.getElementById("deviceImagePreview");
            preview.hidden = !data.image;
            if (data.image) preview.src = data.image;
            else preview.removeAttribute("src");
            specs.replaceChildren();
            specIndex = 0;
            addSpec.disabled = false;
            specRows.forEach((row) => addSpecification(row.name, row.value));
            if (!specRows.length) addSpecification();
        }
        syncContext();
        document.getElementById(config.modal).showModal();
    }

    document
        .getElementById("addProductBtn")
        .addEventListener("click", () => openForm("product"));
    document
        .getElementById("addDeviceBtn")
        .addEventListener("click", () => openForm("device"));
    rows.addEventListener("click", (event) => {
        const button = event.target.closest(".edit-product");
        if (!button) return;
        const data = {
            ...button.dataset,
            category_id: button.dataset.category,
            product_type: button.dataset.type,
        };
        const kind = data.product_type === "phone" ? "product" : "device";
        const specRows = Object.entries(JSON.parse(data.specs || "{}")).map(
            ([name, value]) => ({ name, value }),
        );
        openForm(kind, data, data.updateUrl, specRows);
    });
    for (const config of Object.values(forms)) {
        const modal = document.getElementById(config.modal);
        for (const id of [config.close, config.cancel])
            document
                .getElementById(id)
                .addEventListener("click", () => modal.close());
        modal.addEventListener("click", (event) => {
            if (event.target === modal && !admin.actionPending) modal.close();
        });
    }

    interceptForms(
        "#productForm, #deviceForm, #adminProductRows form",
        async () => {
            await loadProducts(category.value, page, false);
        },
    );

    const previous = JSON.parse(root.dataset.oldInput || "{}");
    if (Object.hasOwn(forms, previous._form)) {
        const editing = /^\d+$/.test(previous._record ?? "");
        const updateUrl = editing
            ? `${root.dataset.phoneStoreUrl}/${previous._record}`
            : null;
        const specRows =
            previous.specs && typeof previous.specs === "object"
                ? Object.values(previous.specs)
                : [];
        openForm(
            previous._form,
            { ...previous, id: previous._record },
            updateUrl,
            specRows.filter((row) => row && typeof row === "object"),
            true,
        );
    }
})();
