
<?php

require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    respond(405, [
        "success" => false,
        "message" => "Method not allowed."
    ]);
}

$userId = (int) ($_SESSION["user_id"] ?? 0);

if ($userId <= 0) {
    respond(401, [
        "success" => false,
        "message" => "Authentication required."
    ]);
}

try {
    $userStmt = $pdo->prepare(
        "SELECT role
         FROM users
         WHERE user_id = ?
           AND account_status = 'active'
         LIMIT 1"
    );

    $userStmt->execute([$userId]);
    $role = $userStmt->fetchColumn();

    if (!in_array($role, ["resident", "admin"], true)) {
        respond(403, [
            "success" => false,
            "message" => "Access denied."
        ]);
    }

    $user = requireRole($role);

    $incidentId = filter_input(
        INPUT_GET,
        "incident_id",
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if (!$incidentId) {
        respond(400, [
            "success" => false,
            "message" => "Invalid incident ID."
        ]);
    }

    // Residents can read only their own incident.
    if ($role === "resident") {
        $incidentStmt = $pdo->prepare(
            "SELECT incident_id
             FROM incidents
             WHERE incident_id = ?
               AND resident_id = ?
             LIMIT 1"
        );

        $incidentStmt->execute([
            $incidentId,
            $userId
        ]);
    } else {
        $incidentStmt = $pdo->prepare(
            "SELECT incident_id
             FROM incidents
             WHERE incident_id = ?
             LIMIT 1"
        );

        $incidentStmt->execute([$incidentId]);
    }

    if (!$incidentStmt->fetchColumn()) {
        respond(404, [
            "success" => false,
            "message" => "Incident not found."
        ]);
    }

    $stmt = $pdo->prepare(
        "SELECT
            c.comment_id,
            c.comment_text,
            c.evidence_path,
            c.created_at,
            c.user_id,
            u.first_name,
            u.last_name,
            u.role
         FROM incident_comments c
         INNER JOIN users u
             ON c.user_id = u.user_id
         WHERE c.incident_id = ?
         ORDER BY c.created_at ASC, c.comment_id ASC"
    );

    $stmt->execute([$incidentId]);

    $messages = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $path = $row["evidence_path"];

        $safePath = null;

        if (
            is_string($path) &&
            preg_match(
                '~^uploads/incidents/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~i',
                $path
            )
        ) {
            $safePath = $path;
        }

        $messages[] = [
            "comment_id" => (int) $row["comment_id"],
            "comment_text" => $row["comment_text"],
            "evidence_path" => $safePath,
            "created_at" => $row["created_at"],
            "author" => $row["role"] === "admin"
                ? "Barangay Administrator"
                : $row["first_name"] . " " . $row["last_name"],
            "is_admin" => $row["role"] === "admin",
            "is_mine" => (int) $row["user_id"] === $userId
        ];
    }

    respond(200, [
        "success" => true,
        "messages" => $messages
    ]);

} catch (Throwable $e) {
    error_log(
        "Incident messaging error: " . $e->getMessage()
    );

    respond(500, [
        "success" => false,
        "message" => "Unable to load messages."
    ]);
}
