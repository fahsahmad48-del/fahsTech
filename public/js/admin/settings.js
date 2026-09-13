(() => {
    if (
        !document.getElementById("settingsForm") &&
        !document.getElementById("passwordForm")
    )
        return;
    const admin = window.MiniStoreAdmin;
    if (!admin) return;
    const { interceptForms } = admin;

    interceptForms("#settingsForm, #passwordForm", async () => {});
})();
