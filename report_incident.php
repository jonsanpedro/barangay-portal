<?php
require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this page.
$resident = requireRole("resident");


/* Generate CSRF token for incident submission. */

if (empty($_SESSION["incident_csrf_token"])) {
    $_SESSION["incident_csrf_token"] = bin2hex(random_bytes(32));
}


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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Report an Incident | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="report_incident.css">

    <!-- Leaflet CSS -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >
</head>

<body>
    <main class="report-container">

        <header class="report-header">
            <h1>Report an Incident</h1>
            <p>
                Welcome, <?= $firstName ?>!
                Please provide accurate incident information.
            </p>
        </header>

        <form
            id="incident-form"
            method="POST"
            enctype="multipart/form-data"
        >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
            $_SESSION["incident_csrf_token"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
        >

            <div class="form-group">
                <label for="category">Incident Category *</label>

                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <option value="Road and Infrastructure">
                        Road and Infrastructure
                    </option>
                    <option value="Street Lighting">
                        Street Lighting
                    </option>
                    <option value="Drainage and Flooding">
                        Drainage and Flooding
                    </option>
                    <option value="Waste Management">
                        Waste Management
                    </option>
                    <option value="Public Safety">
                        Public Safety
                    </option>
                    <option value="Other">
                        Other
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Incident Title *</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="200"
                    placeholder="e.g., Broken streetlight"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    placeholder="Describe the incident..."
                    required
                ></textarea>
            </div>

            <div class="form-group">
                <label>Select Incident Location on Map *</label>

                <div id="incident-map"></div>

                <p class="helper-text">
                    Click the map to place a pin at the incident location.
                </p>

                <input
                    type="hidden"
                    id="latitude"
                    name="latitude"
                >

                <input
                    type="hidden"
                    id="longitude"
                    name="longitude"
                >
            </div>

            <div class="form-group">
                <label for="location_address">
                    Written Address or Landmark *
                </label>

                <input
                    type="text"
                    id="location_address"
                    name="location_address"
                    maxlength="255"
                    placeholder="Street, landmark, or nearby establishment"
                    required
                >
            </div>

            <div class="form-group">
                <label for="photo">Incident Photo (Optional)</label>

                <input
                    type="file"
                    id="photo"
                    name="photo"
                    accept="image/jpeg,image/png,image/webp"
                >
            </div>

            <p id="form-message" role="status"></p>

            <div class="form-actions">
                <a href="resident_dashboard.php"
                   class="cancel-button">
                    Cancel
                </a>

                <button type="submit" id="submit-button">
                    Submit Incident Report
                </button>
            </div>

        </form>
    </main>

    <!-- Leaflet JavaScript -->
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script src="report_incident.js"></script>
</body>
</html>
