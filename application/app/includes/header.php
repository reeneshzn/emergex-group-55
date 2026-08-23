<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Student accommodation, secured';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EMERGEX secure student accommodation search and booking prototype.">
    <title><?= e($pageTitle) ?> | EMERGEX</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/reports.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="EMERGEX home">
        <img src="<?= e(url('assets/img/logo.svg')) ?>" alt="" width="36" height="36">
        <span>EMERGEX</span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu</button>
    <nav id="main-nav" class="main-nav" aria-label="Main navigation">
        <a href="<?= e(url('properties.php')) ?>">Find a home</a>
        <a href="<?= e(url('how-it-works.php')) ?>">How it works</a>
        <?php if ($user): ?>
            <a href="<?= e(url($user['role'] === 'landlord' ? 'landlord.php' : ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'))) ?>">Dashboard</a>
            <?php if ($user['role'] === 'admin'): ?><a href="<?= e(url('reports.php')) ?>">Reports</a><?php endif; ?>
            <a class="button button-small button-ghost" href="<?= e(url('logout.php')) ?>">Sign out</a>
        <?php else: ?>
            <a href="<?= e(url('login.php')) ?>">Sign in</a>
            <a class="button button-small" href="<?= e(url('register.php')) ?>">Get started</a>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php foreach (pull_flashes() as $notice): ?>
    <div class="flash flash-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
<?php endforeach; ?>
