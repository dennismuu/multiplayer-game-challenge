<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use the room form on the dashboard.');
}

verify_csrf();
$action = form_string('action');

if ($action === 'create') {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $createdRoomId = null;
    $createdCode = null;

    $pdo->exec("DELETE FROM rooms WHERE created_at < UTC_TIMESTAMP() - INTERVAL 14 DAY");

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = '';
        for ($character = 0; $character < 6; $character++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO rooms (code, host_user_id) VALUES (?, ?)');
            $stmt->execute([$code, $user['id']]);
            $roomId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO room_players (room_id, user_id, last_seen_at) VALUES (?, ?, UTC_TIMESTAMP())');
            $stmt->execute([$roomId, $user['id']]);
            $pdo->commit();
            $createdRoomId = $roomId;
            $createdCode = $code;
            break;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
        }
    }

    if ($createdRoomId === null || $createdCode === null) {
        throw new RuntimeException('Unable to allocate a unique room code after five attempts.');
    }

    redirect('game.php?code=' . rawurlencode($createdCode));
}

if ($action === 'join') {
    $code = strtoupper(trim(form_string('code')));
    if (!preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code)) {
        set_flash('Enter a valid six-character room code.', 'error');
        redirect('index.php');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, status FROM rooms WHERE code = ? FOR UPDATE');
    $stmt->execute([$code]);
    $room = $stmt->fetch();
    if (!$room) {
        $pdo->rollBack();
        set_flash('We could not find that room. Check the code with your host.', 'error');
        redirect('index.php');
    }

    $stmt = $pdo->prepare('SELECT id FROM room_players WHERE room_id = ? AND user_id = ?');
    $stmt->execute([$room['id'], $user['id']]);
    if ($stmt->fetch()) {
        $pdo->commit();
        redirect('game.php?code=' . rawurlencode($code));
    }
    if ($room['status'] !== 'lobby') {
        $pdo->rollBack();
        set_flash('That game has already started. Ask the host to create a new room.', 'error');
        redirect('index.php');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM room_players WHERE room_id = ?');
    $countStmt->execute([$room['id']]);
    if ((int) $countStmt->fetchColumn() >= 12) {
        $pdo->rollBack();
        set_flash('That room is full. A room can have up to 12 players.', 'error');
        redirect('index.php');
    }

    $stmt = $pdo->prepare('INSERT INTO room_players (room_id, user_id, last_seen_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $stmt->execute([$room['id'], $user['id']]);
    $pdo->commit();
    redirect('game.php?code=' . rawurlencode($code));
}

http_response_code(400);
exit('Unknown room action.');
