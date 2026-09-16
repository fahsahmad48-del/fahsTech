(() => {
    const root = document.getElementById("productSearch");
    const input = document.getElementById("searchInput");
    const category = document.getElementById("categorySelect");
    const grid = document.getElementById("resultsGrid");
    const count = document.getElementById("resultCount");
    const loading = document.getElementById("searchLoading");
    const pagination = document.getElementById("searchPagination");
    let timer,
        controller,
        version = 0,
        page = 1;

    const price = new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
    });
    const textElement = (tag, className, text) => {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = text;
        return element;
    };

    function invalidate() {
        clearTimeout(timer);
        controller?.abort();
        version++;
        loading.hidden = false;
        grid.setAttribute("aria-busy", "true");
        window.clearReveal?.(grid);
        grid.replaceChildren();
        pagination.hidden = true;
        count.textContent = "";
    }

    function render(products) {
        grid.replaceChildren();
        count.textContent = `${products.total} product${products.total === 1 ? "" : "s"} found`;
        if (!products.data.length) {
            grid.append(
                textElement(
                    "div",
                    "empty-state",
                    "No products match your search. Try a different name, brand, or category.",
                ),
            );
        }
        products.data.forEach((product) => {
            const card = document.createElement("a");
            card.className = "card reveal";
            card.href = `${root.dataset.productUrl}/${encodeURIComponent(product.slug)}`;
            const imageBox = textElement("div", "card-image", "");
            const fallback = () =>
                imageBox.replaceChildren(
                    textElement("span", "product-placeholder", "📦"),
                );
            if (product.image_path) {
                const image = document.createElement("img");
                image.src = `${root.dataset.imageUrl}/${product.image_path.split("/").map(encodeURIComponent).join("/")}`;
                image.alt = product.name;
                image.loading = "lazy";
                image.decoding = "async";
                image.addEventListener("error", fallback, { once: true });
                imageBox.append(image);
            } else fallback();
            const body = textElement("div", "card-body", "");
            body.append(
                textElement("div", "card-brand", product.brand),
                textElement("div", "card-name", product.name),
                textElement(
                    "div",
                    "card-price",
                    price.format(Number(product.price)),
                ),
            );
            card.append(imageBox, body);
            grid.append(card);
        });
        window.initReveal?.(grid);

        pagination.replaceChildren();
        pagination.hidden = products.last_page <= 1;
        const pageButton = (label, target, disabled) => {
            const button = textElement("button", "btn btn-outline", label);
            button.type = "button";
            button.disabled = disabled;
            button.addEventListener("click", () => {
                page = target;
                loadProducts().then(() => {
                    count.tabIndex = -1;
                    count.focus({ preventScroll: true });
                    count.scrollIntoView({ block: "start" });
                });
            });
            return button;
        };
        pagination.append(
            pageButton("Previous", page - 1, page <= 1),
            textElement("span", "", `Page ${page} of ${products.last_page}`),
            pageButton("Next", page + 1, page >= products.last_page),
        );
    }

    async function loadProducts() {
        invalidate();
        const requestVersion = version;
        const requestController = new AbortController();
        controller = requestController;
        const params = new URLSearchParams();
        if (input.value.trim()) params.set("q", input.value.trim());
        if (category.value) params.set("category", category.value);
        if (page > 1) params.set("page", String(page));
        const url = new URL(window.location.href);
        url.search = params.toString();
        history.replaceState(null, "", url);
        const timeout = setTimeout(() => requestController.abort(), 15000);
        try {
            const response = await fetch(`${root.dataset.endpoint}?${params}`, {
                signal: requestController.signal,
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error("Unable to load products.");
            const data = await response.json();
            if (requestVersion !== version) return;
            if (page > data.products.last_page) {
                page = data.products.last_page;
                return loadProducts();
            }
            render(data.products);
        } catch (error) {
            if (requestVersion !== version) return;
            const message = textElement(
                "div",
                "empty-state",
                "We couldn’t load the products. Please try again.",
            );
            message.setAttribute("role", "alert");
            const retry = textElement("button", "btn btn-outline", "Try again");
            retry.type = "button";
            retry.addEventListener("click", loadProducts);
            message.append(document.createElement("br"), retry);
            grid.replaceChildren(message);
        } finally {
            clearTimeout(timeout);
            if (requestVersion === version) {
                loading.hidden = true;
                grid.setAttribute("aria-busy", "false");
            }
        }
    }

    function readUrl() {
        const params = new URLSearchParams(location.search);
        input.value = (params.get("q") ?? "").slice(0, 120);
        category.value = params.get("category") ?? "";
        if (category.selectedIndex < 0) category.value = "";
        category.dispatchEvent(new Event("searchable:sync"));
        const requestedPage = Number(params.get("page"));
        page =
            Number.isInteger(requestedPage) &&
            requestedPage > 0 &&
            requestedPage <= 100000
                ? requestedPage
                : 1;
    }
    input.addEventListener("input", () => {
        page = 1;
        invalidate(); // Invalidate immediately, including the debounce interval.
        timer = setTimeout(loadProducts, 300);
    });
    category.addEventListener("change", () => {
        page = 1;
        loadProducts();
    });
    window.addEventListener("popstate", () => {
        readUrl();
        loadProducts();
    });
    readUrl();
    loadProducts();
})();
