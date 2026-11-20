
"use strict";

// =====================================================
// BARANGAY CHRYSANTHEMUM - COMMUNITY ISSUE MAP
// =====================================================
// Features:
// - Approved incidents only (filtered by PHP API)
// - Color-coded status markers
// - Incident heatmap
// - Resident support / same concern
// - View Full Incident Details
// - Automatic mapped incident counter
// =====================================================


// =====================================================
// 1. INITIALIZE MAP
// =====================================================

const map = L.map("community-map").setView(
    [14.3390453, 121.0432380],
    13
);

// Load OpenStreetMap tiles.
L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19
    }
).addTo(map);


// =====================================================
// 2. MAP LAYERS
// =====================================================

// Layer containing incident markers.
const incidentLayer = L.layerGroup().addTo(map);

// Heatmap layer.
const heatmapLayer = L.heatLayer([], {
    radius: 28,
    blur: 20,
    maxZoom: 17
});

// Layer selection controls.
L.control.layers(
    {},
    {
        "Incident Markers": incidentLayer,
        "Incident Heatmap": heatmapLayer
    },
    {
        collapsed: false
    }
).addTo(map);


// =====================================================
// 3. SUPPORT DATA
// =====================================================

const supportData = new Map();

let canSupport = false;


// =====================================================
// 4. SECURITY HELPERS
// =====================================================

// Escape untrusted text before inserting into HTML.
function escapeHtml(value) {

    return String(value ?? "").replace(
        /[&<>"']/g,
        char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[char]
    );

}


// =====================================================
// 5. INCIDENT STATUS COLORS
// =====================================================

function getStatusColor(status) {

    switch (status) {

        case "Pending":
            return "#f59e0b";

        case "In Progress":
            return "#2563eb";

        case "Resolved":
            return "#16a34a";

        default:
            return "#64748b";

    }

}


// =====================================================
// 6. LOAD SUPPORT COUNTS
// =====================================================

async function loadSupportData() {

    const response = await fetch(
        "api/get_incident_supports.php",
        {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        }
    );

    if (!response.ok) {

        throw new Error(
            "Unable to load support counts."
        );

    }

    const data = await response.json();

    if (
        !data.success ||
        !Array.isArray(data.supports)
    ) {

        throw new Error(
            "Invalid support API response."
        );

    }

    supportData.clear();

    data.supports.forEach(item => {

        const id = Number(item.incident_id);

        if (
            !Number.isSafeInteger(id) ||
            id <= 0
        ) {
            return;
        }

        supportData.set(id, {

            count: Math.max(
                0,
                Number(item.support_count) || 0
            ),

            supported:
                Number(item.supported) === 1

        });

    });

    canSupport = data.can_support === true;

}


// =====================================================
// 7. GENERATE INCIDENT POPUP
// =====================================================

function createIncidentPopup(incident) {

    const id = Number(incident.incident_id);

    const color = getStatusColor(
        incident.processing_status
    );

    const support = supportData.get(id) || {
        count: 0,
        supported: false
    };

    // Show support button only when allowed.
    const supportButton = canSupport
        ? `
            <button
                type="button"
                class="incident-support-btn"
                data-incident-id="${id}"
            >
                ${
                    support.supported
                        ? "Remove My Support"
                        : "I Have the Same Concern"
                }
            </button>
          `
        : `
            <p class="support-login-notice">
                <small>
                    Log in as a resident to support
                    this concern.
                </small>
            </p>
          `;

    // Generate link only for valid incident IDs.
    const detailsLink =
        Number.isSafeInteger(id) && id > 0
            ? `
                <p style="margin-top: 12px;">
                    <a
                        href="public_incident_details.php?id=${id}"
                        class="incident-view-details-btn"
                    >
                        View Full Incident Details
                    </a>
                </p>
              `
            : "";

    return `
        <div class="incident-popup">

            <h3>
                ${escapeHtml(incident.title)}
            </h3>

            <p>
                <strong>Category:</strong>
                ${escapeHtml(incident.category)}
            </p>

            <p>
                <strong>Location:</strong>
                ${escapeHtml(
                    incident.location_address
                )}
            </p>

            <p>
                <strong>Status:</strong>

                <span
                    style="
                        color: ${color};
                        font-weight: bold;
                    "
                >
                    ${escapeHtml(
                        incident.processing_status
                    )}
                </span>
            </p>

            <p>
                <strong>Reference:</strong>
                ${escapeHtml(
                    incident.reference_number
                )}
            </p>

            <hr>

            <p>
                <strong>
                    Residents with Same Concern:
                </strong>

                <span id="support-count-${id}">
                    ${support.count}
                </span>
            </p>

            ${supportButton}

            ${detailsLink}

        </div>
    `;

}


// =====================================================
// 8. LOAD APPROVED INCIDENTS
// =====================================================

async function loadApprovedIncidents() {

    try {

        const response = await fetch(
            "api/get_approved_incidents.php",
            {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            }
        );

        if (!response.ok) {

            throw new Error(
                "Unable to load approved incidents."
            );

        }

        const data = await response.json();

        if (
            !data.success ||
            !Array.isArray(data.incidents)
        ) {

            throw new Error(
                "Invalid incident API response."
            );

        }

        // Load support counts and resident eligibility.
        // If support API fails, the map should still load.
        try {

            await loadSupportData();

        } catch (error) {

            console.error(
                "Support data error:",
                error
            );

            supportData.clear();

            canSupport = false;

        }

        // Clear existing markers.
        incidentLayer.clearLayers();

        const bounds = [];
        const heatPoints = [];

        // Process approved incidents.
        data.incidents.forEach(incident => {

            const lat = Number(
                incident.latitude
            );

            const lng = Number(
                incident.longitude
            );

            const id = Number(
                incident.incident_id
            );

            // Ignore invalid coordinates and IDs.
            if (
                !Number.isFinite(lat) ||
                !Number.isFinite(lng) ||
                lat < -90 ||
                lat > 90 ||
                lng < -180 ||
                lng > 180 ||
                (lat === 0 && lng === 0) ||
                !Number.isSafeInteger(id) ||
                id <= 0
            ) {

                return;

            }

            const color = getStatusColor(
                incident.processing_status
            );

            // Add location to heatmap.
            heatPoints.push([
                lat,
                lng,
                1
            ]);

            // Create color-coded incident marker.
            const marker = L.circleMarker(
                [lat, lng],
                {
                    radius: 10,
                    color: "#ffffff",
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 0.95
                }
            );

            // Attach incident details popup.
            marker.bindPopup(
                createIncidentPopup(incident),
                {
                    maxWidth: 320,
                    minWidth: 220
                }
            );

            // Add marker to map.
            marker.addTo(incidentLayer);

            // Save location for automatic map zoom.
            bounds.push([
                lat,
                lng
            ]);

        });


        // =================================================
        // 9. UPDATE HEATMAP
        // =================================================

        heatmapLayer.setLatLngs(
            heatPoints
        );


        // =================================================
        // 10. UPDATE INCIDENT COUNTER
        // =================================================

        // Count only incidents with valid map coordinates.
        const totalIncidentsElement =
            document.getElementById(
                "total-incidents"
            );

        if (totalIncidentsElement) {

            totalIncidentsElement.textContent =
                bounds.length;

        }


        // =================================================
        // 11. AUTOMATIC MAP ZOOM
        // =================================================

        if (bounds.length > 0) {

            map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: 16
            });

        }

    } catch (error) {

        console.error(
            "Community map error:",
            error
        );

        const totalIncidentsElement =
            document.getElementById(
                "total-incidents"
            );

        if (totalIncidentsElement) {

            totalIncidentsElement.textContent = "—";

        }

        alert(
            "Unable to load the Community Issue Map."
        );

    }

}


