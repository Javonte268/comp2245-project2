<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode((string)$rawInput, true);
$payload = is_array($jsonBody) ? $jsonBody : $_POST;

$token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$ticketIdRaw = (string)($payload['ticket_id'] ?? '');
if (!ctype_digit($ticketIdRaw) || (int)$ticketIdRaw <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid ticket ID is required.']);
    exit;
}
$ticketId = (int)$ticketIdRaw;

$existingStmt = $pdo->prepare('SELECT id, status, assigned_to FROM tickets WHERE id = :id LIMIT 1');
$existingStmt->execute([':id' => $ticketId]);
$existing = $existingStmt->fetch();

if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Ticket not found.']);
    exit;
}

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$newStatus = array_key_exists('status', $payload) ? trim((string)$payload['status']) : (string)$existing['status'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid status value.']);
    exit;
}

$newAssigneeId = $existing['assigned_to'] !== null ? (int)$existing['assigned_to'] : null;
if (array_key_exists('assigned_to', $payload)) {
    $rawAssignee = trim((string)$payload['assigned_to']);
    if ($rawAssignee === '') {
        $newAssigneeId = null;
    } elseif (ctype_digit($rawAssignee) && (int)$rawAssignee > 0) {
        $checkUser = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $checkUser->execute([':id' => (int)$rawAssignee]);
        if (!$checkUser->fetch()) {
            http_response_code(422);
            echo json_encode(['error' => 'Assignee user not found.']);
            exit;
        }
        $newAssigneeId = (int)$rawAssignee;
    } else {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid assignee ID.']);
        exit;
    }
}

$updateStmt = $pdo->prepare(
    'UPDATE tickets
     SET status = :status, assigned_to = :assigned_to
     WHERE id = :id'
);
$updateStmt->execute([
    ':status'      => $newStatus,
    ':assigned_to' => $newAssigneeId,
    ':id'          => $ticketId,
]);

$assigneeName = 'Unassigned';
if ($newAssigneeId !== null) {
    $uStmt = $pdo->prepare('SELECT firstname, lastname FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute([':id' => $newAssigneeId]);
    $uRow = $uStmt->fetch();
    if ($uRow) {
        $assigneeName = trim((string)$uRow['firstname'] . ' ' . (string)$uRow['lastname']);
    }
}

echo json_encode([
    'ticket' => [
        'id'            => $ticketId,
        'status'        => $newStatus,
        'assigned_to'   => $newAssigneeId,
        'assignee_name' => $assigneeName,
    ],
]);