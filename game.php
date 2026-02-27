<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

// 1️⃣ Validate session
if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$gameId = $_SESSION['game_id'];
$userId = $_SESSION['user_id'];

// 2️⃣ Fetch game info
$stmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!$game['started']) {
    header("Location: lobby.php");
    exit;
}

// 3️⃣ Fetch user cards
$stmt = $pdo->prepare("SELECT card_data FROM user_cards WHERE user_id = ? AND game_id = ?");
$stmt->execute([$userId, $gameId]);
$cards = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($cards)) {
    die("No cards assigned yet. Please wait for the host to start.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bingo Cards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            color: white;
        }

        .bingo-card {
            max-width: 500px;
            margin: auto;
        }

        .bingo-table td, .bingo-table th {
            width: 70px;
            height: 70px;
            vertical-align: middle;
            cursor: pointer;
            transition: 0.2s;
            user-select: none;
        }

        .bingo-table td:hover {
            transform: scale(1.05);
        }

        .marked {
            background-color: #198754 !important;
            color: white !important;
        }

        .free {
            background-color: #ffc107 !important;
            font-weight: bold;
        }

        .card-body h5 {
            font-weight: bold;
        }
    </style>
</head>
<body class="py-4">

<div class="container text-center">
    <h1 class="mb-4">🎉 My Bingo Cards</h1>

    <?php foreach ($cards as $index => $cardJson): ?>
        <?php $card = json_decode($cardJson, true); ?>
        <div class="card shadow-lg bingo-card mb-5">
            <div class="card-body">
                <h5 class="mb-3">Card <?= $index + 1 ?></h5>
                <table class="table table-bordered text-center bingo-table mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>B</th>
                            <th>I</th>
                            <th>N</th>
                            <th>G</th>
                            <th>O</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php for ($row = 0; $row < 5; $row++): ?>
                        <tr>
                            <?php foreach (['B','I','N','G','O'] as $col): ?>
                                <?php if ($row == 2 && $col == 'N'): ?>
                                    <td class="free marked">FREE</td>
                                <?php else: ?>
                                    <td class="bingo-cell"><?= $card[$col][$row] ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script>
// Allow marking cells
document.querySelectorAll('.bingo-cell').forEach(cell => {
    cell.addEventListener('click', () => {
        cell.classList.toggle('marked');
    });
});
</script>

</body>
</html>