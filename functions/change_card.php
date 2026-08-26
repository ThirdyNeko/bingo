<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('Bingo'); // must match the name set in index.php/login, or this reads a different session entirely
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// bingo_functions.php lives in the separate bingo_admin project, not
// this one — players (bingo) and admin (bingo_admin) are sibling
// folders under htdocs. __DIR__ anchors this to the script's own
// location regardless of include_path or working directory, so this
// can't fail the way a bare 'bingo_functions.php' require just did.
require_once __DIR__ . '/../../bingo_admin/functions/bingo_functions.php';

header('Content-Type: application/json');

// NOTE: this assumes the logged-in player's id is in $_SESSION['user_id'].
// Adjust to match however your auth actually stores it if that's wrong.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Release the session lock right away — nothing below this line reads
// or writes $_SESSION again, and holding it open would otherwise force
// this request to queue behind any other request on the same session
// (e.g. a long-polling endpoint) until that one finishes.
session_write_close();

$input = json_decode(file_get_contents('php://input'), true);
$cardIndex = isset($input['cardIndex']) ? (int) $input['cardIndex'] : null;

if ($cardIndex === null || $cardIndex < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid card.']);
    exit;
}

// Find the player's active game
$userStmt = $pdo->prepare("SELECT current_game FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$gameId = $userStmt->fetchColumn();

if (!$gameId) {
    echo json_encode(['success' => false, 'message' => 'No active game.']);
    exit;
}

$gameStmt = $pdo->prepare("SELECT * FROM game WHERE id = ?");
$gameStmt->execute([$gameId]);
$game = $gameStmt->fetch();

if (!$game) {
    echo json_encode(['success' => false, 'message' => 'Game not found.']);
    exit;
}

// 🚫 Server-side enforcement: only while the game has started AND the
// card-change window hasn't closed yet. Mirrors the check in screen.php —
// this is the check that actually matters, since the UI check is only
// a display convenience.
$cardChangeDeadline = $game['card_change_deadline'] ?? null;
$inCardChangeWindow = (int) $game['started'] === 1
    && $cardChangeDeadline
    && strtotime($cardChangeDeadline) > time();

if (!$inCardChangeWindow) {
    echo json_encode(['success' => false, 'message' => 'Card changes are no longer allowed.']);
    exit;
}

// Same id-ascending ordering claim_bingo.php relies on to map
// cardIndex -> a specific user_cards row.
$cardsStmt = $pdo->prepare("
    SELECT id, card_data, card_changed
    FROM user_cards
    WHERE user_id = ? AND game_id = ?
    ORDER BY id ASC
");
$cardsStmt->execute([$userId, $gameId]);
$cards = $cardsStmt->fetchAll();

if (!isset($cards[$cardIndex])) {
    echo json_encode(['success' => false, 'message' => 'Card not found.']);
    exit;
}

$cardRow = $cards[$cardIndex];

// 🚫 One change per card, ever — not one per window.
if ((int) $cardRow['card_changed'] === 1) {
    echo json_encode(['success' => false, 'message' => 'You can only change this card once.']);
    exit;
}

$cardData = json_decode($cardRow['card_data'], true);
$pattern = json_decode($game['pattern'], true);

// Only the neutral (non-pattern, non-FREE) cells get new numbers —
// the pattern cells (which may hold a winner's shared number) are
// left exactly as they were.
$newCardData = regenerateNeutralNumbers($cardData, $pattern);

$updateStmt = $pdo->prepare("
    UPDATE user_cards
    SET card_data = ?, card_changed = 1
    WHERE id = ?
");
$updateStmt->execute([json_encode($newCardData), $cardRow['id']]);

echo json_encode([
    'success'  => true,
    'cardData' => $newCardData,
]);