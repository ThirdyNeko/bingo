<?php
require_once __DIR__ . '/includes/cards_data.php';
// At this point $cards, $drawnNumbers, $pattern, $autoMode, $userIdNumber,
// $gameId, $gameOverInitial are all guaranteed to exist.

// Card-change window: while open, players may regenerate the neutral
// numbers on their card(s). $pdo is expected to already be in scope
// here via cards_data.php's own require of config/db.php.
$cardChangeStmt = $pdo->prepare("SELECT started, card_change_deadline FROM game WHERE id = ?");
$cardChangeStmt->execute([$gameId]);
$cardChangeRow = $cardChangeStmt->fetch();

$cardChangeDeadline = $cardChangeRow['card_change_deadline'] ?? null;
$inCardChangeWindow = $cardChangeRow
    && (int) $cardChangeRow['started'] === 1
    && $cardChangeDeadline
    && strtotime($cardChangeDeadline) > time();

// Per-card "already used its one change" flags, in the same id-ascending
// order as $cards, so cardChangedFlags[$index] lines up with $cards[$index].
// Assumes $_SESSION['user_id'] is set the same way change_card.php expects —
// which it should be, since cards_data.php already used it to load $cards.
$cardChangedFlags = [];
if (isset($_SESSION['user_id'])) {
    $changedStmt = $pdo->prepare("
        SELECT card_changed
        FROM user_cards
        WHERE user_id = ? AND game_id = ?
        ORDER BY id ASC
    ");
    $changedStmt->execute([(int) $_SESSION['user_id'], $gameId]);
    $cardChangedFlags = $changedStmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bingo Cards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/game.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            color: white;
        }

        .change-card-btn {
            background: linear-gradient(135deg, #f7971e, #ffd200);
            border: none;
            color: #3a2a00;
            font-weight: 700;
            letter-spacing: 0.02em;
            box-shadow: 0 0 0 rgba(255, 210, 0, 0.6);
            animation: change-card-pulse 1.8s ease-in-out infinite;
            transition: transform 0.15s ease;
        }

        .change-card-btn:hover,
        .change-card-btn:focus {
            color: #3a2a00;
            transform: scale(1.05);
        }

        .change-card-btn:active {
            transform: scale(0.97);
        }

        @keyframes change-card-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 210, 0, 0.55);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(255, 210, 0, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 210, 0, 0);
            }
        }
    </style>
</head>
<body class="py-4">

<div class="container text-center">
    <h1 class="mb-4">🎉 My Bingo Cards</h1>

    <?php if ($inCardChangeWindow): ?>
        <div class="alert alert-info d-inline-block mb-4" id="card-change-countdown-wrap">
            <h6 class="mb-1">
                ⏱️ You can change your card until
                <?= date('h:i A', strtotime($cardChangeDeadline)) ?>
            </h6>
            <div class="fw-bold" id="card-change-countdown">calculating…</div>
        </div>
    <?php endif; ?>

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

                <?php if ($inCardChangeWindow && empty($cardChangedFlags[$index])): ?>
                    <button
                        class="btn btn-lg mt-3 change-card-btn"
                        data-card-index="<?= $index ?>">
                        🔄 Change Card
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- Config data for assets/js/my_cards.js -->
<script>
    window.BingoCardsConfig = <?= json_encode([
        'gameId'              => $gameId,
        'drawnNumbers'        => $drawnNumbers,
        'pattern'             => $pattern,
        'autoMode'            => $autoMode,
        'gameOverInitial'     => $gameOverInitial,
        'inCardChangeWindow'  => $inCardChangeWindow,
        'cardChangeDeadline'  => $cardChangeDeadline ? date('c', strtotime($cardChangeDeadline)) : null,
    ]) ?>;
</script>

<script src="sweetalert/dist/sweetalert2.all.min.js"></script>
<script src="js/confetti.min.js"></script>
<script src="js/game/my_cards.js"></script>

</body>
</html>