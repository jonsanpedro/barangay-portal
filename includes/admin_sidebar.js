
(function () {
    "use strict";

    const sidebar = document.getElementById("admin-sidebar");
    const toggle = document.getElementById("admin-sidebar-toggle");
    const overlay = document.getElementById("admin-sidebar-overlay");

    if (!sidebar || !toggle || !overlay) return;

    const mobileQuery = window.matchMedia("(max-width: 768px)");

    let collapsed = false;

    try {
        collapsed = localStorage.getItem("adminSidebarCollapsed") === "true";
    } catch (error) {
        collapsed = false;
    }

    function updateSidebar() {
        const isMobile = mobileQuery.matches;

        document.body.classList.toggle(
            "admin-sidebar-collapsed",
            collapsed && !isMobile
        );

        document.body.classList.remove("admin-sidebar-mobile-open");

        toggle.setAttribute(
            "aria-expanded",
            String(!collapsed && !isMobile)
        );

        toggle.setAttribute(
            "aria-label",
            collapsed && !isMobile
                ? "Expand sidebar"
                : "Collapse sidebar"
        );
    }

    toggle.addEventListener("click", function () {
        if (mobileQuery.matches) {
            const isOpen = document.body.classList.toggle(
                "admin-sidebar-mobile-open"
            );

            toggle.setAttribute("aria-expanded", String(isOpen));
            toggle.setAttribute(
                "aria-label",
                isOpen ? "Close sidebar" : "Open sidebar"
            );

            return;
        }

        collapsed = !collapsed;

        try {
            localStorage.setItem(
                "adminSidebarCollapsed",
                String(collapsed)
            );
        } catch (error) {
            // Sidebar remains usable if storage is unavailable.
        }

        updateSidebar();
    });

    overlay.addEventListener("click", function () {
        document.body.classList.remove("admin-sidebar-mobile-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Open sidebar");
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            document.body.classList.remove("admin-sidebar-mobile-open");

            if (mobileQuery.matches) {
                toggle.setAttribute("aria-expanded", "false");
                toggle.setAttribute("aria-label", "Open sidebar");
            }
        }
    });

    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener("change", updateSidebar);
    } else {
        mobileQuery.addListener(updateSidebar);
    }

    updateSidebar();
})();
