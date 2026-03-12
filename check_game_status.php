<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$gameId = (int) ($_GET['game_id'] ?? 0);

if (!$gameId) {
    echo json_encode(['started' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT started FROM game WHERE id = ?");
$stmt->execute([$gameId]);

$started = (bool)$stmt->fetchColumn();

echo json_encode([
    'started' => $started
]);