<?php
session_name('Bingo');
// read_and_close loads the session and releases its lock immediately,
// so this never queues behind other requests from the same player.
session_start(['read_and_close' => true]);
require '../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['game_id'])) {
    echo json_encode(['gameOver' => false]);
    exit;
}

$gameId = (int) $_SESSION['game_id'];

// One query instead of two
$stmt = $pdo->prepare("
    SELECT
        g.winners AS total_winners,
        (SELECT COUNT(*) FROM game_winner_queue q
          WHERE q.game_id = g.id AND q.claimed = 1) AS claimed_count
    FROM game g
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['gameOver' => false]);
    exit;
}

echo json_encode([
    'gameOver' => ((int) $row['claimed_count'] >= (int) $row['total_winners'])
]);