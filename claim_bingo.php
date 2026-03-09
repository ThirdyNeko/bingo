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

// Get user name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userName = $stmt->fetchColumn();

// Get current winners
$stmt = $pdo->prepare("SELECT game_winners FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$current = $stmt->fetchColumn();

// Decode existing array
$winners = [];
if ($current) {
    $decoded = json_decode($current, true);
    if (is_array($decoded)) {
        $winners = $decoded;
    }
}

// Prevent duplicates
if (!in_array($userName, $winners)) {
    $winners[] = $userName;
}

// Save updated array
$stmt = $pdo->prepare("UPDATE game SET game_winners = ? WHERE id = ?");
$stmt->execute([json_encode($winners), $gameId]);

// Mark the card as claimed
$pdo->prepare("UPDATE game_winner_queue SET claimed = 1 WHERE game_id = ? AND card_id = ?")
    ->execute([$gameId, $card['id']]);

// Add 1 win to the user
$pdo->prepare("UPDATE users SET wins = wins + 1 WHERE id = ?")
    ->execute([$userId]);

echo json_encode([
    'success' => true,
    'message' => 'Bingo claimed!',
    'winners' => $winners
]);