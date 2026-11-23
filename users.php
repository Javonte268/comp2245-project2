<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden: Admin access required.');
}

$pageTitle = 'Users — Fix-It Desk';
$errors = [];
$successMessage = '';
$firstname = '';
$lastname = '';
$email = '';
$role = 'member';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        $errors[] = 'Invalid CSRF token.';
    } else {
        $firstname = trim((string)($_POST['firstname'] ?? ''));
        $lastname = trim((string)($_POST['lastname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = trim((string)($_POST['role'] ?? 'member'));

        if ($firstname === '' || $lastname === '') {
            $errors[] = 'First name and last name are required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!in_array($role, ['admin', 'member'], true)) {
            $errors[] = 'Role must be admin or member.';
        }

        if (count($errors) === 0) {
            $dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $dupStmt->execute([':email' => $email]);
            if ($dupStmt->fetch()) {
                $errors[] = 'That email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insStmt = $pdo->prepare(
                    'INSERT INTO users (firstname, lastname, email, password, role)
                     VALUES (:firstname, :lastname, :email, :password, :role)'
                );
                $insStmt->execute([
                    ':firstname' => $firstname,
                    ':lastname'  => $lastname,
                    ':email'     => $email,
                    ':password'  => $hash,
                    ':role'      => $role,
                ]);
                $successMessage = 'User account created.';
                $firstname = '';
                $lastname = '';
                $email = '';
                $role = 'member';
            }
        }
    }
}

$usersStmt = $pdo->query('SELECT id, firstname, lastname, email, role FROM users ORDER BY id ASC');
$allUsers = $usersStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main">
    <div class="page-header">
        <div>
            <h1>Users</h1>
            <p class="page-subtitle">Manage Fix-It Desk user accounts.</p>
        </div>
    </div>

    <div class="ticket-layout">
        <section class="card">
            <h2>Add User</h2>
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($successMessage) ?></div>
            <?php endif; ?>
            <?php if (count($errors) > 0): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="users.php" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="firstname">First name</label>
                    <input type="text" id="firstname" name="firstname" value="<?= e($firstname) ?>" required>
                </div>

                <div class="form-group">
                    <label for="lastname">Last name</label>
                    <input type="text" id="lastname" name="lastname" value="<?= e($lastname) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create user</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Team Members</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td>#<?= (int)$u['id'] ?></td>
                                <td><?= e(trim((string)$u['firstname'] . ' ' . (string)$u['lastname'])) ?></td>
                                <td><?= e((string)$u['email']) ?></td>
                                <td><span class="badge"><?= e((string)$u['role']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';