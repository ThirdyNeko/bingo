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

/* ==============================
   CLAIMED WINNERS COUNT
============================== */
$claimedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM game_winner_queue 
    WHERE game_id = ? AND claimed = 1
");
$claimedStmt->execute([$gameId]);
$claimedCount = (int) $claimedStmt->fetchColumn();

$totalWinners = (int) $game['winners'];

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

$stmt = $pdo->prepare("SELECT id_number, auto_mode FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$userIdNumber = $user['id_number'];
$autoMode = (bool)$user['auto_mode'];

if (empty($cards)) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Game Already Started</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/design.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
    </style>
</head>
<body>

<div class="container text-center">

    <div class="card bg-secondary bg-opacity-10 border-0 shadow-lg p-4 rounded-4">

        <h2 class="mb-3">⏰ Too Late!</h2>

        <p class="mb-3">
            You weren't able to join the game in time.<br>
            The host has already started the game and cards were already distributed.
        </p>

        <a href="index.php" class="btn btn-light mt-2">
            Back to Main Menu
        </a>

    </div>

</div>

</body>
</html>
<?php
exit;
}

$drawnNumbers = array_map('intval', json_decode($game['drawn_numbers'] ?? '[]', true));
$pattern = json_decode($game['pattern'] ?? '[]', true) ?? [];


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
                    <span class="badge bg-warning text-dark">ID: <?= $userIdNumber ?></span>
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
                            <?php foreach (['B','I','N','G','O'] as $col): ?>
                                <?php if ($row == 2 && $col == 'N'): ?>
                                    <td class="free marked" data-row="2" data-col-index="2">FREE</td>
                                <?php else: ?>
                                    <td 
                                        class="bingo-cell"
                                        data-row="<?= $row ?>"
                                        data-col-index="<?= array_search($col, ['B','I','N','G','O']) ?>"
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

