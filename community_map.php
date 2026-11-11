<?php
// Public Community Map
// No login required to view approved incidents.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Community Map | Barangay Chrysanthemum</title>

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="community_map.css">
</head>

<body>

    <header class="map-header">
        <div class="map-brand">
            <h1>Barangay Chrysanthemum</h1>
            <p>Incident & Public Service Request Portal</p>
        </div>

        <a href="index.html" class="back-btn">
            Back to Home
        </a>
    </header>

    <main class="map-container">

        <div class="map-intro">
            <h2>Community Issue Map</h2>
            <p>
                View reported community incidents
                reviewed and approved by barangay personnel.
            </p>
        </div>

        <div id="community-map"></div>

        <p class="map-note">
            Only incidents approved by barangay personnel
            are displayed on this map.
        </p>

    </main>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script src="community_map.js"></script>

</body>
</html>
