
<?php

$currentPage = basename($_SERVER["SCRIPT_NAME"] ?? "");
$currentSection = $_GET["section"] ?? "";

$adminNavigation = [
    ["label" => "Dashboard Overview", "url" => "admin_dashboard.php", "icon" => "dashboard"],
    ["label" => "Incident Reports", "url" => "admin_incidents.php", "icon" => "reports"],
    ["label" => "Map Approvals", "url" => "admin_incidents.php?section=map", "icon" => "map"],
    ["label" => "Community Contributions", "url" => "admin_public_contributions.php", "icon" => "community"],
    ["label" => "Announcements", "url" => "admin_announcements.php", "icon" => "announcements"],
    ["label" => "Homepage Slideshow", "url" => "admin_slideshow.php", "icon" => "slideshow"],
    ["label" => "Residents", "url" => "admin_residents.php", "icon" => "residents"],
    ["label" => "Settings", "url" => "admin_settings.php", "icon" => "settings"]
];

$adminIcons = [
    "dashboard" => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',

    "reports" => '<path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/><path d="M8 12h8M8 16h6"/><path d="M14 3h7v7"/><path d="m21 3-9 9"/>',

    "map" => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',

    "community" => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',

    "announcements" => '<path d="m3 11 18-5v12L3 13z"/><path d="M7 14v5a2 2 0 0 0 4 0v-4"/><path d="M21 9a3 3 0 0 1 0 6"/>',

    "slideshow" => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/><path d="M7 21h10"/>',

    "residents" => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',

    "settings" => '<circle cx="12" cy="12" r="3.5"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l-2 2A1.7 1.7 0 0 0 15.86 18a1.7 1.7 0 0 0-1.16 1.57V21H9.3v-1.43A1.7 1.7 0 0 0 8.14 18a1.7 1.7 0 0 0-1.88.34l-2-2A1.7 1.7 0 0 0 4.6 14.46 1.7 1.7 0 0 0 3 13.3v-2.6a1.7 1.7 0 0 0 1.6-1.16 1.7 1.7 0 0 0-.34-1.88l2-2A1.7 1.7 0 0 0 8.14 6 1.7 1.7 0 0 0 9.3 4.43V3h5.4v1.43A1.7 1.7 0 0 0 15.86 6a1.7 1.7 0 0 0 1.88-.34l2 2A1.7 1.7 0 0 0 19.4 9.54 1.7 1.7 0 0 0 21 10.7v2.6A1.7 1.7 0 0 0 19.4 15z"/>',

    "logout" => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>'
];

?>

<aside class="sidebar" id="admin-sidebar">

    <div class="sidebar-brand sidebar-brand-modern">

        <div class="sidebar-brand-identity">

            <img
                src="images/barangay_logo_updated.png"
                alt="Barangay Chrysanthemum Official Logo"
                class="sidebar-logo"
                width="54"
                height="54"
            >

            <div class="sidebar-brand-text">
                <h2>Barangay Chrysanthemum</h2>
                <p>Admin Portal</p>
            </div>

        </div>

    </div>

    <nav class="sidebar-nav" aria-label="Admin navigation">

        <p class="sidebar-nav-heading">MAIN MENU</p>

        <?php foreach ($adminNavigation as $item): ?>

            <?php
            $label = $item["label"];
            $url = $item["url"];
            $icon = $item["icon"];

            $targetPage = basename(
                parse_url($url, PHP_URL_PATH)
            );

            $targetQuery = [];

            parse_str(
                parse_url($url, PHP_URL_QUERY) ?? "",
                $targetQuery
            );

            $isActive = ($currentPage === $targetPage);

            if ($targetPage === "admin_incidents.php") {
                $isActive = $isActive &&
                    (($targetQuery["section"] ?? "") === $currentSection);
            }
            ?>

            <a
                href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                class="sidebar-link<?= $isActive ? ' active' : '' ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>
                title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
            >

                <svg
                    class="sidebar-icon"
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    focusable="false"
                >
                    <?= $adminIcons[$icon] ?>
                </svg>

                <span class="sidebar-link-label">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

    <div class="sidebar-footer">

        <div class="sidebar-footer-divider"></div>

        <div class="sidebar-admin-info">

            <span class="sidebar-admin-avatar" aria-hidden="true">
                A
            </span>

            <div class="sidebar-admin-details">
                <strong>Administrator</strong>
                <small>Barangay Management</small>
            </div>

        </div>

        <form id="admin-logout-form">

            <button
                type="submit"
                class="logout-button sidebar-logout-modern"
                title="Logout"
            >

                <svg
                    class="sidebar-icon"
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    focusable="false"
                >
                    <?= $adminIcons["logout"] ?>
                </svg>

                <span>Logout</span>

            </button>

        </form>

    </div>

</aside>

