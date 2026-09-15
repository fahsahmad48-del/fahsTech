(() => {
    const root = document.getElementById("categoryRows");
    if (!root) return;
    const admin = window.MiniStoreAdmin;
    if (!admin) return;
    const { showMessage, clearErrors, interceptForms } = admin;

    function clearCategoryError(form) {
        const error = form.querySelector(".field-error");
        if (!error) return;
        error.textContent = "";
        error.hidden = true;
        const input = form.elements.namedItem("name");
        input.classList.remove("input-error");
        input.removeAttribute("aria-invalid");
    }

    function showCategoryError(form, errors, message, status) {
        const error = form.querySelector(".field-error");
        const nameError = errors?.name?.[0];
        if (status === 422 && error && nameError) {
            error.textContent = nameError;
            error.hidden = false;
            const input = form.elements.namedItem("name");
            input.classList.add("input-error");
            input.setAttribute("aria-invalid", "true");
            input.focus();
            return;
        }
        showMessage(message, "error");
    }

    async function loadCategories() {
        try {
            const response = await fetch(root.dataset.indexUrl, {
                headers: { Accept: "application/json" },
            });
            if (!response.ok) throw new Error();
            const data = await response.json();
            document.getElementById("categoryRows").innerHTML = data.html;
            document.getElementById("retryCategories").hidden = true;
        } catch {
            showMessage(
                "Saved, but the category list could not be refreshed. Try loading the list again.",
                "error",
            );
            document.getElementById("retryCategories").hidden = false;
        }
    }
    document
        .getElementById("retryCategories")
        .addEventListener("click", loadCategories);
    interceptForms(
        "#addCategoryForm, #editCategoryForm, #categoryRows form",
        async (form) => {
            if (form.id === "addCategoryForm") form.elements.name.value = "";
            await loadCategories();
        },
        { beforeSubmit: clearCategoryError, onError: showCategoryError },
    );

    const modal = document.getElementById("categoryModal");
    const form = document.getElementById("editCategoryForm");
    const nameInput = document.getElementById("editCategoryName");
    const deleteWarning = document.getElementById("categoryDeleteWarning");

    deleteWarning?.addEventListener("click", (event) => {
        const bounds = deleteWarning.getBoundingClientRect();
        if (
            event.target === deleteWarning &&
            (event.clientX < bounds.left ||
                event.clientX > bounds.right ||
                event.clientY < bounds.top ||
                event.clientY > bounds.bottom)
        ) {
            deleteWarning.close();
        }
    });

    function closeModal() {
        if (!admin.actionPending) modal.close();
    }

    document
        .getElementById("categoryRows")
        .addEventListener("click", (event) => {
            const button = event.target.closest(".edit-category");
            if (!button || admin.actionPending) return;
            clearErrors(form);
            clearCategoryError(form);
            nameInput.value = button.dataset.name;
            document.getElementById("categoryRecord").value = button.dataset.id;
            form.action = root.dataset.updateUrlTemplate.replace(
                "__CATEGORY__",
                button.dataset.id,
            );
            modal.showModal();
        });

    document
        .getElementById("closeCategoryModal")
        .addEventListener("click", closeModal);

    document
        .getElementById("cancelCategoryModal")
        .addEventListener("click", closeModal);

    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    const previous = JSON.parse(root.dataset.oldInput || "{}");
    if (
        previous._form === "category-edit" &&
        /^\d+$/.test(previous._record ?? "")
    ) {
        nameInput.value = previous.name ?? "";
        document.getElementById("categoryRecord").value = previous._record;
        form.action = root.dataset.updateUrlTemplate.replace(
            "__CATEGORY__",
            previous._record,
        );
        modal.showModal();
    }
})();
