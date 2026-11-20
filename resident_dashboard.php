
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this dashboard.
$resident = requireRole("resident");

// Safely display the resident's first name.
$firstName = htmlspecialchars(
    $resident["first_name"],
    ENT_QUOTES,
    "UTF-8"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Resident Dashboard | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="resident_dashboard.css">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
</head>

<body>

    <main class="resident-dashboard">

        <!-- TOP NAVIGATION -->
        <div class="dashboard-topbar">

            <a href="index.html" class="dashboard-brand">
                <span class="dashboard-brand-icon">
                    <img
                        src="images/barangay_logo_updated.png"
                        alt="Barangay Chrysanthemum Logo"
                        class="dashboard-brand-logo"
                    >
                </span>

                <span class="dashboard-brand-text">
                    <strong>Barangay Chrysanthemum</strong>
                    <small>Resident Services Portal</small>
                </span>
            </a>

            <a href="index.html" class="dashboard-home-link">
                <i class="bi bi-house-door"></i>
                <span>Back to Homepage</span>
            </a>

        </div>


        <!-- WELCOME SECTION -->
        <section class="resident-header">

            <div class="resident-header-content">

                <div class="resident-welcome-label">
                    <i class="bi bi-stars"></i>
                    RESIDENT DASHBOARD
                </div>

                <h1>
                    Welcome back,
                    <span><?= $firstName ?>!</span>
                </h1>

                <p>
                    Your community, your voice.
                    Report concerns, monitor your submitted
                    incidents, and stay informed about
                    what's happening in Barangay Chrysanthemum.
                </p>

                <div class="resident-header-actions">

                    <a
                        href="report_incident.php"
                        class="resident-primary-btn"
                    >
                        <i class="bi bi-plus-circle"></i>
                        Report an Incident
                    </a>

                    <a
                        href="my_incidents.php"
                        class="resident-outline-btn"
                    >
                        <i class="bi bi-clipboard-check"></i>
                        View My Reports
                    </a>

                </div>

            </div>

            <div class="resident-header-decoration"
                 aria-hidden="true">

                <div class="resident-decoration-circle">
                    <i class="bi bi-buildings"></i>
                </div>

            </div>

        </section>


        <!-- SECTION HEADING -->
        <div class="resident-section-heading">

            <div>
                <span class="resident-section-label">
                    QUICK ACCESS
                </span>

                <h2>What would you like to do?</h2>

                <p>
                    Access the services you need in just a few clicks.
                </p>
            </div>

        </div>


        <!-- DASHBOARD ACTION CARDS -->
        <section
            class="resident-actions"
            aria-label="Resident services"
        >

            <!-- CARD 1 -->
            <a
                href="report_incident.php"
                class="resident-action-card"
            >

                <div class="resident-card-icon">
                    <i class="bi bi-file-earmark-plus"></i>
                </div>

                <div class="resident-card-content">

                    <h3>Report an Incident</h3>

                    <p>
                        Submit a new community concern,
                        select its location on the map,
                        and optionally upload a photo.
                    </p>

                </div>

                <span class="resident-card-link">
                    Submit Report
                    <i class="bi bi-arrow-right"></i>
                </span>

            </a>


            <!-- CARD 2 -->
            <a
                href="my_incidents.php"
                class="resident-action-card"
            >

                <div class="resident-card-icon">
                    <i class="bi bi-clipboard-data"></i>
                </div>

                <div class="resident-card-content">

                    <h3>My Incident Reports</h3>

                    <p>
                        View your submitted incidents,
                        reference numbers, processing statuses,
                        and map approval statuses.
                    </p>

                </div>

                <span class="resident-card-link">
                    View My Reports
                    <i class="bi bi-arrow-right"></i>
                </span>

            </a>


            <!-- CARD 3 -->
            <a
                href="community_map.php"
                class="resident-action-card"
            >

                <div class="resident-card-icon">
                    <i class="bi bi-geo-alt"></i>
                </div>

                <div class="resident-card-content">

                    <h3>Community Map</h3>

                    <p>
                        Explore community incidents that have
                        been reviewed and approved by
                        barangay personnel on the public map.
                    </p>

                </div>

                <span class="resident-card-link">
                    Explore Map
                    <i class="bi bi-arrow-right"></i>
                </span>

            </a>

        </section>


        <!-- INFORMATION STRIP -->
        <section class="resident-info-strip">

            <div class="resident-info-icon">
                <i class="bi bi-info-circle"></i>
            </div>

            <div class="resident-info-text">

                <h3>We're here to help</h3>

                <p>
                    Need assistance using the portal?
                    Click the Help & FAQ button in the
                    lower-right corner for guidance.
                </p>

            </div>

        </section>


        <!-- FOOTER -->
        <footer class="resident-dashboard-footer">

            <p>
                &copy; <?= date("Y") ?>
                Barangay Chrysanthemum.
                All rights reserved.
            </p>

            <a href="index.html">
                <i class="bi bi-arrow-left"></i>
                Return to Homepage
            </a>

        </footer>

    </main>

    <?php require_once __DIR__ . "/faq_chatbot.php"; ?>

</body>
</html>
