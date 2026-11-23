<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Log in — Fix-It Desk';
$hideChrome = true;
$errorMessage = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        $errorMessage = 'Invalid request token. Please try again.';
    } else {
        $emailInput = trim((string)($_POST['email'] ?? ''));
        $passwordInput = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT id, firstname, lastname, email, password, role
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $emailInput]);
        $account = $stmt->fetch();

        if ($account && password_verify($passwordInput, (string)$account['password'])) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['user'] = [
                'id'        => (int)$account['id'],
                'firstname' => (string)$account['firstname'],
                'lastname'  => (string)$account['lastname'],
                'email'     => (string)$account['email'],
                'role'      => (string)$account['role'],
            ];
            header('Location: index.php');
            exit;
        }

        $errorMessage = "That email and password don't match an account.";
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<main class="container page-main">
    <div class="login-wrapper">
        <div class="login-brand-header">
            <h1 class="login-brand-title">Fix-It Desk</h1>
            <p class="login-brand-subtitle">Log in to see and update help desk tickets.</p>
        </div>

        <div class="card">
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($emailInput) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Log in</button>
                </div>
            </form>
        </div>
    </div>
</main>
<?php
require_once __DIR__ . '/includes/footer.php';
