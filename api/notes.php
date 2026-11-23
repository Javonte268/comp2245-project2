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
$body = trim((string)($payload['body'] ?? ''));

if (!ctype_digit($ticketIdRaw) || (int)$ticketIdRaw <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid ticket ID is required.']);
    exit;
}
if ($body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Note body cannot be empty.']);
    exit;
}

$ticketId = (int)$ticketIdRaw;
$user = current_user();

$checkTicket = $pdo->prepare('SELECT id FROM tickets WHERE id = :id LIMIT 1');
$checkTicket->execute([':id' => $ticketId]);
if (!$checkTicket->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Ticket not found.']);
    exit;
}

$insertStmt = $pdo->prepare(
    'INSERT INTO notes (ticket_id, user_id, body)
     VALUES (:ticket_id, :user_id, :body)'
);
$insertStmt->execute([
    ':ticket_id' => $ticketId,
    ':user_id'   => (int)$user['id'],
    ':body'      => $body,
]);

$noteId = (int)$pdo->lastInsertId();
$noteStmt = $pdo->prepare(
    'SELECT n.id, n.ticket_id, n.body, n.created_at, u.firstname, u.lastname
     FROM notes n
     JOIN users u ON n.user_id = u.id
     WHERE n.id = :id
     LIMIT 1'
);
$noteStmt->execute([':id' => $noteId]);
$note = $noteStmt->fetch();

http_response_code(201);
echo json_encode([
    'note' => [
        'id'         => (int)$note['id'],
        'ticket_id'  => (int)$note['ticket_id'],
        'body'       => (string)$note['body'],
        'created_at' => (string)$note['created_at'],
        'author'     => trim((string)$note['firstname'] . ' ' . (string)$note['lastname']),
    ],
]);