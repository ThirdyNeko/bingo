<?php
/*
 * game_cache.php — NO-OP version.
 *
 * Static-file polling is not in use, so these functions do nothing. They stay
 * only so draw_number.php and claim_bingo.php (which call them) keep working
 * without edits. If you ever want the cache back, swap in the real version.
 */

function game_cache_path($gameId)
{
    return __DIR__ . '/../cache/game_' . (int) $gameId . '.json';
}

function read_game_cache($gameId)
{
    return false;
}

function write_game_cache($gameId, array $drawn, $visibleAt, $over)
{
    return true;
}