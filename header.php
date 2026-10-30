<?php
$pageTitle = $pageTitle ?? 'Trivia Night';
$navUser = current_user($pdo);
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6fb">
    <meta name="description" content="Create a trivia room and play live with friends from anywhere.">
    <title><?= e($pageTitle) ?> | Trivia Night</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">T</span> Trivia Night</a>
    <nav aria-label="Main navigation">
        <?php if ($navUser): ?>
            <a href="index.php">Dashboard</a>
            <?php if ($navUser['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <form class="nav-form" method="post" action="index.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="logout">
                <button class="nav-button" type="submit">Sign out</button>
            </form>
        <?php else: ?>
            <a href="index.php">Sign in</a>
            <a href="index.php?register=1">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
    <?php if ($flash): ?>
        <div class="notice notice-<?= e($flash['type'] ?? 'info') ?>" role="status"><?= e($flash['message'] ?? '') ?></div>
    <?php endif; ?>
