<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!verify_csrf(is_string($token) ? $token : null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$idRaw = (string)($_POST['id'] ?? '');
if (!ctype_digit($idRaw) || (int)$idRaw <= 0) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}
$ticketId = (int)$idRaw;

$stmt = $pdo->prepare('DELETE FROM tickets WHERE id = :id');
$stmt->execute([':id' => $ticketId]);

header('Location: index.php');
exit;