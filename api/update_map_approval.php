
<?php

require_once __DIR__ . "/../config/auth.php";

// Only authenticated administrators can update approvals.
$admin = requireRole("admin");

// Accept POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Method not allowed.");
}

// Validate CSRF token.
$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["admin_csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid security token.");
}

// Validate incident ID.
$incidentId = filter_input(
    INPUT_POST,
    "incident_id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    http_response_code(422);
    exit("Invalid incident ID.");
}

// Validate approval decision.
$decision = $_POST["decision"] ?? "";

if (
    !is_string($decision) ||
    !in_array($decision, ["Approved", "Rejected"], true)
) {
    http_response_code(422);
    exit("Invalid approval decision.");
}

try {

    // Update only an existing incident.
    $stmt = $pdo->prepare(
        "UPDATE incidents
         SET map_approval_status = ?
         WHERE incident_id = ?"
    );

    $stmt->execute([
        $decision,
        $incidentId
    ]);

    // Check whether the incident exists.
    // A zero row count can also mean the status was unchanged.
    $check = $pdo->prepare(
        "SELECT incident_id
         FROM incidents
         WHERE incident_id = ?
         LIMIT 1"
    );

    $check->execute([$incidentId]);

    if (!$check->fetchColumn()) {
        http_response_code(404);
        exit("Incident not found.");
    }

    // Redirect back to the incident details page.
    header(
        "Location: ../admin_incident_details.php?id=" .
        $incidentId
    );

    exit;

} catch (PDOException $e) {

    error_log(
        "Map approval update failed: " . $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to update map approval.");
}
