
document.addEventListener("DOMContentLoaded", function () {

    const latitudeInput = document.getElementById("latitude");
    const longitudeInput = document.getElementById("longitude");
    const addressInput = document.getElementById("location_address");

    const form = document.getElementById("incident-form");
    const message = document.getElementById("form-message");
    const submitButton = document.getElementById("submit-button");

    const myLocationButton = document.getElementById(
        "my-location-button"
    );

    const searchButton = document.getElementById(
        "search-location-button"
    );

    const locationStatus = document.getElementById(
        "location-status"
    );

    const suggestions = document.getElementById(
        "address-suggestions"
    );

    const map = L.map("incident-map").setView(
        [14.8, 121.0],
        13
    );

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }
    ).addTo(map);

    let incidentMarker = null;
    let submissionSuccessful = false;
    let isSubmitting = false;
    let isSearching = false;

    // Keep track of changes while an address search is running.
    let addressVersion = 0;
    let lastSearchTime = 0;

    function setLocationStatus(text, color = "#64748B") {
        locationStatus.textContent = text;
        locationStatus.style.color = color;
    }

    function hideSuggestions() {
        suggestions.replaceChildren();
        suggestions.hidden = true;
    }

    function setMarker(latitude, longitude, zoom = 17) {

        if (submissionSuccessful || isSubmitting) {
            return;
        }

        const lat = Number(latitude);
        const lng = Number(longitude);

        if (
            !Number.isFinite(lat) ||
            !Number.isFinite(lng) ||
            lat < -90 ||
            lat > 90 ||
            lng < -180 ||
            lng > 180
        ) {
            return;
        }

        if (incidentMarker === null) {

            incidentMarker = L.marker(
                [lat, lng],
                { draggable: true }
            ).addTo(map);

            incidentMarker.on("dragend", function () {

                if (submissionSuccessful || isSubmitting) {
                    return;
                }

                const position = incidentMarker.getLatLng();

                latitudeInput.value = position.lat.toFixed(7);
                longitudeInput.value = position.lng.toFixed(7);

                setLocationStatus(
                    "Pin adjusted. Please confirm the written address.",
                    "#27358B"
                );
            });

        } else {
            incidentMarker.setLatLng([lat, lng]);
        }

        latitudeInput.value = lat.toFixed(7);
        longitudeInput.value = lng.toFixed(7);

        map.setView([lat, lng], zoom);
    }

    // Select a location directly from the map.
    map.on("click", function (event) {

        if (submissionSuccessful || isSubmitting) {
            return;
        }

        setMarker(
            event.latlng.lat,
            event.latlng.lng,
            map.getZoom()
        );

        setLocationStatus(
            "Location selected. Confirm the written address.",
            "#15803D"
        );
    });

    // Detect the resident's current device location.
    myLocationButton.addEventListener("click", function () {

        if (submissionSuccessful || isSubmitting) {
            return;
        }

        if (!navigator.geolocation) {

            setLocationStatus(
                "Your browser does not support location detection.",
                "#B91C1C"
            );

            return;
        }

        myLocationButton.disabled = true;

        setLocationStatus(
            "Requesting location permission..."
        );

        navigator.geolocation.getCurrentPosition(
            function (position) {

                myLocationButton.disabled = false;

                if (submissionSuccessful || isSubmitting) {
                    return;
                }

                setMarker(
                    position.coords.latitude,
                    position.coords.longitude,
                    17
                );

                hideSuggestions();

                setLocationStatus(
                    "Current location detected. Move the pin if the incident is elsewhere.",
                    "#15803D"
                );
            },

            function (error) {

                myLocationButton.disabled = false;

                let text = "Unable to detect your location.";

                if (error.code === 1) {
                    text = "Location permission denied. Allow location access in your browser.";
                } else if (error.code === 2) {
                    text = "Location is unavailable. Please select the location manually.";
                } else if (error.code === 3) {
                    text = "Location request timed out. Please try again.";
                }

                setLocationStatus(text, "#B91C1C");
            },

            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });

    // Search for an address using Nominatim.
    async function searchAddress() {

        if (
            submissionSuccessful ||
            isSubmitting ||
            isSearching
        ) {
            return;
        }

        const query = addressInput.value.trim();

        if (query.length < 3) {

            setLocationStatus(
                "Please enter a more complete address or landmark.",
                "#B91C1C"
            );

            return;
        }

        const now = Date.now();

        // Avoid repeated searches too quickly.
        if (now - lastSearchTime < 1200) {
            setLocationStatus(
                "Please wait a moment before searching again."
            );
            return;
        }

        lastSearchTime = now;
        isSearching = true;
        searchButton.disabled = true;

        const currentVersion = addressVersion;

        hideSuggestions();

        setLocationStatus("Searching for matching locations...");

        try {

            const params = new URLSearchParams({
                q: query,
                format: "jsonv2",
                limit: "5",
                countrycodes: "ph"
            });

            const response = await fetch(
                "https://nominatim.openstreetmap.org/search?" +
                params.toString(),
                {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

            if (!response.ok) {
                throw new Error("Geocoding service unavailable.");
            }

            const results = await response.json();

            // Ignore results for an address that has changed.
            if (
                currentVersion !== addressVersion ||
                submissionSuccessful
            ) {
                return;
            }

            if (!Array.isArray(results) || results.length === 0) {

                setLocationStatus(
                    "No matching location found. Try adding the city or barangay.",
                    "#B91C1C"
                );

                return;
            }

            results.forEach(function (place) {

                const button = document.createElement("button");

                button.type = "button";
                button.className = "address-suggestion";

                button.textContent = place.display_name;

                button.addEventListener("click", function () {

                    if (submissionSuccessful || isSubmitting) {
                        return;
                    }

                    addressInput.value = place.display_name;

                    addressVersion++;

                    setMarker(
                        place.lat,
                        place.lon,
                        17
                    );

                    hideSuggestions();

                    setLocationStatus(
                        "Address found. Verify the pin location before submitting.",
                        "#15803D"
                    );
                });

                suggestions.appendChild(button);
            });

            suggestions.hidden = false;

            setLocationStatus(
                "Select the correct address from the results."
            );

        } catch (error) {

            console.error("Address search error:", error);

            setLocationStatus(
                "Address search is temporarily unavailable. You can still place a pin manually.",
                "#B91C1C"
            );

        } finally {

            isSearching = false;
            searchButton.disabled = false;
        }
    }

    searchButton.addEventListener("click", searchAddress);

    addressInput.addEventListener("keydown", function (event) {

        if (event.key === "Enter") {
            event.preventDefault();
            searchAddress();
        }
    });

    addressInput.addEventListener("input", function () {

        addressVersion++;

        hideSuggestions();

        // An edited address may no longer match the old pin.
        // Clear the previous coordinates until the user selects again.
        if (!submissionSuccessful && !isSubmitting) {

            latitudeInput.value = "";
            longitudeInput.value = "";

            if (incidentMarker !== null) {
                map.removeLayer(incidentMarker);
                incidentMarker = null;
            }

            setLocationStatus(
                "Search this address or select its location on the map."
            );
        }
    });

    // Handle incident submission.
    form.addEventListener("submit", async function (event) {

        event.preventDefault();

        if (submissionSuccessful || isSubmitting) {
            return;
        }

        if (!latitudeInput.value || !longitudeInput.value) {

            message.textContent =
                "Please select the incident location on the map.";

            message.style.color = "#B91C1C";

            map.getContainer().scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            return;
        }

        isSubmitting = true;
        submitButton.disabled = true;

        if (incidentMarker) {
            incidentMarker.dragging.disable();
        }

        message.textContent = "Submitting incident report...";
        message.style.color = "#27358B";

        try {

            const formData = new FormData(form);

            const response = await fetch(
                "api/submit_incident.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    body: formData
                }
            );

            const result = await response.json();

            console.log("HTTP Status:", response.status);
            console.log("Server Response:", result);

            if (response.ok && result.success) {

                submissionSuccessful = true;

                message.style.color = "#15803D";

                message.textContent =
                    "Incident report submitted successfully! " +
                    "Your reference number is: " +
                    result.reference_number +
                    ". Please save this number for tracking.";

                submitButton.disabled = true;
                myLocationButton.disabled = true;
                searchButton.disabled = true;

                hideSuggestions();

                form.querySelectorAll(
                    "input, select, textarea"
                ).forEach(function (field) {
                    field.disabled = true;
                });

            } else {

                message.textContent =
                    result.message ||
                    "Unable to submit the incident report.";

                message.style.color = "#B91C1C";
            }

        } catch (error) {

            console.error("Submission error:", error);

            message.textContent =
                "Unable to process the request. Please try again.";

            message.style.color = "#B91C1C";

        } finally {

            isSubmitting = false;

            if (!submissionSuccessful) {

                submitButton.disabled = false;

                if (incidentMarker) {
                    incidentMarker.dragging.enable();
                }
            }
        }
    });

});
