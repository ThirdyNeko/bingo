<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Session invalid']);
    exit;
}

$gameId = $_SESSION['game_id'];
$userId = $_SESSION['user_id'];

/* ===============================
   READ INPUT
================================ */
$input = json_decode(file_get_contents('php://input'), true);
$cardIndex = (int)($input['cardIndex'] ?? 0);
$markedNumbers = $input['markedNumbers'] ?? [];

if (!is_array($markedNumbers)) {
    echo json_encode(['success'=>false,'message'=>'Invalid marked numbers']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // -----------------------------
    // Fetch the user's card with lock
    // -----------------------------
    $stmt = $pdo->prepare("
        SELECT id, card_data
        FROM user_cards
        WHERE user_id = ? AND game_id = ?
        ORDER BY id
        OFFSET ? ROWS FETCH NEXT 1 ROWS ONLY
    ");
    $stmt->execute([$userId, $gameId, $cardIndex]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Card not found']);
        exit;
    }

    $cardData = json_decode($card['card_data'], true);
    if (!$cardData) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Invalid card data']);
        exit;
    }

    // -----------------------------
    // Lock the game row
    // -----------------------------
    $stmt = $pdo->prepare("
        SELECT pattern, winners, game_winners
        FROM game
        WHERE id = ? 
        FOR UPDATE
    ");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Game not found']);
        exit;
    }

    $pattern = json_decode($game['pattern'], true);
    $maxWinners = (int)$game['winners'];

    // -----------------------------
    // Build required pattern numbers
    // -----------------------------
    $letters = ['B','I','N','G','O'];
    $patternNumbers = [];

    foreach ($pattern as $row => $cols) {
        foreach ($cols as $col => $val) {
            if ($val == 1 && !($row == 2 && $col == 2)) {
                $n = $cardData[$letters[$col]][$row] ?? null;
                if ($n !== null) $patternNumbers[] = $n;
            }
        }
    }

    // -----------------------------
    // Validate pattern
    // -----------------------------
    if (!empty(array_diff($patternNumbers, $markedNumbers))) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Pattern not complete!']);
        exit;
    }

    // -----------------------------
    // Fetch user name
    // -----------------------------
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userName = $stmt->fetchColumn();

    // -----------------------------
    // Decode current winners
    // -----------------------------
    $winners = [];
    if ($game['game_winners']) {
        $decoded = json_decode($game['game_winners'], true);
        if (is_array($decoded)) $winners = $decoded;
    }

    // Check if max winners reached
    if (count($winners) >= $maxWinners) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'All winners already claimed']);
        exit;
    }

    // Prevent duplicate
    if (!in_array($userName, $winners)) {
        $winners[] = $userName;
    }

    // -----------------------------
    // Mark card as claimed in winner queue
    // -----------------------------
    $stmt = $pdo->prepare("
        UPDATE game_winner_queue
        SET claimed = 1
        WHERE game_id = ? AND card_id = ? AND claimed = 0
    ");
    $stmt->execute([$gameId, $card['id']]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Card already claimed']);
        exit;
    }

    // -----------------------------
    // Update game winners
    // -----------------------------
    $stmt = $pdo->prepare("
        UPDATE game
        SET game_winners = ?
        WHERE id = ?
    ");
    $stmt->execute([json_encode($winners), $gameId]);

    // -----------------------------
    // Update user wins
    // -----------------------------
    $stmt = $pdo->prepare("
        UPDATE users
        SET wins = wins + 1
        WHERE id = ?
    ");
    $stmt->execute([$userId]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Bingo claimed!',
        'winners' => $winners
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Database error','error'=>$e->getMessage()]);
}