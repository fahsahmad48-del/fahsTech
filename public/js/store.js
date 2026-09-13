(() => {
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const observer =
        "IntersectionObserver" in window
            ? new IntersectionObserver(
                  (entries) => {
                      entries.forEach((entry) => {
                          if (entry.isIntersecting) {
                              entry.target.classList.add("in-view");
                              observer.unobserve(entry.target);
                          }
                      });
                  },
                  { threshold: 0, rootMargin: "0px 0px -20px 0px" },
              )
            : null;

    window.initReveal = (root = document) => {
        root.querySelectorAll(".reveal:not(.observed)").forEach((element) => {
            element.classList.add("observed");
            if (!observer || reducedMotion.matches) {
                element.classList.add("in-view");
            } else {
                element.classList.add("reveal-ready");
                observer.observe(element);
            }
        });
    };
    window.clearReveal = (root) => {
        root.querySelectorAll(".observed").forEach((element) =>
            observer?.unobserve(element),
        );
    };
    window.initReveal();

    const toggle = document.getElementById("menuToggle");
    const nav = document.getElementById("mainNav");
    const setMenu = (open) => {
        nav?.classList.toggle("open", open);
        toggle?.setAttribute("aria-expanded", String(open));
    };
    toggle?.addEventListener("click", () =>
        setMenu(toggle.getAttribute("aria-expanded") !== "true"),
    );
    nav?.addEventListener("click", (event) => {
        if (event.target.closest("a")) setMenu(false);
    });
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".site-header")) setMenu(false);
    });
    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            toggle?.getAttribute("aria-expanded") === "true"
        ) {
            setMenu(false);
            toggle.focus();
        }
    });
    window
        .matchMedia("(min-width: 761px)")
        .addEventListener("change", () => setMenu(false));

    let confirmation;
    document.addEventListener("submit", (event) => {
        const form = event.target;
        if (
            event.defaultPrevented ||
            form.method === "dialog" ||
            form.method === "get"
        )
            return;
        if (form.dataset.submitting) {
            event.preventDefault();
            return;
        }
        if (form.dataset.confirm && !form.dataset.confirmed) {
            event.preventDefault();
            if (!confirmation) {
                confirmation = document.createElement("dialog");
                confirmation.className = "category-delete-warning";
                confirmation.setAttribute("aria-labelledby", "deleteTitle");
                confirmation.innerHTML =
                    '<form method="dialog"><h3 id="deleteTitle">Confirm deletion</h3><p id="deleteMessage"></p><div class="dialog-actions"><button class="btn btn-outline" value="cancel" autofocus>Cancel</button><button class="btn btn-danger" value="delete">Delete</button></div></form>';
                document.body.appendChild(confirmation);
            }
            confirmation.querySelector("#deleteMessage").textContent =
                form.dataset.confirm;
            confirmation.returnValue = "";
            confirmation.addEventListener(
                "close",
                () => {
                    if (confirmation.returnValue === "delete") {
                        form.dataset.confirmed = "true";
                        form.requestSubmit(event.submitter);
                    }
                },
                { once: true },
            );
            confirmation.showModal();
            return;
        }
        form.dataset.submitting = "true";
        form.setAttribute("aria-busy", "true");
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.dataset.originalText = button.textContent;
            button.disabled = true;
            button.textContent = "Please wait…";
        });
    });
    window.addEventListener("pageshow", () => {
        document.querySelectorAll("form[data-submitting]").forEach((form) => {
            delete form.dataset.submitting;
            delete form.dataset.confirmed;
            form.removeAttribute("aria-busy");
            form.querySelectorAll("[data-original-text]").forEach((button) => {
                button.disabled = false;
                button.textContent = button.dataset.originalText;
            });
        });
    });
})();
