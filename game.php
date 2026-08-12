<?php
require_once __DIR__ . '/includes/cards_data.php';
// At this point $cards, $drawnNumbers, $pattern, $autoMode, $userIdNumber,
// $gameId, $gameOverInitial are all guaranteed to exist.
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
                <h5 class="mb-3">
                    Card <?= $index + 1 ?>
                    <span class="badge bg-warning text-dark">ID: <?= htmlspecialchars($userIdNumber) ?></span>
                </h5>
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
                            <?php foreach (['B', 'I', 'N', 'G', 'O'] as $col): ?>
                                <?php if ($row == 2 && $col == 'N'): ?>
                                    <td class="free marked" data-row="2" data-col-index="2">FREE</td>
                                <?php else: ?>
                                    <td
                                        class="bingo-cell"
                                        data-row="<?= $row ?>"
                                        data-col-index="<?= array_search($col, ['B', 'I', 'N', 'G', 'O']) ?>"
                                        data-number="<?= $card[$col][$row] ?>">
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

<!-- Config data for assets/js/my_cards.js -->
<script>
    window.BingoCardsConfig = <?= json_encode([
        'gameId'         => $gameId,
        'drawnNumbers'   => $drawnNumbers,
        'pattern'        => $pattern,
        'autoMode'       => $autoMode,
        'gameOverInitial'=> $gameOverInitial,
    ]) ?>;
</script>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="js/confetti.min.js"></script>
<script src="js/game/my_cards.js"></script>

</body>
</html>