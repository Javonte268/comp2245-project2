<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login(true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$priorityFilter = trim((string)($_GET['priority'] ?? ''));
$searchFilter = trim((string)($_GET['q'] ?? ''));

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$allowedPriorities = ['low', 'medium', 'high', 'urgent'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
if (!in_array($priorityFilter, $allowedPriorities, true)) {
    $priorityFilter = '';
}

$sql = 'SELECT t.id, t.title, t.status, t.priority, t.created_at,
               creator.firstname AS creator_first, creator.lastname AS creator_last,
               assignee.firstname AS assignee_first, assignee.lastname AS assignee_last
        FROM tickets t
        LEFT JOIN users creator ON t.created_by = creator.id
        LEFT JOIN users assignee ON t.assigned_to = assignee.id
        WHERE 1=1';
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND t.status = :status';
    $params[':status'] = $statusFilter;
}
if ($priorityFilter !== '') {
    $sql .= ' AND t.priority = :priority';
    $params[':priority'] = $priorityFilter;
}
if ($searchFilter !== '') {
    $sql .= ' AND (t.title LIKE :q OR t.description LIKE :q)';
    $params[':q'] = '%' . $searchFilter . '%';
}

$sql .= ' ORDER BY t.created_at DESC, t.id DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $tickets = array_map(static function (array $row): array {
        $assigneeName = trim((string)($row['assignee_first'] ?? '') . ' ' . (string)($row['assignee_last'] ?? ''));
        $creatorName = trim((string)($row['creator_first'] ?? '') . ' ' . (string)($row['creator_last'] ?? ''));

        return [
            'id'           => (int)$row['id'],
            'title'        => (string)$row['title'],
            'status'       => (string)$row['status'],
            'priority'     => (string)$row['priority'],
            'created_at'   => (string)$row['created_at'],
            'created_by'   => $creatorName,
            'assigned_to'  => $assigneeName !== '' ? $assigneeName : null,
        ];
    }, $rows);

    echo json_encode([
        'count'   => count($tickets),
        'tickets' => $tickets,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load tickets.']);
}