// =====================================================
// 12. SUPPORT BUTTON CLICK HANDLER
// =====================================================

map.on("popupopen", function(event) {

    const popupElement =
        event.popup.getElement();

    if (!popupElement) {
        return;
    }

    const button = popupElement.querySelector(
        ".incident-support-btn"
    );

    if (!button) {
        return;
    }

    button.addEventListener(
        "click",
        async function() {

            const incidentId = Number(
                button.dataset.incidentId
            );

            if (
                !Number.isSafeInteger(incidentId) ||
                incidentId <= 0
            ) {

                alert("Invalid incident ID.");

                return;

            }

            const csrfToken = document.querySelector(
                'meta[name="support-csrf-token"]'
            )?.content;

            if (!csrfToken) {

                alert(
                    "Security token missing. " +
                    "Please refresh the page."
                );

                return;

            }

            button.disabled = true;
            button.textContent = "Updating...";

            try {

                const formData = new FormData();

                formData.append(
                    "incident_id",
                    String(incidentId)
                );

                formData.append(
                    "csrf_token",
                    csrfToken
                );

                const response = await fetch(
                    "api/toggle_incident_supports.php",
                    {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    }
                );

                const data = await response.json();

                if (
                    !response.ok ||
                    !data.success
                ) {

                    throw new Error(
                        data.message ||
                        "Unable to update support."
                    );

                }

                const updatedSupport = {

                    count: Number(
                        data.support_count
                    ),

                    supported: Boolean(
                        data.supported
                    )

                };

                // Update local support data.
                supportData.set(
                    incidentId,
                    updatedSupport
                );

                // Update support count in popup.
                const countElement =
                    popupElement.querySelector(
                        `#support-count-${incidentId}`
                    );

                if (countElement) {

                    countElement.textContent =
                        updatedSupport.count;

                }

                // Update support button label.
                button.textContent =
                    updatedSupport.supported
                        ? "Remove My Support"
                        : "I Have the Same Concern";

            } catch (error) {

                console.error(
                    "Support update error:",
                    error
                );

                const currentSupport =
                    supportData.get(incidentId);

                button.textContent =
                    currentSupport?.supported
                        ? "Remove My Support"
                        : "I Have the Same Concern";

                alert(
                    error.message ||
                    "Unable to update support."
                );

            } finally {

                button.disabled = false;

            }

        }
    );

});


// =====================================================
// 13. INITIAL LOAD
// =====================================================

loadApprovedIncidents();
