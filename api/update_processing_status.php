
<?php
// REPLACE FILE: api/update_processing_status.php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/email_notifications.php';

$admin = requireRole('admin');

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['admin_csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token.');
}

// Validate incident ID.
$incidentId = filter_input(
    INPUT_POST,
    'incident_id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$incidentId) {
    http_response_code(422);
    exit('Invalid incident ID.');
}

// Validate processing status.
$status = $_POST['processing_status'] ?? '';

$allowedStatuses = [
    'Pending',
    'In Progress',
    'Resolved'
];

if (
    !is_string($status) ||
    !in_array($status, $allowedStatuses, true)
) {
    http_response_code(422);
    exit('Invalid processing status.');
}

try {

    $pdo->beginTransaction();

    // Lock the incident during status update.
    // The resident's registered email is stored
    // in the users.email column.
    $stmt = $pdo->prepare(
        'SELECT
            i.processing_status,
            i.reference_number,
            i.title,
            u.email
         FROM incidents i
         INNER JOIN users u
             ON u.user_id = i.resident_id
         WHERE i.incident_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$incidentId]);

    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {

        $pdo->rollBack();

        http_response_code(404);
        exit('Incident not found.');
    }

    // Avoid sending duplicate status notifications.
    $changed =
        $incident['processing_status'] !== $status;

    if ($changed) {

        $update = $pdo->prepare(
            'UPDATE incidents
             SET processing_status = ?
             WHERE incident_id = ?'
        );

        $update->execute([
            $status,
            $incidentId
        ]);
    }

    // Save the status update before sending email.
    $pdo->commit();

    // Email failure must not undo a valid update.
    if ($changed) {

        $subject =
            'Incident Status Update - ' .
            $incident['reference_number'];

        $body =
            "Barangay Chrysanthemum Portal\n\n" .
            "Your incident report status has been updated.\n\n" .
            "Reference: {$incident['reference_number']}\n" .
            "Incident: {$incident['title']}\n" .
            "New status: {$status}\n\n" .
            "Please log in to the portal for more details.\n\n" .
            "This is an automated notification. Do not reply.";

        sendPortalEmail(
            (string) $incident['email'],
            $subject,
            $body
        );
    }

    // Return to admin incident details.
    header(
        'Location: ../admin_incident_details.php?id=' .
        $incidentId,
        true,
        303
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Processing status update failed: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit('Unable to update processing status.');
}
