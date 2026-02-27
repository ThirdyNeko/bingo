<?php
session_name('Bingo');
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'config/db.php';

$error = '';

// Detect QR usage
$qrGameCode = trim($_GET['game_code'] ?? '');
$isFromQR = !empty($qrGameCode);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // If QR was used, trust POST but fallback to GET
    $game_code = trim($_POST['game_code'] ?? $qrGameCode);
    $id_number = trim($_POST['id_number'] ?? '');

    if (empty($game_code) || empty($id_number)) {
        $error = "All fields are required.";
    } else {

        // 1️⃣ Check Game
        $stmt = $pdo->prepare("SELECT * FROM game WHERE game_code = ?");
        $stmt->execute([$game_code]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            $error = "Invalid Game Code.";
        } else {

            // 2️⃣ Check User
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
            $stmt->execute([$id_number]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "ID Number not found.";
            } else {

                // 3️⃣ Link user to game
                $update = $pdo->prepare("UPDATE users SET current_game = ? WHERE id_number = ?");
                $update->execute([$game['id'], $id_number]);

                // 4️⃣ Store Session (fixed)
                $_SESSION['game_id']   = $game['id'];
                $_SESSION['game_code'] = $game['game_code'];
                $_SESSION['user_id']   = $user['id'];       // INTERNAL ID, not id_number
                $_SESSION['name']      = $user['name'];
                $_SESSION['role']      = $user['role'];

                header("Location: lobby.php");
                exit;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bingo Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height: 100vh;">

<style>
    body {
        background: radial-gradient(circle at top, #1f1f1f, #0f0f0f);
    }

    .dark-card {
        background-color: #1a1a1a;
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .form-control {
        background-color: #2a2a2a;
        border: 1px solid #444;
        color: #fff;
    }

    .form-control:focus {
        background-color: #2a2a2a;
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
        color: #fff;
    }

    .form-control::placeholder {
        color: #aaa;
    }

    .btn-primary {
        background: linear-gradient(45deg, #0d6efd, #4dabf7);
        border: none;
    }

    .btn-primary:hover {
        opacity: 0.9;
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">

            <div class="card dark-card shadow-lg rounded-4">
                <div class="card-body p-4">

                    <h4 class="text-center mb-4 fw-bold">Join Bingo Game</h4>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <?php if ($isFromQR): ?>
                            <input type="hidden" name="game_code" value="<?= htmlspecialchars($qrGameCode) ?>">

                            <div class="alert alert-success text-center">
                                Joining Game: <strong><?= htmlspecialchars($qrGameCode) ?></strong>
                            </div>

                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Game Code</label>
                                <input 
                                    type="text" 
                                    name="game_code" 
                                    id="game_code"
                                    class="form-control form-control-lg text-center"
                                    placeholder="Enter Game Code"
                                    required
                                >
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">ID Number</label>
                            <input 
                                type="text" 
                                name="id_number"
                                id="id_number"
                                class="form-control form-control-lg text-center"
                                placeholder="Enter ID Number"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 rounded-3">
                            Join Game
                        </button>

                    </form>

                </div>
            </div>

            <p class="text-center text-secondary small mt-3">
                Scan the QR code to auto-fill the Game Code
            </p>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    <?php if ($isFromQR): ?>
        document.getElementById('id_number').focus();
    <?php else: ?>
        document.getElementById('game_code').focus();
    <?php endif; ?>
});
</script>

</body>
</html>