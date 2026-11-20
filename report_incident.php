
<?php
require_once __DIR__ . "/config/auth.php";

$resident = requireRole("resident");

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

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >
</head>

<body>

<main class="report-container">

    
<header class="report-topbar">

    <a
        href="resident_dashboard.php"
        class="report-topbar-identity"
    >
        <img
            src="images/barangay_logo_updated.png"
            alt="Barangay Chrysanthemum Official Logo"
            class="report-topbar-logo"
            width="54"
            height="54"
        >

        <span class="report-topbar-branding">
            <strong>Barangay Chrysanthemum</strong>
            <small>Resident Portal</small>
        </span>
    </a>

    <a
        href="resident_dashboard.php"
        class="back-link"
    >
        <span aria-hidden="true">&larr;</span>
        Back to Dashboard
    </a>

</header>


    <header class="report-header">
        <div class="header-badge">
            COMMUNITY REPORTING
        </div>

        <h1>Report an Incident</h1>

        <p>
            Welcome, <?= $firstName ?>!
            Help improve our community by reporting
            incidents that need barangay attention.
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

        <div class="form-section-heading">
            <span class="section-number">01</span>
            <div>
                <h2>Incident Details</h2>
                <p>Tell us what happened.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="category">Incident Category *</label>

            <select id="category" name="category" required>
                <option value="">Select category</option>
                <option value="Road and Infrastructure">Road and Infrastructure</option>
                <option value="Street Lighting">Street Lighting</option>
                <option value="Drainage and Flooding">Drainage and Flooding</option>
                <option value="Waste Management">Waste Management</option>
                <option value="Public Safety">Public Safety</option>
                <option value="Other">Other</option>
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
                placeholder="Describe what happened and provide helpful details..."
                required
            ></textarea>
        </div>

        <div class="form-section-heading location-heading">
            <span class="section-number">02</span>
            <div>
                <h2>Incident Location</h2>
                <p>Help us locate the incident accurately.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="location_address">
                Written Address or Landmark *
            </label>

            <div class="address-search-row">
                <input
                    type="text"
                    id="location_address"
                    name="location_address"
                    maxlength="255"
                    placeholder="Search street, landmark, or nearby establishment"
                    autocomplete="off"
                    required
                >

                <button
                    type="button"
                    id="search-location-button"
                    class="search-location-button"
                >
                    Search
                </button>
            </div>

            <p class="helper-text">
                Type an address and select a suggested location,
                or press Search.
            </p>

            <div
                id="address-suggestions"
                class="address-suggestions"
                hidden
            ></div>

            <p
                id="location-status"
                class="location-status"
                role="status"
                aria-live="polite"
            ></p>
        </div>

        <div class="map-toolbar">
            <span class="map-toolbar-title">
                Select Incident Location on Map *
            </span>

            <button
                type="button"
                id="my-location-button"
                class="my-location-button"
            >
                ◎ Use My Location
            </button>
        </div>

        <div id="incident-map"></div>

        <p class="helper-text map-helper">
            Click the map or drag the pin to adjust the
            exact incident location.
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

        <div class="form-section-heading photo-heading">
            <span class="section-number">03</span>
            <div>
                <h2>Supporting Photo</h2>
                <p>Add a photo to help barangay personnel understand the incident.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="photo">
                Incident Photo (Optional)
            </label>

            <input
                type="file"
                id="photo"
                name="photo"
                accept="image/jpeg,image/png,image/webp"
            >

            <p class="helper-text">
                Accepted formats: JPG, PNG, and WebP.
            </p>
        </div>

        <p
            id="form-message"
            role="status"
            aria-live="polite"
        ></p>

        <div class="form-actions">
            <a
                href="resident_dashboard.php"
                class="cancel-button"
            >
                Cancel
            </a>

            <button type="submit" id="submit-button">
                Submit Incident Report
            </button>
        </div>

    </form>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="report_incident.js"></script>

</body>
</html>
