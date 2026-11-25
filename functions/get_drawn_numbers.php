<?php
/*
 * Returns the numbers drawn after the first ?count= ones, in draw order.
 *
 * Replaces the old long-poll loop. It answers immediately with one small
 * query, so a poll never holds a PHP worker open (the old version held one
 * per player for up to 25s, which exhausted the web server at ~150 players).
 *
 * Client sends ?count=<how many numbers it already has>.
 */
session_name('Bingo');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['game_id'])) {
    http_response_code(400);
    exit;
}

header('Content-Type: application/json');

$gameId = (int) $_SESSION['game_id'];

// Release the session lock right away so this player's other requests
// (claim_bingo, change_card) are never blocked behind a poll.
session_write_close();

$count = max(0, (int) ($_GET['count'] ?? 0));

$stmt = $pdo->prepare("SELECT drawn_numbers FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$drawn = array_map('intval', json_decode($row['drawn_numbers'] ?? '[]', true) ?: []);

echo json_encode(['newNumbers' => array_slice($drawn, $count)]);