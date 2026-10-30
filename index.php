<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('You have signed out.');
        redirect('index.php');
    }

    if ($action === 'login') {
        $identity = trim(form_string('identity'));
        $password = form_string('password');
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, strtolower($identity)]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            set_flash('Welcome back!');
            redirect('index.php');
        }

        set_flash('Those sign-in details did not match an account.', 'error');
        redirect('index.php');
    }

    if ($action === 'register') {
        $username = trim(form_string('username'));
        $email = strtolower(trim(form_string('email')));
        $password = form_string('password');

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            set_flash('Choose a username with 3-30 letters, numbers, or underscores.', 'error');
            redirect('index.php?register=1');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            set_flash('Enter a valid email address.', 'error');
            redirect('index.php?register=1');
        }
        if (!password_is_valid($password)) {
            set_flash('Use at least 6 characters and include at least one letter and one number.', 'error');
            redirect('index.php?register=1');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, "user")');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            set_flash('That username or email is already registered.', 'error');
            redirect('index.php?register=1');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        set_flash('Your account is ready. Let’s play!');
        redirect('index.php');
    }

    http_response_code(400);
    exit('Unknown form action.');
}

$user = current_user($pdo);
$pageTitle = $user ? 'Dashboard' : (isset($_GET['register']) ? 'Create account' : 'Sign in');
require __DIR__ . '/header.php';
?>
<?php if (!$user): ?>
    <section class="auth-layout">
        <div class="hero">
            <p class="eyebrow">ONE QUIZ • EVERYWHERE</p>
            <h1>Bring your<br>brightest people.</h1>
            <p>Create a room, share the six-character code, and play the same live trivia round together from anywhere.</p>
            <div class="hero-stats"><span>✦ Live rooms</span><span>✦ Shared scores</span></div>
        </div>
        <section class="card auth-card">
            <?php if (isset($_GET['register'])): ?>
                <p class="eyebrow">JOIN THE GAME</p>
                <h2>Create your account</h2>
                <form method="post" action="index.php" class="stack">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="register">
                    <label>Username
                        <input name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" autocomplete="username">
                    </label>
                    <label>Email
                        <input name="email" type="email" required maxlength="190" autocomplete="email">
                    </label>
                    <label>Password <span class="hint">(at least 6 characters, with a letter and a number)</span>
                        <input name="password" type="password" required minlength="6" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{6,}" title="Use at least 6 characters and include at least one letter and one number." autocomplete="new-password">
                    </label>
                    <button class="button button-primary" type="submit">Create account</button>
                </form>
                <p class="form-foot">Already playing? <a href="index.php">Sign in</a></p>
            <?php else: ?>
                <p class="eyebrow">GOOD TO SEE YOU</p>
                <h2>Sign in</h2>
                <form method="post" action="index.php" class="stack">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="login">
                    <label>Username or email
                        <input name="identity" required autocomplete="username">
                    </label>
                    <label>Password
                        <input name="password" type="password" required autocomplete="current-password">
                    </label>
                    <button class="button button-primary" type="submit">Sign in</button>
                </form>
                <p class="form-foot">New here? <a href="index.php?register=1">Create an account</a></p>
            <?php endif; ?>
        </section>
    </section>
<?php else: ?>
    <?php
    $statsStmt = $pdo->prepare('SELECT COUNT(*) AS games, COALESCE(MAX(score), 0) AS best_score FROM attempts WHERE user_id = ?');
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();

    $historyStmt = $pdo->prepare('SELECT score, total, created_at FROM attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
    $historyStmt->execute([$user['id']]);
    $history = $historyStmt->fetchAll();
    $activeRoomStmt = $pdo->prepare("SELECT r.code FROM rooms r INNER JOIN room_players rp ON rp.room_id = r.id WHERE rp.user_id = ? AND r.status IN ('lobby', 'playing') ORDER BY r.created_at DESC LIMIT 1");
    $activeRoomStmt->execute([$user['id']]);
    $activeRoomCode = $activeRoomStmt->fetchColumn();
    ?>
    <section class="welcome-row">
        <div>
            <p class="eyebrow">YOUR TRIVIA SPACE</p>
            <h1>Hey, <?= e($user['username']) ?> <span class="wave">✦</span></h1>
            <p class="muted">Make a room and invite your people, wherever they are.</p>
        </div>
        <?php if ($activeRoomCode): ?>
            <div class="dashboard-actions">
                <a class="button button-secondary" href="game.php?code=<?= e($activeRoomCode) ?>">Return to your room</a>
                <form method="post" action="rooms.php">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <button class="button button-primary" type="submit">Create a new room <span aria-hidden="true">→</span></button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" action="rooms.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <button class="button button-primary" type="submit">Create a room <span aria-hidden="true">→</span></button>
            </form>
        <?php endif; ?>
    </section>
    <?php if (!$activeRoomCode): ?>
        <section class="card join-card">
            <div>
                <p class="eyebrow">GOT AN INVITE?</p>
                <h2>Join your friends</h2>
                <p class="muted">Enter the six-character room code shared by your host.</p>
            </div>
            <form method="post" action="rooms.php" class="join-form">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="join">
                <label class="sr-only" for="room-code">Room code</label>
                <input id="room-code" name="code" placeholder="ABC234" required minlength="6" maxlength="6" pattern="[A-HJ-NP-Za-hj-np-z2-9]{6}" autocomplete="off" aria-label="Six-character room code">
                <button class="button button-secondary" type="submit">Join room</button>
            </form>
        </section>
    <?php endif; ?>
    <section class="card rules-card">
        <p class="eyebrow">HOW A ROUND WORKS</p>
        <div class="rules-grid">
            <div><strong>01</strong><span>Invite up to 11 friends with your room code.</span></div>
            <div><strong>02</strong><span>Everyone gets the same 10 questions, with 20 seconds each.</span></div>
            <div><strong>03</strong><span>One point for each correct answer. Highest score wins; ties share the win.</span></div>
        </div>
    </section>
    <section class="stats-grid">
        <article class="card stat-card"><span class="stat-icon">◷</span><p class="muted">Rounds played</p><strong><?= e($stats['games']) ?></strong></article>
        <article class="card stat-card"><span class="stat-icon">★</span><p class="muted">Best score</p><strong><?= e($stats['best_score']) ?></strong></article>
    </section>
    <section class="card history-card">
        <div class="section-heading"><div><p class="eyebrow">KEEP IT GOING</p><h2>Recent games</h2></div></div>
        <?php if (!$history): ?>
            <p class="empty-state">Your score history will show up here after your first quiz.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Result</th><th>Score</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $attempt): ?>
                        <tr>
                            <td><span class="score-pill"><?= e((int) $attempt['score']) ?>/<?= e((int) $attempt['total']) ?></span></td>
                            <td><?= e((int) round(((int) $attempt['score'] / max(1, (int) $attempt['total'])) * 100)) ?>%</td>
                            <td><?= e(date('M j, Y · g:i a', strtotime($attempt['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
