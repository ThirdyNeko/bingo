<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session invalid']);
    exit;
}

$gameId = $_SESSION['game_id'];
$userId = $_SESSION['user_id'];

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$cardIndex = $input['cardIndex'] ?? 0;
$markedNumbers = $input['markedNumbers'] ?? [];

// Fetch user's card
$stmt = $pdo->prepare("SELECT id, card_data FROM user_cards WHERE user_id = ? AND game_id = ? LIMIT 1 OFFSET ?");
$stmt->execute([$userId, $gameId, $cardIndex]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    echo json_encode(['success' => false, 'message' => 'Card not found']);
    exit;
}

$cardData = json_decode($card['card_data'], true);
$pattern = json_decode($pdo->query("SELECT pattern FROM game WHERE id=$gameId")->fetchColumn(), true);
$letters = ['B','I','N','G','O'];

// Check if pattern is completed
$patternNumbers = [];
foreach ($pattern as $row => $cols) {
    foreach ($cols as $col => $val) {
        if ($val == 1) {
            $n = $cardData[$letters[$col]][$row] ?? null;
            if ($n !== null && $n !== "FREE") $patternNumbers[] = $n;
        }
    }
}

if (!empty(array_diff($patternNumbers, $markedNumbers))) {
    echo json_encode(['success' => false, 'message' => 'Pattern not complete!']);
    exit;
}

// ✅ Only mark the card as claimed in the winner queue
$pdo->prepare("UPDATE game_winner_queue SET claimed = 1 WHERE game_id = ? AND card_id = ?")
    ->execute([$gameId, $card['id']]);

echo json_encode(['success' => true, 'message' => 'Bingo claimed!']);