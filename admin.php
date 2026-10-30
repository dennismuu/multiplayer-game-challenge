<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
$admin = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = form_string('action');

    if ($action === 'save_question') {
        $questionIdValue = $_POST['question_id'] ?? null;
        $questionId = positive_int($questionIdValue);
        if ($questionIdValue !== null && $questionIdValue !== '' && $questionId === null) {
            set_flash('Choose a valid question to edit.', 'error');
            redirect('admin.php');
        }
        $question = trim(form_string('question'));
        $options = [];
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $options[$letter] = trim(form_string('option_' . $letter));
        }
        $correct = form_string('correct_option');

        if ($question === '' || strlen($question) > 500 || in_array('', $options, true) ||
            max(array_map('strlen', $options)) > 255 || !in_array($correct, ['a', 'b', 'c', 'd'], true)) {
            set_flash('Enter a question, four answers, and select the correct answer.', 'error');
            redirect('admin.php');
        }

        if ($questionId) {
            $stmt = $pdo->prepare('UPDATE questions SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ? WHERE id = ?');
            $stmt->execute([$question, $options['a'], $options['b'], $options['c'], $options['d'], $correct, $questionId]);
            set_flash($stmt->rowCount() ? 'Question updated.' : 'No question changes were needed.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO questions (question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$question, $options['a'], $options['b'], $options['c'], $options['d'], $correct]);
            set_flash('Question added.');
        }
        redirect('admin.php');
    }

    if ($action === 'delete_question') {
        $questionId = positive_int($_POST['question_id'] ?? null);
        if (!$questionId) {
            set_flash('Choose a valid question.', 'error');
        } else {
            $stmt = $pdo->prepare('DELETE FROM questions WHERE id = ?');
            $stmt->execute([$questionId]);
            set_flash($stmt->rowCount() ? 'Question deleted.' : 'That question was not found.', $stmt->rowCount() ? 'info' : 'error');
        }
        redirect('admin.php');
    }

    if ($action === 'change_role') {
        $targetId = positive_int($_POST['user_id'] ?? null);
        $newRole = form_string('role');
        if (!$targetId || !in_array($newRole, ['user', 'admin'], true) || $targetId === (int) $admin['id']) {
            set_flash('That account role cannot be changed.', 'error');
            redirect('admin.php');
        }

        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            set_flash('That account was not found.', 'error');
        } elseif ($target['role'] === 'admin' && $newRole === 'user' &&
            (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() <= 1) {
            set_flash('The last admin account cannot be demoted.', 'error');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$newRole, $targetId]);
            set_flash('Account role updated.');
        }
        redirect('admin.php');
    }

    http_response_code(400);
    exit('Unknown form action.');
}

$editId = positive_int($_GET['edit'] ?? null);
$editQuestion = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
    $stmt->execute([$editId]);
    $editQuestion = $stmt->fetch() ?: null;
}
$questions = $pdo->query('SELECT id, question, option_a, option_b, option_c, option_d, correct_option FROM questions ORDER BY id DESC')->fetchAll();
$users = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Admin';
require __DIR__ . '/header.php';
?>
<section class="admin-heading">
    <p class="eyebrow">GAME MANAGEMENT</p>
    <h1>Admin space</h1>
    <p class="muted">Keep the question list fresh and manage player access.</p>
</section>
<section class="card admin-section">
    <div class="section-heading">
        <div><p class="eyebrow"><?= $editQuestion ? 'MAKE A CHANGE' : 'GROW THE QUESTION BANK' ?></p><h2><?= $editQuestion ? 'Edit question' : 'Add a question' ?></h2></div>
    </div>
    <form method="post" class="stack">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="save_question">
        <input type="hidden" name="question_id" value="<?= e($editQuestion['id'] ?? '') ?>">
        <label>Question
            <textarea name="question" rows="2" maxlength="500" required><?= e($editQuestion['question'] ?? '') ?></textarea>
        </label>
        <div class="admin-option-grid">
            <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                <label>Answer <?= e(strtoupper($letter)) ?>
                    <input name="option_<?= e($letter) ?>" maxlength="255" required value="<?= e($editQuestion['option_' . $letter] ?? '') ?>">
                </label>
            <?php endforeach; ?>
        </div>
        <label>Correct answer
            <select name="correct_option" required>
                <option value="">Choose one</option>
                <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                    <option value="<?= e($letter) ?>" <?= ($editQuestion['correct_option'] ?? '') === $letter ? 'selected' : '' ?>>Answer <?= e(strtoupper($letter)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="inline-actions">
            <button class="button button-primary" type="submit"><?= $editQuestion ? 'Save question' : 'Add question' ?></button>
            <?php if ($editQuestion): ?><a class="button button-secondary" href="admin.php">Cancel edit</a><?php endif; ?>
        </div>
    </form>
</section>
<section class="card admin-section">
    <div class="section-heading"><div><p class="eyebrow">QUESTION BANK</p><h2>Questions <span class="count"><?= e(count($questions)) ?></span></h2></div></div>
    <?php if (!$questions): ?>
        <p class="empty-state">No questions yet. Add the first one above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Question</th><th>Correct</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><?= e($question['question']) ?></td>
                        <td><span class="score-pill"><?= e(strtoupper($question['correct_option'])) ?></span></td>
                        <td><div class="inline-actions">
                            <a class="text-link" href="admin.php?edit=<?= e((int) $question['id']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this question?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?= e((int) $question['id']) ?>">
                                <button class="text-button danger-text" type="submit">Delete</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card admin-section">
    <div class="section-heading"><div><p class="eyebrow">PLAYER ACCESS</p><h2>Accounts <span class="count"><?= e(count($users)) ?></span></h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Player</th><th>Role</th><th>Joined</th><th>Change role</th></tr></thead>
            <tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td><strong><?= e($account['username']) ?></strong><br><span class="muted small"><?= e($account['email']) ?></span></td>
                    <td><span class="role-pill"><?= e(ucfirst($account['role'])) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($account['created_at']))) ?></td>
                    <td>
                        <?php if ((int) $account['id'] === (int) $admin['id']): ?>
                            <span class="muted small">You</span>
                        <?php else: ?>
                            <form method="post" class="role-form">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= e((int) $account['id']) ?>">
                                <select name="role" aria-label="Role for <?= e($account['username']) ?>">
                                    <option value="user" <?= $account['role'] === 'user' ? 'selected' : '' ?>>Player</option>
                                    <option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button class="button button-small button-secondary" type="submit">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
