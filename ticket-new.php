<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'New Ticket — Fix-It Desk';
$user = current_user();

$allowedPriorities = ['low', 'medium', 'high', 'urgent'];

$usersStmt = $pdo->query('SELECT id, firstname, lastname, role FROM users ORDER BY firstname ASC, lastname ASC');
$staffUsers = $usersStmt->fetchAll();
$validAssigneeIds = array_map(static fn(array $u): int => (int)$u['id'], $staffUsers);

$errors = [];
$title = '';
$description = '';
$priority = 'medium';
$assignedTo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priority = trim((string)($_POST['priority'] ?? 'medium'));
        $assignedRaw = trim((string)($_POST['assigned_to'] ?? ''));

        if ($title === '') {
            $errors[] = 'Title is required.';
        } elseif (mb_strlen($title) > 160) {
            $errors[] = 'Title must be 160 characters or fewer.';
        }

        if ($description === '') {
            $errors[] = 'Description is required.';
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
            $stmt = $pdo->prepare(
                'INSERT INTO tickets (title, description, status, priority, created_by, assigned_to)
                 VALUES (:title, :description, :status, :priority, :created_by, :assigned_to)'
            );
            $stmt->execute([
                ':title'       => $title,
                ':description' => $description,
                ':status'      => 'open',
                ':priority'    => $priority,
                ':created_by'  => (int)$user['id'],
                ':assigned_to' => $assigneeId,
            ]);

            $newId = (int)$pdo->lastInsertId();
            header('Location: ticket.php?id=' . $newId);
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main">
    <div class="page-header">
        <div>
            <h1>New Ticket</h1>
            <p class="page-subtitle">Submit a new issue to the help desk queue.</p>
        </div>
        <a class="btn btn-ghost" href="index.php">Back to tickets</a>
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

        <form method="post" action="ticket-new.php" class="stack-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" maxlength="160" value="<?= e($title) ?>" required>
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
                <button type="submit" class="btn btn-primary">Create ticket</button>
                <a class="btn btn-ghost" href="index.php">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';