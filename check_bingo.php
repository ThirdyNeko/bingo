<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    echo json_encode(['bingo' => false]);
    exit;
}

$gameId = $_SESSION['game_id'];
$userId = $_SESSION['user_id'];

$cardIndex = $_GET['cardIndex'] ?? 0;

/* GAME */
$stmt = $pdo->prepare("SELECT drawn_numbers, pattern FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

$drawnNumbers = json_decode($game['drawn_numbers'], true) ?? [];
$pattern = json_decode($game['pattern'], true) ?? [];

/* CARD */
$stmt = $pdo->prepare("
SELECT card_data 
FROM user_cards 
WHERE user_id=? AND game_id=?
LIMIT 1 OFFSET ?
");
$stmt->execute([$userId, $gameId, $cardIndex]);

$cardJson = $stmt->fetchColumn();

if (!$cardJson) {
    echo json_encode(['bingo'=>false]);
    exit;
}

$card = json_decode($cardJson, true);

$columns = ['B','I','N','G','O'];
$bingo = true;

foreach ($pattern as $row => $cols) {
    foreach ($cols as $col => $required) {

        if ($required == 1) {

            if ($row == 2 && $col == 2) continue;

            $number = $card[$columns[$col]][$row];

            if (!in_array($number, $drawnNumbers)) {
                $bingo = false;
                break 2;
            }
        }
    }
}

echo json_encode(['bingo'=>$bingo]);