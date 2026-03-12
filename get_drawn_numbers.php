<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['game_id'])) {
    http_response_code(400);
    exit;
}

header('Content-Type: application/json');

$gameId = $_SESSION['game_id'];

// Client sends last known numbers as comma-separated list
$lastNumbers = isset($_GET['lastNumbers']) ? explode(',', $_GET['lastNumbers']) : [];
$lastNumbers = array_map('intval', $lastNumbers);

$timeout = 25; // seconds
$startTime = time();

while (true) {
    $stmt = $pdo->prepare("SELECT drawn_numbers FROM game WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    $drawnNumbers = json_decode($game['drawn_numbers'], true) ?? [];

    // Send numbers the client hasn't seen yet
    $newNumbers = array_values(array_diff($drawnNumbers, $lastNumbers));

    if (!empty($newNumbers)) {
        echo json_encode(['newNumbers' => $newNumbers]);
        exit;
    }

    // Timeout
    if ((time() - $startTime) >= $timeout) {
        echo json_encode(['newNumbers' => []]);
        exit;
    }

    // Sleep 0.5s
    usleep(500000);
}