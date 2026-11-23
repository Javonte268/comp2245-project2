<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Tickets — Fix-It Desk';
$user = current_user();

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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main">
    <div class="page-header">
        <div>
            <h1>Tickets</h1>
            <p class="page-subtitle">Browse and filter help desk tickets.</p>
        </div>
        <a class="btn btn-primary" href="ticket-new.php">New ticket</a>
    </div>

    <div class="card filter-card">
        <form id="ticket-filter-form" method="get" action="index.php" class="filter-form">
            <div class="form-group">
                <label for="filter-status">Status</label>
                <select id="filter-status" name="status">
                    <option value="">All statuses</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <div class="form-group">
                <label for="filter-priority">Priority</label>
                <select id="filter-priority" name="priority">
                    <option value="">All priorities</option>
                    <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
            </div>

            <div class="form-group">
                <label for="filter-q">Search</label>
                <input type="search" id="filter-q" name="q" value="<?= e($searchFilter) ?>" placeholder="Search title or details...">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a class="btn btn-ghost" href="index.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div id="ticket-list-container">
            <?php if (count($tickets) === 0): ?>
                <p class="empty-state">No tickets match the current filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assignee</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                $assigneeName = trim((string)($ticket['assignee_first'] ?? '') . ' ' . (string)($ticket['assignee_last'] ?? ''));
                                if ($assigneeName === '') {
                                    $assigneeName = 'Unassigned';
                                }
                                ?>
                                <tr>
                                    <td>#<?= (int)$ticket['id'] ?></td>
                                    <td><a href="ticket.php?id=<?= (int)$ticket['id'] ?>"><?= e((string)$ticket['title']) ?></a></td>
                                    <td><span class="badge status-<?= e((string)$ticket['status']) ?>"><?= e(str_replace('_', ' ', (string)$ticket['status'])) ?></span></td>
                                    <td><span class="badge priority-<?= e((string)$ticket['priority']) ?>"><?= e((string)$ticket['priority']) ?></span></td>
                                    <td><?= e($assigneeName) ?></td>
                                    <td><?= e((string)$ticket['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';