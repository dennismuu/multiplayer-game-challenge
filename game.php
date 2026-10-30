<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login($pdo);
$codeValue = $_GET['code'] ?? '';
$code = is_string($codeValue) ? strtoupper(trim($codeValue)) : '';

if (!preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code)) {
    http_response_code(404);
    exit('That room could not be found.');
}

$stmt = $pdo->prepare('SELECT r.code FROM rooms r INNER JOIN room_players rp ON rp.room_id = r.id WHERE r.code = ? AND rp.user_id = ?');
$stmt->execute([$code, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit('That room could not be found.');
}

$pageTitle = 'Game room';
$pageScript = 'game.js';
require __DIR__ . '/header.php';
?>
<section class="game-shell" id="game-app" data-code="<?= e($code) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <section class="game-top">
        <a class="back-link" href="index.php">← Dashboard</a>
        <span class="live-badge"><span></span> LIVE ROOM</span>
    </section>
    <section class="card room-card">
        <div class="room-header">
            <div>
                <p class="eyebrow">YOUR GAME ROOM</p>
                <h1>Room <span class="room-code"><?= e($code) ?></span></h1>
            </div>
            <button type="button" class="button button-secondary" id="copy-invite">Copy invite link</button>
        </div>
        <p class="muted" id="room-status" aria-live="polite">Connecting to your room…</p>
        <div class="room-toolbar">
            <div class="room-metric"><span class="metric-label">ROUND</span><strong id="round-label">Waiting</strong></div>
            <div class="timer-wrap"><span class="metric-label">TIME LEFT</span><strong id="timer-label">—</strong></div>
        </div>
        <div id="lobby-panel" class="lobby-panel" hidden>
            <div class="lobby-message">
                <span class="lobby-icon" aria-hidden="true">✦</span>
                <div><h2>Bring your crew in</h2><p>Share the room code or invite link. The host can start once at least two players have joined.</p></div>
            </div>
            <button id="start-game" class="button button-primary" type="button" hidden>Start the game <span aria-hidden="true">→</span></button>
        </div>
        <div id="question-panel" class="question-panel" hidden>
            <p class="question-kicker" id="question-kicker"></p>
            <h2 id="question-text"></h2>
            <div class="game-answer-grid" id="answer-options"></div>
            <p class="answer-feedback" id="answer-feedback" aria-live="polite"></p>
        </div>
        <div id="results-panel" class="results-panel" hidden>
            <div class="result-confetti" aria-hidden="true">✦</div>
            <p class="eyebrow">FINAL STANDINGS</p>
            <h2 id="winner-heading">Game complete</h2>
            <ol class="leaderboard" id="leaderboard"></ol>
            <a class="button button-primary" href="index.php">Back to dashboard</a>
        </div>
    </section>
    <aside class="card players-card">
        <div class="players-heading"><div><p class="eyebrow">THE CREW</p><h2>Players <span id="player-count" class="count">0</span></h2></div></div>
        <ul class="player-list" id="player-list" aria-live="polite"></ul>
        <p class="game-rules"><strong>Quick rules</strong><br>10 questions · 20 seconds each · 1 point per correct answer. When everyone answers, the next question starts. Highest score wins; ties share the win.</p>
    </aside>
</section>
<?php require __DIR__ . '/footer.php'; ?>
