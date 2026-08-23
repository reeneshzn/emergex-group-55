<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
if ($signedIn = current_user()) { redirect($signedIn['role'] === 'landlord' ? 'landlord.php' : ($signedIn['role'] === 'admin' ? 'admin.php' : 'dashboard.php')); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    enforce_login_throttle();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email address and password.';
    } else {
        $stmt = db()->prepare("SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if (!$account || $account['status'] !== 'active' || !password_verify($password, $account['password_hash'])) {
            register_login_failure();
            $errors[] = 'The email or password is incorrect.';
        } else {
            clear_login_failures();
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            audit('user.login', 'user', (int) $account['id']);
            flash('success', 'Welcome back.');
            $roleStmt = db()->prepare('SELECT role FROM users WHERE id = ?');
            $roleStmt->execute([(int) $account['id']]);
            $role = $roleStmt->fetchColumn();
            redirect($role === 'landlord' ? 'landlord.php' : ($role === 'admin' ? 'admin.php' : 'dashboard.php'));
        }
    }
}
$pageTitle = 'Sign in'; require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="auth-layout"><div class="auth-copy"><p class="eyebrow">Welcome back</p><h1>Your next move starts here.</h1><p>Track applications, deposits and check-in milestones from one place.</p></div><div class="auth-card"><h2>Sign in</h2><?php foreach ($errors as $error): ?><div class="form-error"><?= e($error) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><label for="email">Email address</label><input id="email" type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required><button class="button button-block" type="submit">Sign in securely</button></form><p class="form-foot">New to EMERGEX? <a href="<?= e(url('register.php')) ?>">Create an account</a>.</p></div></section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
