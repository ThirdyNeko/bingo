<?php
session_name('Bingo');
session_start();
require_once __DIR__ . '/../config/db.php';

// 1️⃣ Validate session
if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$gameId = $_SESSION['game_id'];
$userId = $_SESSION['user_id'];

// 2️⃣ Fetch game info
$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!$game['started']) {
    header("Location: lobby.php");
    exit;
}

/* ==============================
   CLAIMED WINNERS COUNT
============================== */
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM game_winner_queue
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int) $claimedStmt->fetchColumn();
$totalWinners = (int) $game['winners'];

// 3️⃣ Fetch user cards
$stmt = $pdo->prepare("SELECT card_data FROM user_cards WHERE user_id = ? AND game_id = ?");
$stmt->execute([$userId, $gameId]);
$cards = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT id_number, auto_mode FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$userIdNumber = $user['id_number'];
$autoMode = (bool) $user['auto_mode'];

// 4️⃣ No cards → show locked-out screen and stop
if (empty($cards)) {
    require __DIR__ . '/no_cards.php';
    exit;
}

$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
$pattern = json_decode($game['pattern'] ?? '[]', true) ?? [];
$gameOverInitial = ($claimedCount >= $totalWinners);