<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$rawId = $_GET['id'] ?? $_POST['id'] ?? '';
if (!is_string($rawId) && !is_int($rawId)) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}
$idStr = (string)$rawId;
if (!ctype_digit($idStr) || (int)$idStr <= 0) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}
$ticketId = (int)$idStr;

$ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
$ticketStmt->execute([':id' => $ticketId]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}

$pageTitle = 'Edit Ticket #' . $ticketId . ' — Fix-It Desk';
$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
$allowedPriorities = ['low', 'medium', 'high', 'urgent'];

$usersStmt = $pdo->query('SELECT id, firstname, lastname, role FROM users ORDER BY firstname ASC, lastname ASC');
$staffUsers = $usersStmt->fetchAll();
$validAssigneeIds = array_map(static fn(array $u): int => (int)$u['id'], $staffUsers);

$errors = [];
$title = (string)$ticket['title'];
$description = (string)$ticket['description'];
$status = (string)$ticket['status'];
$priority = (string)$ticket['priority'];
$assignedTo = $ticket['assigned_to'] !== null ? (string)$ticket['assigned_to'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? ''));
        $assignedRaw = trim((string)($_POST['assigned_to'] ?? ''));

        if ($title === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($title) > 160) {
            $errors[] = 'Title must be 160 characters or fewer.';
        }

        if ($description === '') {
            $errors[] = 'Description is required.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = 'Please select a valid status.';
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            $errors[] = 'Please select a valid priority.';
        }

        $assigneeId = null;
        if ($assignedRaw !== '') {
            if (!ctype_digit($assignedRaw) || !in_array((int)$assignedRaw, $validAssigneeIds, true)) {
                $errors[] = 'Selected assignee is not valid.';
            } else {
                $assigneeId = (int)$assignedRaw;
            }
        }
        $assignedTo = $assignedRaw;

        if (count($errors) === 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE tickets
                 SET title = :title,
                     description = :description,
                     status = :status,
                     priority = :priority,
                     assigned_to = :assigned_to
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':title'       => $title,
                ':description' => $description,
                ':status'      => $status,
                ':priority'    => $priority,
                ':assigned_to' => $assigneeId,
                ':id'          => $ticketId,
            ]);

            header('Location: ticket.php?id=' . $ticketId);
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main">
    <div class="page-header">
        <div>
            <h1>Edit Ticket #<?= $ticketId ?></h1>
            <p class="page-subtitle">Update ticket details, status, priority, or assignment.</p>
        </div>
        <a class="btn btn-ghost" href="ticket.php?id=<?= $ticketId ?>">Back to ticket</a>
    </div>

    <div class="card">
        <?php if (count($errors) > 0): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="ticket-edit.php?id=<?= $ticketId ?>" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $ticketId ?>">

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" maxlength="160" value="<?= e($title) ?>" required>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <?php foreach ($allowedPriorities as $p): ?>
                        <option value="<?= e($p) ?>" <?= $priority === $p ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="assigned_to">Assign to</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staffUsers as $staff): ?>
                        <?php
                        $sid = (string)$staff['id'];
                        $fullName = trim((string)$staff['firstname'] . ' ' . (string)$staff['lastname']);
                        ?>
                        <option value="<?= e($sid) ?>" <?= $assignedTo === $sid ? 'selected' : '' ?>><?= e($fullName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6" required><?= e($description) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a class="btn btn-ghost" href="ticket.php?id=<?= $ticketId ?>">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';