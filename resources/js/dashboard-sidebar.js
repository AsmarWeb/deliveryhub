document.addEventListener("DOMContentLoaded", () => {
    const shell = document.getElementById("dashboard-shell");
    const sidebar = document.getElementById("dashboard-sidebar");
    const overlay = document.getElementById("sidebar-overlay");
    const open = document.getElementById("sidebar-open");
    const close = document.getElementById("sidebar-close");
    const collapse = document.getElementById("sidebar-collapse");
    if (!shell || !sidebar || !overlay) return;

    const desktop = window.matchMedia("(min-width: 1024px)");
    let previousFocus = null;
    const storageKey = "deliveryhub.sidebar.collapsed";
    const isOpen = () => sidebar.classList.contains("is-open");
    const focusables = () =>
        [...sidebar.querySelectorAll("a[href],button:not([disabled])")].filter(
            (el) => el.getClientRects().length,
        );

    function setOpen(value, restoreFocus = true) {
        const next = value && !desktop.matches;
        if (next && !isOpen()) previousFocus = document.activeElement;
        sidebar.classList.toggle("is-open", next);
        overlay.hidden = !next;
        document.body.classList.toggle("sidebar-mobile-open", next);
        open?.setAttribute("aria-expanded", String(next));
        if (!desktop.matches) {
            sidebar.inert = !next;
            sidebar.setAttribute("aria-hidden", String(!next));
        } else {
            sidebar.inert = false;
            sidebar.removeAttribute("aria-hidden");
        }
        if (next) close?.focus();
        else if (restoreFocus && previousFocus && !desktop.matches)
            previousFocus.focus();
        if (!next) previousFocus = null;
    }

    function updateCollapsed() {
        const collapsed = shell.classList.contains("sidebar-collapsed");
        collapse?.setAttribute("aria-expanded", String(!collapsed));
        collapse?.setAttribute(
            "aria-label",
            collapsed ? "Expand sidebar" : "Collapse sidebar",
        );
    }
    try {
        if (localStorage.getItem(storageKey) === "1")
            shell.classList.add("sidebar-collapsed");
    } catch (_) {}
    updateCollapsed();
    setOpen(false, false);
    open?.addEventListener("click", () => setOpen(!isOpen()));
    close?.addEventListener("click", () => setOpen(false));
    overlay.addEventListener("click", () => setOpen(false));
    collapse?.addEventListener("click", () => {
        shell.classList.toggle("sidebar-collapsed");
        try {
            localStorage.setItem(
                storageKey,
                shell.classList.contains("sidebar-collapsed") ? "1" : "0",
            );
        } catch (_) {}
        updateCollapsed();
    });
    desktop.addEventListener("change", () => setOpen(false, false));
    document.addEventListener("keydown", (event) => {
        if (!isOpen()) return;
        if (event.key === "Escape") {
            setOpen(false);
            return;
        }
        if (event.key !== "Tab") return;
        const items = focusables();
        if (!items.length) return;
        if (event.shiftKey && document.activeElement === items[0]) {
            event.preventDefault();
            items.at(-1).focus();
        }
        if (!event.shiftKey && document.activeElement === items.at(-1)) {
            event.preventDefault();
            items[0].focus();
        }
    });
    let touchStart = null;
    sidebar.addEventListener(
        "touchstart",
        (e) => {
            if (!isOpen() || desktop.matches) return;
            touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
        },
        { passive: true },
    );
    sidebar.addEventListener(
        "touchend",
        (e) => {
            if (!touchStart) return;
            const dx = e.changedTouches[0].clientX - touchStart.x;
            const dy = e.changedTouches[0].clientY - touchStart.y;
            touchStart = null;
            if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy)) return;
            const rtl = document.documentElement.dir === "rtl";
            if (rtl ? dx > 0 : dx < 0) setOpen(false);
        },
        { passive: true },
    );
});
