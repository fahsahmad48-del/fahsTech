(() => {
    const root = document.getElementById("perfumeCatalog");
    if (!root) return;
    const admin = window.MiniStoreAdmin;
    if (!admin) return;
    const { clearErrors, interceptForms } = admin;

    const select = root.querySelector("[data-category-select]");
    const results = root.querySelector("[data-results]");
    const items = root.querySelector("[data-items]");
    const pagination = root.querySelector("[data-pagination]");
    const loading = root.querySelector("[data-loading]");
    const errorBox = root.querySelector("[data-error]");
    let category =
        select?.value ??
        root.querySelector("[data-category][aria-current]")?.dataset.category ??
        "";
    let page = Number(root.dataset.page),
        version = 0,
        controller;
    let retry = () => {};

    function syncContext() {
        root.dataset.category = category;
        root.dataset.page = String(page);
        if (select) select.value = category;
        root.querySelectorAll("[data-category]").forEach((link) => {
            const active = link.dataset.category === category;
            link.classList.toggle("btn-primary", active);
            link.classList.toggle("btn-outline", !active);
            if (active) link.setAttribute("aria-current", "true");
            else link.removeAttribute("aria-current");
        });
        const form = document.getElementById("perfumeForm");
        if (form) {
            form.elements.filter_category.value = category;
            form.elements.filter_page.value = page;
        }
    }
    syncContext();

    async function loadPerfumes(
        selected,
        requestedPage = 1,
        updateHistory = true,
    ) {
        controller?.abort();
        controller = new AbortController();
        const current = controller,
            requestVersion = ++version;
        loading.hidden = false;
        errorBox.hidden = true;
        results.inert = true;
        results.setAttribute("aria-busy", "true");
        const params = new URLSearchParams({ page: String(requestedPage) });
        if (selected) params.set("category", selected);
        const timeout = setTimeout(() => current.abort(), 15000);
        try {
            const response = await fetch(
                `${root.dataset.filterUrl}?${params}`,
                {
                    signal: current.signal,
                    headers: { Accept: "application/json" },
                },
            );
            if (!response.ok)
                throw new Error(
                    response.status === 401
                        ? "Your session expired. Please sign in again."
                        : "Unable to load perfumes. Please try again.",
                );
            const data = await response.json();
            if (requestVersion !== version) return;
            window.clearReveal?.(items);
            items.innerHTML = data.html;
            pagination.innerHTML = data.pagination;
            category = selected;
            page = data.page;
            root.querySelector("[data-count]").textContent =
                `${data.count} perfume${data.count === 1 ? "" : "s"} found`;
            syncContext();
            window.initReveal?.(items);
            const url = new URL(root.dataset.indexUrl);
            if (category) url.searchParams.set("category", category);
            if (page > 1) url.searchParams.set("page", page);
            if (updateHistory) history.pushState(null, "", url);
            else history.replaceState(null, "", url);
        } catch (error) {
            if (requestVersion !== version) return;
            syncContext();
            errorBox.querySelector("span").textContent =
                error.name === "AbortError"
                    ? "Loading took too long. Please try again."
                    : error.message;
            errorBox.hidden = false;
            retry = () => loadPerfumes(selected, requestedPage, updateHistory);
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) {
                loading.hidden = true;
                results.inert = false;
                results.setAttribute("aria-busy", "false");
            }
        }
    }
    select?.addEventListener("change", () => loadPerfumes(select.value));
    root.querySelector("[data-retry]").addEventListener("click", () => retry());
    root.addEventListener("click", (event) => {
        const link = event.target.closest("a");
        if (
            !link ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        if (link.hasAttribute("data-category")) {
            event.preventDefault();
            loadPerfumes(link.dataset.category);
        } else if (pagination.contains(link)) {
            event.preventDefault();
            loadPerfumes(
                category,
                Number(new URL(link.href).searchParams.get("page")) || 1,
            );
        }
    });
    window.addEventListener("popstate", () => {
        const params = new URLSearchParams(location.search);
        loadPerfumes(
            params.get("category") ?? "",
            Number(params.get("page")) || 1,
            false,
        );
    });

    const modal = document.getElementById("perfumeModal");
    if (!modal) return;
    const form = document.getElementById("perfumeForm");
    const specs = document.getElementById("perfumeSpecRows");
    const addSpec = document.getElementById("addPerfumeSpecification");
    let specIndex = 0;

    function addSpecification(name = "", value = "") {
        if (specs.children.length >= 30) return;
        const row = document
            .getElementById("perfumeSpecTemplate")
            .content.cloneNode(true);
        for (const [field, text] of Object.entries({ name, value })) {
            const input = row.querySelector(`[data-spec-input="${field}"]`);
            input.name = `specs[${specIndex}][${field}]`;
            input.id = `perfume-spec-${specIndex}-${field}`;
            input.value =
                typeof text === "string" || typeof text === "number"
                    ? text
                    : "";
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
        data = {},
        updateUrl = null,
        specRows = [],
        restoreErrors = false,
    ) {
        if (admin.actionPending) return;
        if (!restoreErrors) clearErrors(form);
        form.reset();
        const errors = document.getElementById("perfumeValidationErrors");
        if (errors) errors.hidden = !restoreErrors;
        form.action = updateUrl ?? modal.dataset.storeUrl;
        form.elements._record.value = data.record ?? "";
        form.elements.filter_category.value = root.dataset.category ?? "";
        form.elements.filter_page.value = root.dataset.page;
        const method = document.getElementById("perfumeMethod");
        method.replaceChildren();
        if (updateUrl) {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = "_method";
            input.value = "PUT";
            method.append(input);
        }
        for (const field of [
            "name",
            "brand",
            "category",
            "price",
            "description",
        ]) {
            const value =
                data[field] ??
                (field === "category" ? root.dataset.category || "men" : "");
            form.elements.namedItem(field).value =
                typeof value === "string" || typeof value === "number"
                    ? value
                    : "";
        }
        document.getElementById("perfumeModalTitle").textContent = updateUrl
            ? "Edit Perfume"
            : "Add Perfume";
        document.getElementById("perfumeSubmit").textContent = updateUrl
            ? "Save Changes"
            : "Add Perfume";
        form.elements.image.required = !updateUrl;
        document.getElementById("perfumeImageLabel").textContent = updateUrl
            ? "Image"
            : "Image *";
        document.getElementById("perfumeImageHint").textContent = updateUrl
            ? "Leave empty to keep the current image."
            : "JPEG, PNG or WebP, up to 8 MB and 4096 × 4096 pixels.";
        const preview = document.getElementById("perfumeImagePreview");
        preview.hidden = !data.image;
        if (data.image) preview.src = data.image;
        else preview.removeAttribute("src");
        specs.replaceChildren();
        specIndex = 0;
        addSpec.disabled = false;
        specRows.forEach((row) => addSpecification(row.name, row.value));
        if (!specRows.length) addSpecification();
        modal.showModal();
    }
    document
        .getElementById("addPerfume")
        .addEventListener("click", () => openForm());
    root.addEventListener("click", (event) => {
        const button = event.target.closest(".edit-perfume");
        if (!button) return;
        const data = button.dataset;
        const specRows = Object.entries(JSON.parse(data.specs || "{}")).map(
            ([name, value]) => ({ name, value }),
        );
        openForm(data, data.updateUrl, specRows);
    });
    modal
        .querySelectorAll("[data-close-perfume]")
        .forEach((button) =>
            button.addEventListener("click", () => modal.close()),
        );
    modal.addEventListener("click", (event) => {
        if (event.target === modal && !admin.actionPending) modal.close();
    });

    interceptForms(
        "#perfumeForm, #perfumeCatalog [data-items] form",
        async () => {
            await loadPerfumes(select.value, page, false);
        },
    );

    const previous = JSON.parse(modal.dataset.oldInput || "{}");
    if (previous._form === "perfume") {
        const editing = /^\d+$/.test(previous._record ?? "");
        const specRows =
            previous.specs && typeof previous.specs === "object"
                ? Object.values(previous.specs)
                : [];
        openForm(
            {
                ...previous,
                record: previous._record,
                image: modal.dataset.oldImage,
            },
            editing ? `${modal.dataset.storeUrl}/${previous._record}` : null,
            specRows.filter((row) => row && typeof row === "object"),
            true,
        );
    }
})();
