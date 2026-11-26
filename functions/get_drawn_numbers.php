<?php
/*
 * Returns the numbers drawn after the first ?count= ones, in draw order,
 * plus a gameOver hint so the client doesn't need a second poll for it.
 *
 * Answers immediately with ONE small query, so a poll never holds a PHP
 * worker open.
 *
 * Client sends ?count=<how many numbers it already has>.
 */
session_name('Bingo');
// read_and_close loads the session and releases its lock immediately, so
// 100+ players polling never queue up behind each other's session files
// (and this player's claim_bingo / change_card are never blocked by a poll).
session_start(['read_and_close' => true]);
require_once '../config/db.php';

if (!isset($_SESSION['game_id'])) {
    http_response_code(400);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

$gameId = (int) $_SESSION['game_id'];
$count  = max(0, (int) ($_GET['count'] ?? 0));

// One round trip: drawn numbers + how many winners are claimed vs required.
$stmt = $pdo->prepare("
    SELECT g.drawn_numbers,
           g.winners,
           (SELECT COUNT(*) FROM game_winner_queue q
             WHERE q.game_id = g.id AND q.claimed = 1) AS claimed_count
    FROM game g
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$drawn = array_map('intval', json_decode($row['drawn_numbers'] ?? '[]', true) ?: []);

$totalWinners = (int) ($row['winners'] ?? 0);
$claimedCount = (int) ($row['claimed_count'] ?? 0);

echo json_encode([
    'newNumbers' => array_slice($drawn, $count),
    // Hint only: the client confirms with check_game_over.php before
    // showing the "Game Over" dialog.
    'gameOver'   => $totalWinners > 0 && $claimedCount >= $totalWinners,
]);