
<?php

require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// This is a public read-only endpoint.
// No resident or admin login is required.

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            incident_id,
            reference_number,
            category,
            title,
            location_address,
            latitude,
            longitude,
            processing_status,
            created_at
         FROM incidents
         WHERE map_approval_status = 'Approved'
         ORDER BY created_at DESC"
    );

    $stmt->execute();

    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "incidents" => $incidents
    ]);

} catch (PDOException $e) {

    error_log(
        "Approved incidents API error: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load approved incidents."
    ]);
}
