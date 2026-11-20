
<?php
require_once __DIR__ . "/config/auth.php";

if (empty($_SESSION["support_csrf_token"])) {
    $_SESSION["support_csrf_token"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="support-csrf-token"
          content="<?= htmlspecialchars(
              $_SESSION["support_csrf_token"],
              ENT_QUOTES,
              "UTF-8"
          ) ?>">

    <title>Community Issue Map | Barangay Chrysanthemum</title>

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="community_map.css">
</head>

<body>

    <header class="map-header">
        <div class="map-header-inner">

            <a href="index.html" class="map-brand">
                <span class="map-brand-logo">
                    <img
                        src="images/barangay_logo_updated.png"
                        alt="Barangay Chrysanthemum Logo"
                    >
                </span>

                <span class="map-brand-text">
                    <strong>Barangay Chrysanthemum</strong>
                    <small>Incident & Public Service Request Portal</small>
                </span>
            </a>

            <a href="index.html" class="back-btn">
                <span aria-hidden="true">←</span>
                Back to Home
            </a>

        </div>
    </header>

    <main class="map-container">

        <section class="map-hero">
            <div class="map-hero-content">

                <span class="map-eyebrow">
                    COMMUNITY MONITORING
                </span>

                <h1>Community Issue Map</h1>

                <p>
                    Stay informed about community concerns.
                    Explore incidents reviewed and approved
                    by barangay personnel, check their progress,
                    and support concerns affecting your area.
                </p>

            </div>

            <div class="map-hero-decoration" aria-hidden="true">
                <span>⌖</span>
            </div>
        </section>

        <section class="map-content-card">

            <div class="map-section-header">
                <div>
                    <span class="map-section-label">
                        INTERACTIVE COMMUNITY MAP
                    </span>

                    <h2>Explore Reported Incidents</h2>

                    <p>
                        Select a marker to view incident information,
                        processing status, and available actions.
                    </p>
                </div>

                <div class="map-live-indicator">
                    <span class="live-dot"></span>
                    Approved Reports
                </div>
            </div>

            <div class="map-summary">
                <div class="summary-item">
                    <span class="summary-icon">📍</span>

                    <div>
                        <strong id="total-incidents">—</strong>
                        <span>Mapped Incidents</span>
                    </div>
                </div>

                <div class="summary-item">
                    <span class="summary-icon">🗺️</span>

                    <div>
                        <strong>Interactive Map</strong>
                        <span>Explore incident locations</span>
                    </div>
                </div>

                <div class="summary-item">
                    <span class="summary-icon">✓</span>

                    <div>
                        <strong>Reviewed Reports</strong>
                        <span>Approved for public display</span>
                    </div>
                </div>
            </div>

            <div class="map-display">
                <div id="community-map"
                     aria-label="Interactive community incident map">
                </div>
            </div>

            <div class="map-bottom-info">

                <div class="map-legend">
                    <strong>Incident Status</strong>

                    <div class="legend-items">
                        <span>
                            <i class="legend-dot pending"></i>
                            Pending
                        </span>

                        <span>
                            <i class="legend-dot progress"></i>
                            In Progress
                        </span>

                        <span>
                            <i class="legend-dot resolved"></i>
                            Resolved
                        </span>
                    </div>
                </div>

                <p class="map-note">
                    <span aria-hidden="true">ⓘ</span>
                    Only incidents approved by barangay
                    personnel are displayed on this map.
                </p>

            </div>

        </section>

        <section class="map-help-section">
            <div class="map-help-icon" aria-hidden="true">?</div>

            <div>
                <h2>How to use the Community Map</h2>

                <p>
                    Click an incident marker to view its details.
                    Use the layer control in the upper-right corner
                    of the map to show or hide incident markers
                    and the heatmap.
                </p>
            </div>
        </section>

    </main>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script
        src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js">
    </script>

    <script src="community_map.js"></script>

    <?php require_once __DIR__ . "/faq_chatbot.php"; ?>

</body>
</html>
