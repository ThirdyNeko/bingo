<?php
session_name('Bingo');
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'config/db.php';

if (!isset($_SESSION['game_id'], $_SESSION['name'])) {
    header("Location: index.php");
    exit;
}

$gameId = $_SESSION['game_id'];

$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lobby</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark text-white d-flex align-items-center" style="min-height:100vh;">

<div class="container text-center">

    <div class="card bg-secondary bg-opacity-10 border-0 shadow-lg p-4 rounded-4">

        <h2 class="mb-3">🎉 You're in!</h2>

        <p class="mb-1">
            <strong>Player:</strong> <?= htmlspecialchars($_SESSION['name']) ?>
        </p>

        <p class="mb-3">
            <strong>Game Code:</strong> <?= htmlspecialchars($_SESSION['game_code']) ?>
        </p>

        <div class="spinner-border text-success mb-3" role="status"></div>

        <h5 class="text-warning">Waiting for host to start the game...</h5>

        <p class="text-muted small mt-3">
            The game will begin automatically.
        </p>

    </div>

</div>

<script>
function checkGameStatus() {
    fetch("check_game_status.php?game_id=<?= $gameId ?>")
        .then(res => res.json())
        .then(data => {
            if (data.started) {
                window.location.href = "game.php";
            }
        });
}

// Check every 3 seconds
setInterval(checkGameStatus, 3000);
</script>

</body>
</html>