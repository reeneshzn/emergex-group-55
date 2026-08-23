<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/includes/bootstrap.php';
if ($signedIn = current_user()) { redirect($signedIn['role'] === 'landlord' ? 'landlord.php' : ($signedIn['role'] === 'admin' ? 'admin.php' : 'dashboard.php')); }
$defaultRole = ($_GET['role'] ?? '') === 'landlord' ? 'landlord' : 'student';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $role = in_array($_POST['role'] ?? '', ['student', 'landlord'], true) ? $_POST['role'] : 'student';
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $studentNumber = trim((string) ($_POST['student_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($firstName === '' || $lastName === '') $errors[] = 'Enter your first and last name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+ ()-]{9,20}$/', $phone)) $errors[] = 'Enter a valid contact number.';
    if ($role === 'student' && $studentNumber === '') $errors[] = 'Enter your student number.';
    if ($message = validate_password($password)) $errors[] = $message;
    if (!isset($_POST['terms'])) $errors[] = 'Confirm that you accept the prototype terms and privacy notice.';
    if (!$errors) {
        try {
            $stmt = db()->prepare('INSERT INTO users (role, first_name, last_name, email, phone, student_number, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, "active", NOW())');
            $stmt->execute([$role, $firstName, $lastName, $email, $phone, $role === 'student' ? $studentNumber : null, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) db()->lastInsertId();
            session_regenerate_id(true); $_SESSION['user_id'] = $id; audit('user.register', 'user', $id, ['role' => $role]);
            flash('success', 'Your account has been created.');
            redirect($role === 'landlord' ? 'landlord.php' : 'dashboard.php');
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'An account already uses that email address.' : 'Registration could not be completed.';
        }
    }
    $defaultRole = $role;
}
$pageTitle = 'Create account'; require dirname(__DIR__) . '/app/includes/header.php';
?>
<section class="auth-layout"><div class="auth-copy"><p class="eyebrow">Join EMERGEX</p><h1>One account. A clearer accommodation journey.</h1><p>Students can search and book; landlords can publish and manage verified homes.</p></div><div class="auth-card wide"><h2>Create your account</h2><?php foreach ($errors as $error): ?><div class="form-error"><?= e($error) ?></div><?php endforeach; ?><form method="post" class="form-grid"><?= csrf_field() ?><fieldset class="role-choice"><legend>I am joining as</legend><label><input type="radio" name="role" value="student" <?= $defaultRole === 'student' ? 'checked' : '' ?>> Student</label><label><input type="radio" name="role" value="landlord" <?= $defaultRole === 'landlord' ? 'checked' : '' ?>> Landlord</label></fieldset><label>First name<input name="first_name" required value="<?= e($_POST['first_name'] ?? '') ?>"></label><label>Last name<input name="last_name" required value="<?= e($_POST['last_name'] ?? '') ?>"></label><label>Email address<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label><label>Contact number<input name="phone" required value="<?= e($_POST['phone'] ?? '') ?>"></label><label class="student-only">Student number<input name="student_number" value="<?= e($_POST['student_number'] ?? '') ?>"></label><label>Password<input type="password" name="password" required aria-describedby="password-help"><small id="password-help">10+ characters with upper, lower and number.</small></label><label class="check full"><input type="checkbox" name="terms" value="1" required> I accept the prototype terms and privacy notice.</label><button class="button button-block full" type="submit">Create account</button></form></div></section>
<?php require dirname(__DIR__) . '/app/includes/footer.php'; ?>
