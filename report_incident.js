
document.addEventListener("DOMContentLoaded", function () {

    // Get form elements.
    const latitudeInput = document.getElementById("latitude");
    const longitudeInput = document.getElementById("longitude");
    const form = document.getElementById("incident-form");
    const message = document.getElementById("form-message");
    const submitButton = document.getElementById("submit-button");

    // Initialize the Leaflet map.
    // Temporary starting coordinates.
    const map = L.map("incident-map").setView(
        [14.8, 121.0],
        13
    );

    // Load OpenStreetMap tiles.
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

    // Allow residents to select an incident location.
    map.on("click", function (event) {

        // Do not modify the report after successful submission.
        if (submissionSuccessful || isSubmitting) {
            return;
        }

        const latitude = event.latlng.lat;
        const longitude = event.latlng.lng;

        // Remove the previous marker.
        if (incidentMarker !== null) {
            map.removeLayer(incidentMarker);
        }

        // Add a marker at the selected location.
        incidentMarker = L.marker([
            latitude,
            longitude
        ]).addTo(map);

        // Store coordinates in hidden form fields.
        latitudeInput.value = latitude.toFixed(7);
        longitudeInput.value = longitude.toFixed(7);

        message.textContent = "Incident location selected.";
        message.style.color = "green";
    });

    // Handle incident form submission.
    form.addEventListener("submit", async function (event) {

        event.preventDefault();

        // Prevent duplicate submissions.
        if (submissionSuccessful || isSubmitting) {
            return;
        }

        // Require a selected map location.
        if (!latitudeInput.value || !longitudeInput.value) {

            message.textContent =
                "Please select the incident location on the map.";

            message.style.color = "red";

            map.getContainer().scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            return;
        }

        // Mark the request as being processed.
        isSubmitting = true;
        submitButton.disabled = true;

        message.textContent = "Submitting incident report...";
        message.style.color = "#27358B";

        try {

            // Collect all form fields, including optional photo.
            const formData = new FormData(form);

            // Send incident details to PHP backend.
            const response = await fetch(
                "api/submit_incident.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    body: formData
                }
            );

            // Read the JSON response from PHP.
            const result = await response.json();

            console.log("HTTP Status:", response.status);
            console.log("Server Response:", result);

            // Check whether the submission succeeded.
            if (response.ok && result.success) {

                submissionSuccessful = true;

                message.style.color = "green";

                // Display the generated reference number.
                message.textContent =
                    "Incident report submitted successfully! " +
                    "Your reference number is: " +
                    result.reference_number +
                    ". Please save this number for tracking.";

                // Keep the submit button disabled.
                submitButton.disabled = true;

                // Prevent changes to submitted form fields.
                const fields = form.querySelectorAll(
                    "input, select, textarea"
                );

                fields.forEach(function (field) {
                    field.disabled = true;
                });

            } else {

                message.textContent =
                    result.message ||
                    "Unable to submit the incident report.";

                message.style.color = "red";
            }

        } catch (error) {

            message.textContent =
                "Unable to process the request. Please try again.";

            message.style.color = "red";

            console.error("Submission error:", error);

        } finally {

            // Request processing is finished.
            isSubmitting = false;

            // Re-enable the button only if submission failed.
            if (!submissionSuccessful) {
                submitButton.disabled = false;
            }
        }
    });

});
