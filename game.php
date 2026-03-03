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

$drawnNumbers = json_decode($game['drawn_numbers'], true) ?? [];
$pattern = json_decode($game['pattern'], true) ?? [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bingo Cards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/design.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            color: white;
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
                                    <td 
                                        class="bingo-cell"
                                        data-row="<?= $row ?>"
                                        data-col-index="<?= array_search($col, ['B','I','N','G','O']) ?>">
                                        <?= $card[$col][$row] ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>

                <button 
                    class="btn btn-success mt-3 bingo-btn d-none bounce-btn"
                    data-card-index="<?= $index ?>">
                    🎉 BINGO! 🎉
                </button>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script>
const drawnNumbers = <?= json_encode($drawnNumbers) ?>;
const gamePattern = <?= json_encode($pattern) ?>;
</script>
<script src="sweetalert\dist\sweetalert2.all.min.js"></script>
<script>
document.querySelectorAll('.bingo-card').forEach(card => {

    const cells = card.querySelectorAll('.bingo-cell');
    const bingoButton = card.querySelector('.bingo-btn');

    function checkPattern() {

        if (!bingoButton) return;

        let matched = true;

        cells.forEach(cell => {

            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.colIndex);

            if (!gamePattern[row]) return;

            if (gamePattern[row][col] === 1) {

                if (row === 2 && col === 2) return;

                if (!cell.classList.contains('marked')) {
                    matched = false;
                }
            }
        });

        if (matched) {
            bingoButton.classList.remove('d-none');
        } else {
            bingoButton.classList.add('d-none');
        }
    }

    cells.forEach(cell => {
        cell.addEventListener('click', () => {

            const number = parseInt(cell.innerText);

            if (drawnNumbers.includes(number)) {
                cell.classList.toggle('marked');
                checkPattern();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Not Drawn!',
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        });
    });

});
</script>

</body>
</html>