<?php
session_name('Bingo');
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

// 1️⃣ Validate session
if (!isset($_SESSION['game_id'], $_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Session invalid']);
    exit;
}

$gameId = (int)$_SESSION['game_id'];
$userId = (int)$_SESSION['user_id'];

// 2️⃣ Read input
$input = json_decode(file_get_contents('php://input'), true);
$cardIndex = max(0, (int)($input['cardIndex'] ?? 0));
$markedNumbers = $input['markedNumbers'] ?? [];

if (!is_array($markedNumbers)) {
    echo json_encode(['success'=>false,'message'=>'Invalid marked numbers']);
    exit;
}

// Ensure integers only
$markedNumbers = array_map('intval', $markedNumbers);

try {
    // 3️⃣ Start transaction
    $pdo->beginTransaction();

    // -----------------------------
    // Fetch user card (SQL Server OFFSET FETCH)
    // -----------------------------
    $cardIndex = (int)$cardIndex; // ensure integer
    $stmt = $pdo->prepare("
        SELECT id, card_data
        FROM (
            SELECT ROW_NUMBER() OVER (ORDER BY id) AS rn, id, card_data
            FROM user_cards
            WHERE user_id = ? AND game_id = ?
        ) AS t
        WHERE rn = ?
    ");
    $stmt->execute([$userId, $gameId, $cardIndex + 1]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Card not found']);
        exit;
    }

    // -----------------------------
    // Decode card data safely
    // -----------------------------
    $cardDataJson = trim($card['card_data'] ?? '');
    $cardData = !empty($cardDataJson) ? json_decode($cardDataJson, true) : [];
    if (!is_array($cardData)) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Invalid card data']);
        exit;
    }

    // -----------------------------
    // Lock game row (SQL Server style)
    // -----------------------------
    $stmt = $pdo->prepare("
        SELECT pattern, winners, game_winners
        FROM game WITH (UPDLOCK, ROWLOCK)
        WHERE id = ?
    ");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Game not found']);
        exit;
    }

    $pattern = json_decode($game['pattern'] ?? '[]', true);
    $maxWinners = (int)($game['winners'] ?? 1);

    // -----------------------------
    // Build required numbers from pattern (ignore FREE cell)
    // -----------------------------
    $letters = ['B','I','N','G','O'];
    $patternNumbers = [];

    foreach ($pattern as $row => $cols) {
        foreach ($cols as $col => $val) {
            if ($val == 1 && !($row == 2 && $col == 2)) { // skip FREE
                $n = $cardData[$letters[$col]][$row] ?? null;
                if ($n !== null) $patternNumbers[] = (int)$n;
            }
        }
    }

    // -----------------------------
    // Validate bingo pattern
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
    $userName = $stmt->fetchColumn() ?? "Unknown";

    // -----------------------------
    // Decode current winners
    // -----------------------------
    $winners = [];
    if (!empty($game['game_winners'])) {
        $decoded = json_decode($game['game_winners'], true);
        if (is_array($decoded)) $winners = $decoded;
    }

    if (count($winners) >= $maxWinners) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'All winners already claimed']);
        exit;
    }

    if (!in_array($userName, $winners)) {
        $winners[] = $userName;
    }

    // -----------------------------
    // Check and mark card as claimed (cast bit to int)
    // -----------------------------
    $stmt = $pdo->prepare("
        SELECT CAST(claimed AS int) AS claimed
        FROM game_winner_queue
        WHERE game_id = ? AND card_id = ?
    ");
    $stmt->execute([$gameId, $card['id']]);
    $claimed = (int)$stmt->fetchColumn();

    if ($claimed === 1) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Card already claimed']);
        exit;
    }

    // Update claimed
    $stmt = $pdo->prepare("
        UPDATE game_winner_queue
        SET claimed = 1
        WHERE game_id = ? AND card_id = ?
    ");
    $stmt->execute([$gameId, $card['id']]);

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

    // ✅ Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Bingo claimed!',
        'winners' => $winners
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Build detailed error info
    $errorDetails = [
        'message' => $e->getMessage(),           // Main error message
        'code'    => $e->getCode(),              // PDO error code
    ];

    // Include SQLSTATE if available
    if ($e instanceof PDOException && isset($e->errorInfo)) {
        $errorDetails['sqlstate'] = $e->errorInfo[0] ?? null;
        $errorDetails['driverCode'] = $e->errorInfo[1] ?? null;
        $errorDetails['driverMessage'] = $e->errorInfo[2] ?? null;
    }

    // Optional: include stack trace in development
    $errorDetails['trace'] = $e->getTraceAsString();

    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $errorDetails
    ]);
}