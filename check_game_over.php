<?php
session_name('Bingo');
session_start();
require 'config/db.php';

if (!isset($_SESSION['game_id'])) {
    echo json_encode(['gameOver' => false]);
    exit;
}

$gameId = $_SESSION['game_id'];

// Fetch total winners from game
$stmt = $pdo->prepare("SELECT winners FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    echo json_encode(['gameOver' => false]);
    exit;
}

$totalWinners = (int)$game['winners'];

// Count claimed winners dynamically
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int)$claimedStmt->fetchColumn();

$gameOver = ($claimedCount >= $totalWinners);

echo json_encode(['gameOver' => $gameOver]);