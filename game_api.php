<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login($pdo);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function room_for_player(PDO $pdo, string $code, int $userId): array
{
    $stmt = $pdo->prepare('SELECT r.*, rp.id AS room_player_id FROM rooms r INNER JOIN room_players rp ON rp.room_id = r.id WHERE r.code = ? AND rp.user_id = ? FOR UPDATE');
    $stmt->execute([$code, $userId]);
    $room = $stmt->fetch();
    if (!$room) {
        respond(['error' => 'Room not found. Return to your dashboard and check the invite.'], 404);
    }

    return $room;
}

function advance_room_if_ready(PDO $pdo, array $room): array
{
    if ($room['status'] !== 'playing') {
        return $room;
    }

    $questionStmt = $pdo->prepare('SELECT id FROM room_questions WHERE room_id = ? AND position = ?');
    $questionStmt->execute([$room['id'], $room['current_question']]);
    $questionId = $questionStmt->fetchColumn();
    if (!$questionId) {
        throw new RuntimeException('The active room question is missing.');
    }

    $playersStmt = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
    $playersStmt->execute([$room['id']]);
    $playerCount = (int) $playersStmt->fetchColumn();

    $answersStmt = $pdo->prepare('SELECT COUNT(*) FROM room_answers WHERE room_question_id = ?');
    $answersStmt->execute([$questionId]);
    $answerCount = (int) $answersStmt->fetchColumn();

    $timeStmt = $pdo->prepare('SELECT GREATEST(0, 20 - TIMESTAMPDIFF(SECOND, question_started_at, UTC_TIMESTAMP())) FROM rooms WHERE id = ?');
    $timeStmt->execute([$room['id']]);
    $timeLeft = (int) $timeStmt->fetchColumn();

    if ($answerCount < $playerCount && $timeLeft > 0) {
        return $room;
    }

    $questionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM room_questions WHERE room_id = ?');
    $questionCountStmt->execute([$room['id']]);
    $questionCount = (int) $questionCountStmt->fetchColumn();
    $nextQuestion = (int) $room['current_question'] + 1;

    if ($nextQuestion >= $questionCount) {
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'finished' WHERE id = ? AND status = 'playing'");
        $stmt->execute([$room['id']]);
        $stmt = $pdo->prepare('INSERT INTO attempts (user_id, score, total) SELECT user_id, score, ? FROM room_players WHERE room_id = ?');
        $stmt->execute([$questionCount, $room['id']]);
    } else {
        $stmt = $pdo->prepare('UPDATE rooms SET current_question = ?, question_started_at = UTC_TIMESTAMP() WHERE id = ? AND status = \'playing\'');
        $stmt->execute([$nextQuestion, $room['id']]);
    }

    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$room['id']]);

    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Use POST to access the game API.'], 405);
}

$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($submittedToken) || !hash_equals(csrf_token(), $submittedToken)) {
    respond(['error' => 'The page expired. Reload it and try again.'], 400);
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody === false ? '' : $rawBody, true);
if (!is_array($input)) {
    respond(['error' => 'Invalid request.'], 400);
}

$action = $input['action'] ?? null;
$codeValue = $input['code'] ?? null;
$code = is_string($codeValue) ? strtoupper($codeValue) : '';
if (!is_string($action) || !in_array($action, ['state', 'start', 'answer'], true) ||
    !preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code)) {
    respond(['error' => 'Invalid game request.'], 400);
}

