<?php
declare(strict_types=1);

$activePage = basename($_SERVER['PHP_SELF'] ?? '');
$navUser = current_user();
$hideChrome = !empty($hideChrome);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Fix-It Desk') ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php if (!$hideChrome && $navUser !== null): ?>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand-and-nav">
            <a class="brand-logo" href="index.php">Fix-It Desk</a>
            <nav class="nav-links" aria-label="Primary navigation">
                <a class="nav-link <?= $activePage === 'index.php' ? 'active' : '' ?>" href="index.php">Tickets</a>
                <a class="nav-link <?= $activePage === 'ticket-new.php' ? 'active' : '' ?>" href="ticket-new.php">New ticket</a>
                <?php if (($navUser['role'] ?? '') === 'admin'): ?>
                    <a class="nav-link <?= $activePage === 'users.php' ? 'active' : '' ?>" href="users.php">Users</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="header-user-actions">
            <span class="current-user-name"><?= e(trim(($navUser['firstname'] ?? '') . ' ' . ($navUser['lastname'] ?? ''))) ?></span>
            <form class="logout-form" method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn-logout">Log out</button>
            </form>
        </div>
    </div>
</header>
<?php endif; ?>
