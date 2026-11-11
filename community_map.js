
// Initialize the Community Map.
const communityMap = L.map("community-map").setView(
    [14.3390453, 121.0432380],
    13
);

// Add OpenStreetMap tiles.
L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19
    }
).addTo(communityMap);

// Escape text before inserting it into HTML.
function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => {
        const entities = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        };

        return entities[char];
    });
}

// Load only approved incidents from the API.
async function loadApprovedIncidents() {
    try {
        const response = await fetch(
            "api/get_approved_incidents.php",
            { cache: "no-store" }
        );

        if (!response.ok) {
            throw new Error("Unable to load incidents.");
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.incidents)) {
            throw new Error("Invalid API response.");
        }

        const markerBounds = [];

        data.incidents.forEach((incident) => {
            const latitude = Number(incident.latitude);
            const longitude = Number(incident.longitude);

            // Skip invalid coordinates.
            if (
                !Number.isFinite(latitude) ||
                !Number.isFinite(longitude) ||
                latitude < -90 ||
                latitude > 90 ||
                longitude < -180 ||
                longitude > 180
            ) {
                return;
            }

            const popupContent = `
                <div>
                    <h3>${escapeHtml(incident.title)}</h3>

                    <p>
                        <strong>Category:</strong>
                        ${escapeHtml(incident.category)}
                    </p>

                    <p>
                        <strong>Location:</strong>
                        ${escapeHtml(incident.location_address)}
                    </p>

                    <p>
                        <strong>Processing Status:</strong>
                        ${escapeHtml(incident.processing_status)}
                    </p>
                </div>
            `;

            L.marker([latitude, longitude])
                .addTo(communityMap)
                .bindPopup(popupContent);

            markerBounds.push([latitude, longitude]);
        });

        // Automatically focus on approved incidents.
        if (markerBounds.length === 1) {
            communityMap.setView(markerBounds[0], 15);
        } else if (markerBounds.length > 1) {
            communityMap.fitBounds(markerBounds, {
                padding: [40, 40],
                maxZoom: 15
            });
        }

    } catch (error) {
        console.error(
            "Community Map Error:",
            error
        );

        alert(
            "Unable to load community incidents. Please try again."
        );
    }
}

// Load incidents when the page opens.
loadApprovedIncidents();
