
<?php
require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function sendResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    sendResponse(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

if (empty($_SESSION["user_id"])) {
    sendResponse(401, [
        "success" => false,
        "message" => "Please log in as a resident."
    ]);
}

$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    empty($_SESSION["support_csrf_token"]) ||
    !hash_equals(
        $_SESSION["support_csrf_token"],
        $csrfToken
    )
) {
    sendResponse(403, [
        "success" => false,
        "message" => "Invalid security token. Refresh the page."
    ]);
}

$incidentId = filter_input(
    INPUT_POST,
    "incident_id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    sendResponse(400, [
        "success" => false,
        "message" => "Invalid incident ID."
    ]);
}

$residentId = (int) $_SESSION["user_id"];

try {
    $userStmt = $pdo->prepare(
        "SELECT user_id
         FROM users
         WHERE user_id = ?
           AND role = 'resident'
           AND account_status = 'active'
         LIMIT 1"
    );

    $userStmt->execute([$residentId]);

    if (!$userStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(403, [
            "success" => false,
            "message" => "Only active residents can support incidents."
        ]);
    }

    $pdo->beginTransaction();

    $incidentStmt = $pdo->prepare(
        "SELECT incident_id, resident_id
         FROM incidents
         WHERE incident_id = ?
           AND map_approval_status = 'Approved'
         FOR UPDATE"
    );

    $incidentStmt->execute([$incidentId]);
    $incident = $incidentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        $pdo->rollBack();

        sendResponse(404, [
            "success" => false,
            "message" => "Approved incident not found."
        ]);
    }

    if ((int) $incident["resident_id"] === $residentId) {
        $pdo->rollBack();

        sendResponse(403, [
            "success" => false,
            "message" => "You cannot support your own incident."
        ]);
    }

    $checkStmt = $pdo->prepare(
        "SELECT support_id
         FROM incident_supports
         WHERE incident_id = ?
           AND resident_id = ?
         LIMIT 1"
    );

    $checkStmt->execute([$incidentId, $residentId]);

    $existingSupport = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSupport) {
        $deleteStmt = $pdo->prepare(
            "DELETE FROM incident_supports
             WHERE incident_id = ?
               AND resident_id = ?"
        );

        $deleteStmt->execute([$incidentId, $residentId]);
        $supported = false;
    } else {
        $insertStmt = $pdo->prepare(
            "INSERT INTO incident_supports
                (incident_id, resident_id)
             VALUES (?, ?)"
        );

        $insertStmt->execute([$incidentId, $residentId]);
        $supported = true;
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM incident_supports
         WHERE incident_id = ?"
    );

    $countStmt->execute([$incidentId]);
    $supportCount = (int) $countStmt->fetchColumn();

    $pdo->commit();

    sendResponse(200, [
        "success" => true,
        "supported" => $supported,
        "support_count" => $supportCount,
        "message" => $supported
            ? "Concern supported successfully."
            : "Support removed successfully."
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Toggle incident support error: " . $e->getMessage());

    sendResponse(500, [
        "success" => false,
        "message" => "Unable to update incident support."
    ]);
}
