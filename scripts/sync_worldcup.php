<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\WorldCupSyncService;

$service = new WorldCupSyncService();
$scoreboard = $service->syncScoreboard();
echo 'Scoreboard: ' . json_encode($scoreboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

try {
    $squads = $service->syncWikipediaSquads();
    echo 'Squads: ' . json_encode($squads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    echo 'Squads sync failed: ' . $error->getMessage() . PHP_EOL;
}
