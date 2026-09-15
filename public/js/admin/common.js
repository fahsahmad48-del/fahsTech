(() => {
    // Shared in-page form handling for the admin pages.
    let actionPending = false;
    let validationId = 0;
    function showMessage(message, type = "success") {
        const box = document.getElementById("adminMessage");
        if (!box) return;
        box.textContent = message;
        box.hidden = false;
        box.classList.toggle("note-success", type === "success");
        box.style.color = type === "error" ? "#dc2626" : "";
        box.setAttribute("role", type === "error" ? "alert" : "status");
    }
    function clearErrors(form) {
        form.querySelectorAll(
            '[data-validation-error], .note[role="alert"]',
        ).forEach((error) => error.remove());
        form.querySelectorAll("[data-error-id]").forEach((field) => {
            const ids = (field.getAttribute("aria-describedby") || "")
                .split(" ")
                .filter((id) => id && id !== field.dataset.errorId);
            if (ids.length)
                field.setAttribute("aria-describedby", ids.join(" "));
            else field.removeAttribute("aria-describedby");
            field.removeAttribute("aria-invalid");
            delete field.dataset.errorId;
        });
    }
    function showErrors(form, errors, message) {
        const summary = document.createElement("div");
        summary.className = "note";
        summary.dataset.validationError = "";
        summary.setAttribute("role", "alert");
        summary.textContent = message;
        form.prepend(summary);
        let firstField;
        for (const [key, messages] of Object.entries(errors || {})) {
            const parts = key.split(".");
            const name =
                parts.shift() + parts.map((part) => `[${part}]`).join("");
            const control = form.elements.namedItem(name);
            const field = control?.closest?.(".searchable-select")
                ?.querySelector(".searchable-select-trigger") || control;
            const error = document.createElement("small");
            error.dataset.validationError = "";
            error.style.cssText = "display:block;color:#dc2626";
            error.textContent = Array.isArray(messages)
                ? messages.join(" ")
                : String(messages);
            if (field instanceof HTMLElement && field.type !== "hidden") {
                error.id = `validation-${++validationId}`;
                field.dataset.errorId = error.id;
                field.setAttribute("aria-invalid", "true");
                field.setAttribute(
                    "aria-describedby",
                    [field.getAttribute("aria-describedby"), error.id]
                        .filter(Boolean)
                        .join(" "),
                );
                field.insertAdjacentElement("afterend", error);
                firstField ??= field;
            } else summary.append(error);
        }
        firstField?.focus();
    }
    function confirmDeletion(message) {
        return new Promise((resolve) => {
            const dialog = document.createElement("dialog");
            dialog.className = "category-delete-warning";
            dialog.setAttribute("aria-labelledby", "actionDeleteTitle");
            dialog.innerHTML =
                '<form method="dialog"><h3 id="actionDeleteTitle">Confirm deletion</h3><p></p><div class="dialog-actions"><button class="btn btn-outline" value="cancel" autofocus>Cancel</button><button class="btn btn-danger" value="delete">Delete</button></div></form>';
            dialog.querySelector("p").textContent = message;
            dialog.addEventListener(
                "close",
                () => {
                    resolve(dialog.returnValue === "delete");
                    dialog.remove();
                },
                { once: true },
            );
            document.body.append(dialog);
            dialog.showModal();
        });
    }
    function interceptForms(selector, afterSuccess, options = {}) {
        function reportError(form, errors, message, status) {
            if (options.onError) {
                options.onError(form, errors, message, status);
                return;
            }
            showErrors(form, errors, message);
            showMessage(message, "error");
        }
        document
            .querySelector("main")
            ?.addEventListener("submit", async (event) => {
                const form = event.target;
                if (!form.matches(selector)) return;
                event.preventDefault(); // The shared store script also respects defaultPrevented.
                if (actionPending) return;
                actionPending = true;
                let buttons = [];
                try {
                    if (
                        form.dataset.confirm &&
                        !(await confirmDeletion(form.dataset.confirm))
                    )
                        return;
                    clearErrors(form);
                    options.beforeSubmit?.(form);
                    const messageBox = document.getElementById("adminMessage");
                    if (messageBox) messageBox.hidden = true;
                    const data = new FormData(form);
                    const method = String(
                        data.get("_method") || "POST",
                    ).toUpperCase();
                    if (method === "PUT") data.set("_method", "PUT");
                    const modal = form.closest("dialog");
                    buttons = Array.from(
                        (modal || form).querySelectorAll("button"),
                    ).map((button) => [
                        button,
                        button.disabled,
                        button.textContent,
                    ]);
                    buttons.forEach(([button]) => {
                        button.disabled = true;
                    });
                    const submit =
                        event.submitter ||
                        form.querySelector('button[type="submit"]');
                    if (submit)
                        submit.textContent =
                            method === "DELETE" ? "Deleting..." : "Saving...";
                    form.setAttribute("aria-busy", "true");
                    const response = await fetch(form.action, {
                        method: method === "DELETE" ? "DELETE" : "POST",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN":
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                )?.content || "",
                        },
                        body: method === "DELETE" ? undefined : data,
                    });
                    const payload = response.headers
                        .get("content-type")
                        ?.includes("application/json")
                        ? await response.json()
                        : {};
                    if (!response.ok || response.redirected) {
                        const message =
                            response.status === 419
                                ? "Your session expired. Please sign in again."
                                : response.status === 401
                                  ? "Please sign in again."
                                  : response.status === 429
                                    ? "Too many attempts. Please wait and try again."
                                    : response.status >= 500
                                      ? "The server could not complete the request. Please try again."
                                      : payload.message ||
                                        "Unable to complete the request. Please try again.";
                        reportError(form, payload.errors, message, response.status);
                        return;
                    }
                    if (payload.csrf_token) {
                        const csrfMeta = document.querySelector(
                            'meta[name="csrf-token"]',
                        );
                        if (csrfMeta) csrfMeta.content = payload.csrf_token;
                        document
                            .querySelectorAll('input[name="_token"]')
                            .forEach((input) => {
                                input.value = payload.csrf_token;
                                input.defaultValue = payload.csrf_token;
                            });
                    }
                    modal?.close();
                    if (method === "POST" || form.id === "passwordForm")
                        form.reset();
                    showMessage(payload.message || "Saved successfully.");
                    await afterSuccess(form, payload);
                } catch (error) {
                    const message =
                        "The request could not be completed. Check your connection and try again.";
                    reportError(form, {}, message);
                } finally {
                    buttons.forEach(([button, disabled, text]) => {
                        button.disabled = disabled;
                        button.textContent = text;
                    });
                    form.removeAttribute("aria-busy");
                    actionPending = false;
                }
            });
        document.querySelectorAll("dialog").forEach((dialog) => {
            dialog.addEventListener("cancel", (event) => {
                if (actionPending) event.preventDefault();
            });
        });
    }

    window.MiniStoreAdmin = {
        showMessage,
        clearErrors,
        interceptForms,
        get actionPending() {
            return actionPending;
        },
    };
})();
