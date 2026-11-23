<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$idRaw = (string)($_GET['id'] ?? '');
if (!ctype_digit($idRaw) || (int)$idRaw <= 0) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}
$ticketId = (int)$idRaw;

$stmt = $pdo->prepare(
    'SELECT t.*,
            c.firstname AS creator_first, c.lastname AS creator_last,
            a.firstname AS assignee_first, a.lastname AS assignee_last
     FROM tickets t
     LEFT JOIN users c ON t.created_by = c.id
     LEFT JOIN users a ON t.assigned_to = a.id
     WHERE t.id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

$notesStmt = $pdo->prepare(
    'SELECT n.id, n.body, n.created_at, u.firstname, u.lastname
     FROM notes n
     JOIN users u ON n.user_id = u.id
     WHERE n.ticket_id = :ticket_id
     ORDER BY n.created_at ASC, n.id ASC'
);
$notesStmt->execute([':ticket_id' => $ticketId]);
$notes = $notesStmt->fetchAll();

$usersStmt = $pdo->query('SELECT id, firstname, lastname FROM users ORDER BY firstname ASC, lastname ASC');
$staffUsers = $usersStmt->fetchAll();

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$pageTitle = 'Ticket #' . $ticketId . ' — Fix-It Desk';
$creatorName = trim((string)($ticket['creator_first'] ?? '') . ' ' . (string)($ticket['creator_last'] ?? ''));
$assigneeName = trim((string)($ticket['assignee_first'] ?? '') . ' ' . (string)($ticket['assignee_last'] ?? ''));
if ($assigneeName === '') {
    $assigneeName = 'Unassigned';
}

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main" data-ticket-id="<?= $ticketId ?>">
    <div class="page-header">
        <div>
            <h1>#<?= $ticketId ?>: <?= e((string)$ticket['title']) ?></h1>
            <p class="page-subtitle">
                Created by <?= e($creatorName) ?> on <?= e((string)$ticket['created_at']) ?>
            </p>
        </div>
        <div class="header-actions">
            <a class="btn btn-secondary" href="ticket-edit.php?id=<?= $ticketId ?>">Edit</a>
            <form method="post" action="ticket-delete.php" class="inline-form" onsubmit="return confirm('Delete this ticket permanently?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $ticketId ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
            <a class="btn btn-ghost" href="index.php">Back</a>
        </div>
    </div>

    <div class="ticket-layout">
        <section class="card">
            <h2>Details</h2>
            <p class="ticket-description"><?= nl2br(e((string)$ticket['description'])) ?></p>

            <div class="meta-grid">
                <div>
                    <span class="meta-label">Status</span>
                    <span id="ticket-status-badge" class="badge status-<?= e((string)$ticket['status']) ?>">
                        <?= e(str_replace('_', ' ', (string)$ticket['status'])) ?>
                    </span>
                </div>
                <div>
                    <span class="meta-label">Priority</span>
                    <span class="badge priority-<?= e((string)$ticket['priority']) ?>">
                        <?= e((string)$ticket['priority']) ?>
                    </span>
                </div>
                <div>
                    <span class="meta-label">Assignee</span>
                    <span id="ticket-assignee-text"><?= e($assigneeName) ?></span>
                </div>
            </div>

            <form id="quick-update-form" class="inline-update-form" data-ticket-id="<?= $ticketId ?>">
                <div class="form-group">
                    <label for="quick-status">Update status</label>
                    <select id="quick-status" name="status">
                        <?php foreach ($allowedStatuses as $st): ?>
                            <option value="<?= e($st) ?>" <?= (string)$ticket['status'] === $st ? 'selected' : '' ?>>
                                <?= e(str_replace('_', ' ', ucfirst($st))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quick-assignee">Assignee</label>
                    <select id="quick-assignee" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($staffUsers as $staff): ?>
                            <?php
                            $sid = (int)$staff['id'];
                            $fullName = trim((string)$staff['firstname'] . ' ' . (string)$staff['lastname']);
                            ?>
                            <option value="<?= $sid ?>" <?= (int)$ticket['assigned_to'] === $sid ? 'selected' : '' ?>>
                                <?= e($fullName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-secondary">Update ticket</button>
                </div>
            </form>
            <div id="ticket-update-feedback" class="feedback-msg" aria-live="polite"></div>
        </section>

        <section class="card">
            <h2>Notes</h2>
            <div id="notes-list" class="notes-list">
                <?php if (count($notes) === 0): ?>
                    <p id="no-notes-msg" class="empty-state">No notes yet.</p>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <?php $author = trim((string)$note['firstname'] . ' ' . (string)$note['lastname']); ?>
                        <article class="note-item">
                            <div class="note-meta">
                                <strong><?= e($author) ?></strong>
                                <span><?= e((string)$note['created_at']) ?></span>
                            </div>
                            <p><?= nl2br(e((string)$note['body'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form id="add-note-form" class="stack-form" data-ticket-id="<?= $ticketId ?>">
                <div class="form-group">
                    <label for="note-body">Add a note</label>
                    <textarea id="note-body" name="body" rows="3" required placeholder="Write an update or troubleshooting note..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Post note</button>
                </div>
            </form>
            <div id="note-feedback" class="feedback-msg" aria-live="polite"></div>
        </section>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';