<script>
const drawnNumbers = <?= json_encode($drawnNumbers) ?>;
const gamePattern = <?= json_encode($pattern) ?>;
const autoMode = <?= $autoMode ? 'true' : 'false' ?>;
let gameOver = <?= ($claimedCount >= $totalWinners) ? 'true' : 'false' ?>; // initial state from PHP
let previousGameOver = gameOver; // remember previous state
</script>
<script src="sweetalert\dist\sweetalert2.all.min.js"></script>
<script src="js/confetti.min.js"></script>
<script>
document.querySelectorAll('.bingo-card').forEach((card, cardIndex) => {
    let previousGameOver = false;

    async function checkGameOverOnce() {
        try {
            const res = await fetch('check_game_over.php');
            const data = await res.json();
            const gameOver = data.gameOver;

            if (!previousGameOver && gameOver) {
                window.gameOverShown = true;

                // Disable all cells
                document.querySelectorAll('.bingo-cell').forEach(cell => {
                    cell.style.pointerEvents = 'none';
                    cell.classList.add('opacity-50');
                });

                // Disable all bingo buttons
                document.querySelectorAll('.bingo-btn').forEach(btn => {
                    btn.disabled = true;
                    btn.classList.remove('bounce-btn');
                });

                Swal.fire({
                    icon: 'info',
                    title: 'Game Over!',
                    text: 'All winners have already been claimed.',
                    confirmButtonColor: '#764ba2',
                    confirmButtonText: 'Back to Main Menu'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            }

            previousGameOver = gameOver;
        } catch (err) {
            console.error(err);
        }
    }

    setInterval(checkGameOverOnce, 5000);

    const cells = card.querySelectorAll('.bingo-cell');
    const bingoButton = card.querySelector('.bingo-btn');

    // Load manual marks from localStorage
    const storageKey = `bingo_marks_game_${<?= $gameId ?>}_card_${cardIndex}`;
    const savedMarks = JSON.parse(localStorage.getItem(storageKey)) || [];
    const manualMarks = new Set(savedMarks);

    const currentGameId = <?= $gameId ?>;
    const lastGameId = localStorage.getItem("last_game_id");

    if (lastGameId != currentGameId) {
        localStorage.clear();
        localStorage.setItem("last_game_id", currentGameId);
    }

    function restoreMarks() {
        cells.forEach(cell => {
            const number = parseInt(cell.dataset.number);
            if (manualMarks.has(number)) {
                cell.classList.add('marked');
            } else {
                cell.classList.remove('marked');
            }
        });
        verifyBingo();
    }

    cells.forEach(cell => {
        cell.addEventListener('click', () => {
            const number = parseInt(cell.dataset.number);

            // ❌ Check if number is drawn
            if (!drawnNumbers.includes(number)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Not Drawn!',
                    text: 'You cannot mark this number yet.',
                    timer: 1200,
                    showConfirmButton: false
                });
                return; // stop marking
            }

            // ✅ Normal marking
            cell.classList.toggle('marked');

            if (cell.classList.contains('marked')) manualMarks.add(number);
            else manualMarks.delete(number);

            localStorage.setItem(storageKey, JSON.stringify(Array.from(manualMarks)));

            // ✅ Verify bingo after marking
            verifyBingo();
        });
    });

    async function verifyBingo() {
        let patternMarked = true;

        for (let row = 0; row < 5; row++) {
            for (let col = 0; col < 5; col++) {

                if (gamePattern[row][col] === 1) {

                    // FREE cell is always considered marked
                    if (row === 2 && col === 2) continue;

                    const cell = Array.from(cells).find(c =>
                        parseInt(c.dataset.row) === row &&
                        parseInt(c.dataset.colIndex) === col
                    );

                    const number = parseInt(cell?.dataset.number ?? -1);

                    // Check if number is marked or in drawnNumbers if autoMode
                    if (!cell || (!cell.classList.contains('marked') && (!autoMode || !drawnNumbers.includes(number)))) {
                        patternMarked = false;
                        break;
                    }
                }

            }

            if (!patternMarked) break;
        }

        if (patternMarked) {
            bingoButton.classList.remove('d-none');
            bingoButton.classList.add('bounce-btn');
        } else {
            bingoButton.classList.add('d-none');
            bingoButton.classList.remove('bounce-btn');
        }
    }
    // Restore marks on page load
    restoreMarks();

    // ----- Long polling for new numbers (no auto-color) -----
    async function pollNewNumbers(lastNumber = 0) {
        try {
            const res = await fetch(`get_drawn_numbers.php?lastNumber=${lastNumber}`);
            const data = await res.json();

            if (data.newNumbers.length > 0) {
                data.newNumbers.forEach(n => {
                    drawnNumbers.push(n);

                    if (autoMode) {
                        cells.forEach(cell => {
                            const number = parseInt(cell.dataset.number);
                            if (number === n) {
                                cell.classList.add('marked');
                                manualMarks.add(number);
                            }
                        });
                    }
                });

                localStorage.setItem(storageKey, JSON.stringify(Array.from(manualMarks)));
                verifyBingo();
                lastNumber = Math.max(...drawnNumbers);
            }
        } catch (err) {
            console.error(err);
        } finally {
            // Poll again after 500ms (non-blocking)
            setTimeout(() => pollNewNumbers(lastNumber), 500);
        }
    }

    pollNewNumbers(); // start polling

    function disableAllCards() {

        // Disable all cells
        document.querySelectorAll('.bingo-cell').forEach(cell => {
            cell.style.pointerEvents = 'none';
            cell.classList.add('opacity-50');
        });

        // Disable all bingo buttons
        document.querySelectorAll('.bingo-btn').forEach(btn => {
            btn.disabled = true;
            btn.classList.remove('bounce-btn');
        });

    }

    // ----- Handle Bingo button click -----
    if (bingoButton) {
        bingoButton.addEventListener('click', async () => {
            const markedNumbers = Array.from(cells)
                .filter(cell => cell.classList.contains('marked'))
                .map(cell => parseInt(cell.dataset.number));

            try {
                const res = await fetch('claim_bingo.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        cardIndex: cardIndex,
                        markedNumbers: markedNumbers
                    })
                });

                // Parse JSON safely
                let data;
                try {
                    data = await res.json();
                } catch (jsonErr) {
                    console.error('JSON parse error:', jsonErr);
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid response',
                        text: await res.text(),
                    });
                    return;
                }

                if (data.success) {
                    disableAllCards();

                    // 🎉 Launch confetti
                    const duration = 3000;
                    const end = Date.now() + duration;
                    (function frame() {
                        confetti({
                            particleCount: 3,
                            spread: 90,
                            origin: { x: Math.random(), y: 0 }
                        });
                        if (Date.now() < end) requestAnimationFrame(frame);
                    })();

                    Swal.fire({
                        icon: 'success',
                        title: 'Bingo claimed!',
                        text: data.message || '',
                    });

                    bingoButton.disabled = true;

                } else {
                    // Show full server error if exists
                    let errorText = data.message || 'Cannot claim bingo now.';
                    if (data.error) {
                        errorText += '\n\n' + JSON.stringify(data.error, null, 2);
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Oops!',
                        html: `<pre style="text-align:left;white-space:pre-wrap;">${errorText}</pre>`,
                    });
                }

            } catch (err) {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Fetch Error',
                    text: err.message || 'Something went wrong while claiming bingo.',
                });
            }
        });
    }

});

</script>

</body>
</html>