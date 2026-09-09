<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\ArkClient;

$options = getopt('', ['limit::', 'batch::', 'dry-run']);
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 0;
$batchSize = isset($options['batch']) ? max(3, min(50, (int) $options['batch'])) : 20;
$dryRun = array_key_exists('dry-run', $options);

$client = new ArkClient();
if (!$client->textReady()) {
    fwrite(STDERR, "ARK text model is not configured. Set ARK_API_KEY and ARK_MODEL in .env first.\n");
    exit(1);
}

$pdo = Database::pdo();
$sql = 'SELECT id, name_original FROM players WHERE name_cn IS NULL OR name_cn = "" ORDER BY team_id, popularity_score DESC, id';
if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}
$rows = $pdo->query($sql)->fetchAll();
if (!$rows) {
    upsertTranslationCheck($pdo, 0, 0, 0);
    echo "No missing player Chinese names.\n";
    exit(0);
}

$update = $pdo->prepare('UPDATE players SET name_cn = ?, source_synced_at = NOW() WHERE id = ? AND (name_cn IS NULL OR name_cn = "")');
$translated = 0;
$skipped = 0;
$failedBatches = 0;

foreach (array_chunk($rows, $batchSize) as $index => $chunk) {
    $items = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'name_original' => (string) $row['name_original'],
    ], $chunk);

    $prompt = "请把下面的2026世界杯球员姓名翻译成简体中文常用音译。要求：只翻译姓名，不添加国籍、位置或说明；不要把英文原样返回；只输出JSON数组，每个元素格式为 {\"id\":数字,\"name_cn\":\"中文名\"}。\n\n" . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $translations = [];
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $response = $client->chat($prompt, '准确、克制、只输出JSON', null, 180);
            $translations = decodeJsonArray($response);
            break;
        } catch (Throwable $error) {
            if ($attempt === 2) {
                $failedBatches++;
                fwrite(STDERR, 'Batch ' . ($index + 1) . ' failed: ' . $error->getMessage() . "\n");
            } else {
                usleep(800000);
            }
        }
    }
    if (!$translations) {
        continue;
    }

    foreach ($translations as $item) {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $nameCn = trim((string) ($item['name_cn'] ?? ''));
        if (!$id || $nameCn === '' || !is_chinese_text($nameCn)) {
            $skipped++;
            continue;
        }
        if (!$dryRun) {
            $update->execute([$nameCn, $id]);
        }
        $translated++;
    }

    echo 'Batch ' . ($index + 1) . '/' . (int) ceil(count($rows) / $batchSize) . " translated={$translated} skipped={$skipped}\n";
    usleep(250000);
}

$remaining = (int) $pdo->query('SELECT COUNT(*) FROM players WHERE name_cn IS NULL OR name_cn = ""')->fetchColumn();
upsertTranslationCheck($pdo, $translated, $skipped + $failedBatches, $remaining);

echo json_encode([
    'requested' => count($rows),
    'translated' => $translated,
    'skipped_or_failed' => $skipped + $failedBatches,
    'remaining_missing' => $remaining,
    'dry_run' => $dryRun,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

/**
 * @return array<int, array<string, mixed>>
 */
function decodeJsonArray(string $text): array
{
    $text = trim($text);
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        if (preg_match('/\[[\s\S]*\]/u', $text, $match)) {
            $decoded = json_decode($match[0], true);
        }
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Model did not return a JSON array.');
    }
    return array_is_list($decoded) ? $decoded : array_values($decoded);
}

function upsertTranslationCheck(PDO $pdo, int $translated, int $skipped, int $remaining): void
{
    $status = $remaining === 0 ? 'pass' : 'warn';
    $stmt = $pdo->prepare(
        'INSERT INTO data_quality_checks (check_key, label, expected_value, actual_value, status, detail, source_url, checked_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           expected_value = VALUES(expected_value),
           actual_value = VALUES(actual_value),
           status = VALUES(status),
           detail = VALUES(detail),
           source_url = VALUES(source_url),
           checked_at = NOW()'
    );
    $stmt->execute([
        'player_name_cn_translation',
        '球员中文名翻译补全',
        '0 missing',
        (string) $remaining,
        $status,
        "本次翻译 {$translated} 条，跳过或失败 {$skipped} 条。翻译由本地配置的 ARK 文本模型生成，原名仍保留在 name_original。",
        'https://www.volcengine.com/docs/82379',
    ]);
}