try {
    $pdo->beginTransaction();
    $room = room_for_player($pdo, $code, (int) $user['id']);
    $roomPlayerId = (int) $room['room_player_id'];

    if ($action === 'start') {
        if ((int) $room['host_user_id'] !== (int) $user['id']) {
            $pdo->rollBack();
            respond(['error' => 'Only the room host can start the game.'], 403);
        }
        if ($room['status'] !== 'lobby') {
            $pdo->rollBack();
            respond(['error' => 'This game has already started.'], 409);
        }

        $playersStmt = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
        $playersStmt->execute([$room['id']]);
        if ((int) $playersStmt->fetchColumn() < 2) {
            $pdo->rollBack();
            respond(['error' => 'At least two players must join before the game can start.'], 409);
        }

        $questions = $pdo->query('SELECT question, option_a, option_b, option_c, option_d, correct_option FROM questions ORDER BY RAND() LIMIT 10')->fetchAll();
        if (count($questions) < 10) {
            $pdo->rollBack();
            respond(['error' => 'Add at least 10 questions in the admin page before starting a game.'], 409);
        }

        $insertQuestion = $pdo->prepare('INSERT INTO room_questions (room_id, position, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($questions as $position => $question) {
            $insertQuestion->execute([
                $room['id'],
                $position,
                $question['question'],
                $question['option_a'],
                $question['option_b'],
                $question['option_c'],
                $question['option_d'],
                $question['correct_option'],
            ]);
        }
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'playing', current_question = 0, question_started_at = UTC_TIMESTAMP() WHERE id = ?");
        $stmt->execute([$room['id']]);
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
        $stmt->execute([$room['id']]);
        $room = $stmt->fetch();
    } elseif ($action === 'answer') {
        $position = $input['position'] ?? null;
        $answer = $input['answer'] ?? null;
        if (!is_int($position) || $position < 0 || !is_string($answer) || !in_array($answer, ['a', 'b', 'c', 'd'], true)) {
            $pdo->rollBack();
            respond(['error' => 'Choose one of the available answers.'], 400);
        }

        $room = advance_room_if_ready($pdo, $room);
        if ($room['status'] !== 'playing') {
            $pdo->commit();
            respond(['error' => 'This game is already over.'], 409);
        }
        if ($position !== (int) $room['current_question']) {
            $pdo->commit();
            respond(['error' => 'That question has closed. The game has moved on.'], 409);
        }

        $questionStmt = $pdo->prepare('SELECT id, correct_option FROM room_questions WHERE room_id = ? AND position = ?');
        $questionStmt->execute([$room['id'], $position]);
        $question = $questionStmt->fetch();
        if (!$question) {
            throw new RuntimeException('The active room question could not be loaded.');
        }

        $alreadyAnswered = $pdo->prepare('SELECT id FROM room_answers WHERE room_question_id = ? AND room_player_id = ?');
        $alreadyAnswered->execute([$question['id'], $roomPlayerId]);
        if ($alreadyAnswered->fetch()) {
            $pdo->commit();
            respond(['error' => 'Your answer has already been recorded.'], 409);
        }

        $points = $answer === $question['correct_option'] ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO room_answers (room_question_id, room_player_id, selected_option, awarded_points, answered_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
        $stmt->execute([$question['id'], $roomPlayerId, $answer, $points]);
        if ($points) {
            $stmt = $pdo->prepare('UPDATE room_players SET score = score + 1 WHERE id = ?');
            $stmt->execute([$roomPlayerId]);
        }
    }

    $stmt = $pdo->prepare('UPDATE room_players SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$roomPlayerId]);
    $room = advance_room_if_ready($pdo, $room);

    $question = null;
    $questionId = null;
    $timeLeft = 0;
    if ($room['status'] === 'playing') {
        $questionStmt = $pdo->prepare('SELECT id, question, option_a, option_b, option_c, option_d FROM room_questions WHERE room_id = ? AND position = ?');
        $questionStmt->execute([$room['id'], $room['current_question']]);
        $question = $questionStmt->fetch();
        $questionId = $question ? (int) $question['id'] : null;
        $timeStmt = $pdo->prepare('SELECT GREATEST(0, 20 - TIMESTAMPDIFF(SECOND, question_started_at, UTC_TIMESTAMP())) FROM rooms WHERE id = ?');
        $timeStmt->execute([$room['id']]);
        $timeLeft = (int) $timeStmt->fetchColumn();
    }

    $playersStmt = $pdo->prepare(
        'SELECT rp.id, u.username, rp.score, a.id AS answer_id
         FROM room_players rp
         INNER JOIN users u ON u.id = rp.user_id
         LEFT JOIN room_answers a ON a.room_player_id = rp.id AND a.room_question_id = ?
         WHERE rp.room_id = ?
         ORDER BY rp.joined_at, rp.id'
    );
    $playersStmt->execute([$questionId, $room['id']]);
    $players = array_map(static fn (array $player): array => [
        'username' => $player['username'],
        'score' => (int) $player['score'],
        'answered' => $player['answer_id'] !== null,
        'is_you' => (int) $player['id'] === $roomPlayerId,
    ], $playersStmt->fetchAll());

    $response = [
        'code' => $room['code'],
        'status' => $room['status'],
        'is_host' => (int) $room['host_user_id'] === (int) $user['id'],
        'players' => $players,
        'question' => $question ?: null,
    ];

    if ($room['status'] === 'playing') {
        $response['position'] = (int) $room['current_question'];
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM room_questions WHERE room_id = ?');
        $totalStmt->execute([$room['id']]);
        $response['total'] = (int) $totalStmt->fetchColumn();
        $response['time_left'] = $timeLeft;
        $response['has_answered'] = (bool) array_filter(
            $players,
            static fn (array $player): bool => $player['is_you'] && $player['answered']
        );
    }

    if ($room['status'] === 'finished') {
        $response['players'] = array_values($players);
        usort($response['players'], static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $winningScore = $response['players'][0]['score'];
        $response['winners'] = array_values(array_map(
            static fn (array $player): string => $player['username'],
            array_filter($response['players'], static fn (array $player): bool => $player['score'] === $winningScore)
        ));
        $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM room_questions WHERE room_id = ?');
        $totalStmt->execute([$room['id']]);
        $response['total'] = (int) $totalStmt->fetchColumn();
    }

    $pdo->commit();
    respond($response);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Trivia game database error: ' . $exception->getMessage());
    respond(['error' => 'The game service could not complete that request. Please try again.'], 500);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Trivia game state error: ' . $exception->getMessage());
    respond(['error' => 'The game is in an unexpected state. Please return to your dashboard.'], 500);
}
