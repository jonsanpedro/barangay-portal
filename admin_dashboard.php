<?php

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

$firstName = htmlspecialchars(
    $admin["first_name"],
    ENT_QUOTES,
    "UTF-8"
);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>
    <div class="admin-layout">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h2>BC Portal</h2>
                <p>Administration</p>
            </div>

            <nav class="sidebar-nav" aria-label="Admin navigation">
                <a href="admin_dashboard.php" class="active">
                    Dashboard Overview
                </a>

                <a href="#" class="nav-placeholder">
                    Incident Reports
                </a>

                <a href="#" class="nav-placeholder">
                    Map Approvals
                </a>

                <a href="#" class="nav-placeholder">
                    Announcements
                </a>

                <a href="#" class="nav-placeholder">
                    Residents
                </a>

                <a href="#" class="nav-placeholder">
                    Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <form id="admin-logout-form">
                    <button type="submit" class="logout-button">
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">

            <header class="dashboard-header">
                <div>
                    <h1>Dashboard Overview</h1>
                    <p>Welcome, <?= $firstName ?>!</p>
                </div>

                <div class="admin-profile">
                    Barangay Administrator
                </div>
            </header>

            <!-- Summary Cards -->
            <section class="stats-grid" aria-label="Incident summary">

                <article class="stat-card">
                    <p>Total Reports</p>
                    <h2>—</h2>
                </article>

                <article class="stat-card">
                    <p>Pending Reports</p>
                    <h2>—</h2>
                </article>

                <article class="stat-card">
                    <p>In Progress</p>
                    <h2>—</h2>
                </article>

                <article class="stat-card">
                    <p>Resolved Reports</p>
                    <h2>—</h2>
                </article>

            </section>

            <!-- Recent Reports -->
            <section class="recent-reports">
                <div class="section-heading">
                    <h2>Recent Incident Reports</h2>
                    <p>
                        Latest incident submissions
                        will appear here.
                    </p>
                </div>

                <div class="empty-state">
                    No incident data connected yet.
                </div>
            </section>

        </main>
    </div>

<script>
document.getElementById("admin-logout-form")
    .addEventListener("submit", async function(event) {
        event.preventDefault();

        try {
            const response = await fetch("api/logout.php", {
                method: "POST",
                credentials: "same-origin"
            });

            if (!response.ok) {
                throw new Error("Logout failed.");
            }

            window.location.href = "index.html";

        } catch (error) {
            alert("Unable to log out. Please try again.");
        }
    });
</script>


</body>
</html>
