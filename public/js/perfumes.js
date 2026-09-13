(() => {
    const root = document.getElementById("perfumeCatalog");
    if (!root) return;
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

    async function load(selected, requestedPage = 1, updateHistory = true) {
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
            retry = () => load(selected, requestedPage, updateHistory);
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) {
                loading.hidden = true;
                results.inert = false;
                results.setAttribute("aria-busy", "false");
            }
        }
    }
    select?.addEventListener("change", () => load(select.value));
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
            load(link.dataset.category);
        } else if (pagination.contains(link)) {
            event.preventDefault();
            load(
                category,
                Number(new URL(link.href).searchParams.get("page")) || 1,
            );
        }
    });
    window.addEventListener("popstate", () => {
        const params = new URLSearchParams(location.search);
        load(
            params.get("category") ?? "",
            Number(params.get("page")) || 1,
            false,
        );
    });
})();
