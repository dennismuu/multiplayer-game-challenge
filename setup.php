<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0) {
    http_response_code(403);
    $pageTitle = 'Setup complete';
    require __DIR__ . '/header.php';
    ?>
    <section class="card narrow-card">
        <p class="eyebrow">ALREADY SET UP</p>
        <h1>Admin setup is closed</h1>
        <p class="muted">An admin account already exists. Sign in to continue.</p>
        <a class="button button-primary" href="index.php">Go to sign in</a>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

if (SETUP_KEY === '') {
    http_response_code(503);
    exit('Initial admin setup is disabled. Configure TRIVIA_SETUP_KEY before deploying.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $providedSetupKey = form_string('setup_key');
    if (!hash_equals(SETUP_KEY, $providedSetupKey)) {
        set_flash('The setup key is not correct.', 'error');
        redirect('setup.php');
    }

    $username = trim(form_string('username'));
    $email = strtolower(trim(form_string('email')));
    $password = form_string('password');

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        set_flash('Choose a username with 3-30 letters, numbers, or underscores.', 'error');
        redirect('setup.php');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        set_flash('Enter a valid email address.', 'error');
        redirect('setup.php');
    }
    if (!password_is_valid($password)) {
        set_flash('Use at least 6 characters and include at least one letter and one number.', 'error');
        redirect('setup.php');
    }

    $pdo->beginTransaction();
    try {
        if ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0) {
            $pdo->rollBack();
            set_flash('Setup has already been completed.', 'error');
            redirect('index.php');
        }
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, "admin")');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $adminId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
        set_flash('That username or email is already registered.', 'error');
        redirect('setup.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $adminId;
    set_flash('Your admin account is ready.');
    redirect('index.php');
}

$pageTitle = 'Create admin account';
require __DIR__ . '/header.php';
?>
<section class="card narrow-card">
    <p class="eyebrow">ONE-TIME SETUP</p>
    <h1>Create the first admin</h1>
    <p class="muted">Enter the private setup key configured by the site owner. This page closes after the first admin account is created.</p>
    <form method="post" class="stack">
        <?php csrf_field(); ?>
        <label>Setup key
            <input name="setup_key" type="password" required autocomplete="off">
        </label>
        <label>Username
            <input name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" autocomplete="username">
        </label>
        <label>Email
            <input name="email" type="email" required maxlength="190" autocomplete="email">
        </label>
        <label>Password <span class="hint">(at least 6 characters, with a letter and a number)</span>
            <input name="password" type="password" required minlength="6" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{6,}" title="Use at least 6 characters and include at least one letter and one number." autocomplete="new-password">
        </label>
        <button class="button button-primary" type="submit">Create admin account</button>
    </form>
</section>
<?php require __DIR__ . '/footer.php'; ?>
