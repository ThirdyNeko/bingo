<?php
require_once 'config/db.php';

$gameId = (int) ($_GET['game_id'] ?? 0);

$stmt = $pdo->prepare("SELECT started FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'started' => $game ? (bool)$game['started'] : false
]);