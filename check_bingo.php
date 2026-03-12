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

/* =========================
   FETCH GAME DATA
========================= */
$stmt = $pdo->prepare("SELECT drawn_numbers, pattern FROM game WHERE id=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Ensure null doesn't break json_decode
$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
$pattern = json_decode($game['pattern'] ?? '[]', true);

/* =========================
   FETCH PLAYER CARD
========================= */
$stmt = $pdo->prepare("
    SELECT card_data 
    FROM user_cards 
    WHERE user_id=? AND game_id=?
    ORDER BY id
    OFFSET ? ROWS FETCH NEXT 1 ROWS ONLY
");
$stmt->execute([$userId, $gameId, $cardIndex]);

$cardJson = $stmt->fetchColumn();

if (!$cardJson) {
    echo json_encode(['bingo' => false]);
    exit;
}

$card = json_decode($cardJson, true);

/* =========================
   CHECK FOR BINGO
========================= */
$columns = ['B','I','N','G','O'];
$bingo = true;

foreach ($pattern as $row => $cols) {
    foreach ($cols as $col => $required) {

        if ($required == 1) {

            // Skip center free cell
            if ($row == 2 && $col == 2) continue;

            $number = (int)$card[$columns[$col]][$row]; // cast to int

            if (!in_array($number, $drawnNumbers, true)) { // strict comparison
                $bingo = false;
                break 2;
            }
        }
    }
}

echo json_encode(['bingo' => $bingo]);