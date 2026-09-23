(() => {
    const root = document.getElementById("perfumeCatalog");
    if (!root) return;
    const select = root.querySelector("[data-category-select]");
    const search = root.querySelector("[data-search-input]");
    const results = root.querySelector("[data-results]");
    const items = root.querySelector("[data-items]");
    const pagination = root.querySelector("[data-pagination]");
    const loading = root.querySelector("[data-loading]");
    const errorBox = root.querySelector("[data-error]");
    let category = select.value, query = search.value.trim();
    let page = Number(root.dataset.page), version = 0, controller, debounce;
    let retry = () => {};

    function syncContext() {
        root.dataset.category = category;
        root.dataset.q = query;
        root.dataset.page = String(page);
        select.value = category;
        search.value = query;
        const form = document.getElementById("perfumeForm");
        if (form) {
            form.elements.filter_category.value = category;
            form.elements.filter_q.value = query;
            form.elements.filter_page.value = page;
        }
    }
    syncContext();

    function cancelPending() {
        clearTimeout(debounce);
        ++version;
        controller?.abort();
    }

    async function loadPerfumes(selected, requestedPage = 1, updateHistory = true, requestedQuery = search.value.trim()) {
        cancelPending();
        controller = new AbortController();
        const current = controller, requestVersion = version;
        loading.hidden = false;
        errorBox.hidden = true;
        results.inert = true;
        results.setAttribute("aria-busy", "true");
        const params = new URLSearchParams({ page: String(requestedPage) });
        if (selected) params.set("category", selected);
        if (requestedQuery) params.set("q", requestedQuery);
        const timeout = setTimeout(() => current.abort(), 15000);
        try {
            const response = await fetch(`${root.dataset.filterUrl}?${params}`, {
                signal: current.signal,
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error(response.status === 401
                ? "Your session expired. Please sign in again."
                : "Unable to load perfumes. Please try again.");
            const data = await response.json();
            if (requestVersion !== version) return;
            window.clearReveal?.(items);
            items.innerHTML = data.html;
            pagination.innerHTML = data.pagination;
            category = selected;
            query = requestedQuery;
            page = data.page;
            root.querySelector("[data-count]").textContent =
                `${data.count} perfume${data.count === 1 ? "" : "s"} found`;
            syncContext();
            window.initReveal?.(items);
            const url = new URL(root.dataset.indexUrl);
            if (query) url.searchParams.set("q", query);
            if (category) url.searchParams.set("category", category);
            if (page > 1) url.searchParams.set("page", page);
            if (updateHistory && url.href !== location.href) history.pushState(null, "", url);
            else history.replaceState(null, "", url);
        } catch (error) {
            if (requestVersion !== version) return;
            errorBox.querySelector("span").textContent = error.name === "AbortError"
                ? "Loading took too long. Please try again." : error.message;
            errorBox.hidden = false;
            retry = () => loadPerfumes(selected, requestedPage, updateHistory, requestedQuery);
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) {
                loading.hidden = true;
                results.inert = false;
                results.setAttribute("aria-busy", "false");
            }
        }
    }
    search.addEventListener("input", () => {
        // Invalidate in-flight responses immediately, including during the debounce.
        cancelPending();
        loading.hidden = false;
        errorBox.hidden = true;
        results.inert = true;
        results.setAttribute("aria-busy", "true");
        debounce = setTimeout(() => loadPerfumes(select.value), 300);
    });
    select.addEventListener("change", () => loadPerfumes(select.value));
    root.querySelector("[data-search-form]").addEventListener("submit", (event) => {
        event.preventDefault();
        loadPerfumes(select.value);
    });
    root.querySelector("[data-retry]").addEventListener("click", () => retry());
    pagination.addEventListener("click", (event) => {
        const link = event.target.closest("a");
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        loadPerfumes(select.value, Number(new URL(link.href).searchParams.get("page")) || 1);
    });
    window.addEventListener("popstate", () => {
        const params = new URLSearchParams(location.search);
        select.value = params.get("category") ?? "";
        search.value = params.get("q") ?? "";
        loadPerfumes(select.value, Number(params.get("page")) || 1, false);
    });
})();
