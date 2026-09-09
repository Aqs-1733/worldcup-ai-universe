<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Repositories\AppRepository;

final class CollectedWorldCupImporter
{
    private PDO $pdo;
    private AppRepository $repo;
    private string $root;
    private string $dataRoot;

    /** @var array<int, int> */
    private array $teamBySourceId = [];
    /** @var array<string, int> */
    private array $teamByCode = [];
    /** @var array<int, string> */
    private array $teamCodeBySourceId = [];
    /** @var array<int, int> */
    private array $venueBySourceId = [];
    /** @var array<int, int> */
    private array $matchBySourceId = [];
    /** @var array<int, int> */
    private array $playerBySourceId = [];
    /** @var array<int, string> */
    private array $stageBySourceId = [];

    public function __construct(string $root)
    {
        $this->pdo = Database::pdo();
        $this->repo = new AppRepository();
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->dataRoot = $this->root . '/data';
    }

    /** @return array<string, mixed> */
    public function run(bool $fresh = true): array
    {
        if (!is_dir($this->dataRoot)) {
            throw new RuntimeException("Collected data folder not found: {$this->dataRoot}");
        }

        $this->ensureWarehouseSchema();
        if ($fresh) {
            $this->clearImportedSportsData();
        }

        $result = [
            'sources' => $this->importSourceCatalog(),
            'raw_records' => $this->importRawFiles(),
            'mominullptr' => $this->importMominullptr(),
            'pochih' => $this->importPochih(),
            'ebemad' => $this->importEbEmad(),
            'alamyy' => $this->importAlamyy(),
        ];
        $this->rebuildStandings();
        $result['quality_checks'] = $this->validateImportedData();
        $result['database_counts'] = $this->tableCounts([
            'teams',
            'players',
            'matches',
            'venues',
            'match_stats',
            'player_statistics',
            'match_lineups',
            'match_events',
            'source_files',
            'source_records',
            'data_quality_checks',
        ]);

        return $result;
    }

    private function ensureWarehouseSchema(): void
    {
        if (!$this->columnExists('teams', 'flag_url')) {
            $this->pdo->exec('ALTER TABLE teams ADD COLUMN flag_url VARCHAR(800) NULL AFTER flag_emoji');
        }

        $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
            if (str_starts_with(strtoupper($statement), 'CREATE TABLE')) {
                $this->pdo->exec($statement);
            }
        }
    }

    private function clearImportedSportsData(): void
    {
        $tables = [
            'user_favorite_players',
            'user_favorite_teams',
            'user_blocked_teams',
            'standings',
            'match_events',
            'match_lineups',
            'match_prediction_features',
            'match_stats',
            'player_statistics',
            'matches',
            'players',
            'coaches',
            'referees',
            'tournament_stages',
            'venues',
            'teams',
            'source_records',
            'source_files',
            'data_quality_checks',
        ];

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                if ($this->tableExists($table)) {
                    $this->pdo->exec("TRUNCATE TABLE {$table}");
                }
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function importSourceCatalog(): int
    {
        $path = $this->root . '/sources.csv';
        if (!is_file($path)) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO data_sources (source_key, name, url, source_type, trust_level, last_synced_at, last_status)
             VALUES (?, ?, ?, ?, ?, NOW(), "listed")
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               url = VALUES(url),
               source_type = VALUES(source_type),
               trust_level = VALUES(trust_level),
               last_synced_at = NOW(),
               last_status = "listed"'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $name = (string) ($row['source_name'] ?? 'source');
            $category = (string) ($row['category'] ?? 'api');
            $stmt->execute([
                $this->slug($name),
                $name,
                (string) ($row['url'] ?? ''),
                $this->sourceType($category),
                $this->trustLevel($category),
            ]);
            $count++;
        }
        return $count;
    }

    private function importRawFiles(): int
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dataRoot, FilesystemIterator::SKIP_DOTS));
        $inserted = 0;

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['csv', 'json'], true)) {
                continue;
            }

            $fileId = $this->upsertSourceFile($path, $extension);
            $this->pdo->prepare('DELETE FROM source_records WHERE source_file_id = ?')->execute([$fileId]);

            if ($extension === 'csv') {
                $rowNumber = 0;
                $stmt = $this->pdo->prepare('INSERT INTO source_records (source_file_id, row_number, entity_key, record_json) VALUES (?, ?, ?, ?)');
                $this->pdo->beginTransaction();
                try {
                    foreach ($this->csvRows($path) as $row) {
                        $rowNumber++;
                        $stmt->execute([$fileId, $rowNumber, $this->entityKey($row), $this->json($row)]);
                    }
                    $this->pdo->commit();
                } catch (Throwable $error) {
                    $this->pdo->rollBack();
                    throw $error;
                }
                $this->setSourceFileRowCount($fileId, $rowNumber);
                $inserted += $rowNumber;
                continue;
            }

            $rows = $this->jsonRows($path);
            $stmt = $this->pdo->prepare('INSERT INTO source_records (source_file_id, row_number, entity_key, record_json) VALUES (?, ?, ?, ?)');
            $this->pdo->beginTransaction();
            try {
                foreach ($rows as $index => $row) {
                    $stmt->execute([$fileId, $index + 1, is_array($row) ? $this->entityKey($row) : null, $this->json($row)]);
                }
                $this->pdo->commit();
            } catch (Throwable $error) {
                $this->pdo->rollBack();
                throw $error;
            }
            $this->setSourceFileRowCount($fileId, count($rows));
            $inserted += count($rows);
        }

        return $inserted;
    }

    /** @return array<string, int> */
    private function importMominullptr(): array
    {
        $base = $this->dataRoot . '/open_datasets/huggingface_mominullptr';
        if (!is_dir($base)) {
            return [];
        }

        return [
            'teams' => $this->importMominullptrTeams($base . '/teams.csv'),
            'venues' => $this->importMominullptrVenues($base . '/venues.csv'),
            'stages' => $this->importMominullptrStages($base . '/tournament_stages.csv'),
            'referees' => $this->importMominullptrReferees($base . '/referees.csv'),
            'matches' => $this->importMominullptrMatches($base . '/matches.csv'),
            'players' => $this->importMominullptrPlayers($base . '/squads_and_players.csv'),
            'match_stats' => $this->importMominullptrMatchStats($base . '/match_team_stats.csv'),
            'player_statistics' => $this->importMominullptrPlayerStats($base . '/player_stats.csv'),
            'lineups' => $this->importMominullptrLineups($base . '/match_lineups.csv'),
            'events' => $this->importMominullptrEvents($base . '/match_events.csv'),
            'prediction_features' => $this->importPredictionFeatures($base . '/match_prediction_features.csv'),
        ];
    }

    private function importMominullptrTeams(string $path): int
    {
        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['team_id'];
            $code = strtoupper((string) $row['fifa_code']);
            $teamId = $this->repo->upsertTeam([
                'fifa_id' => 'mominullptr-team-' . $sourceId,
                'slug' => $this->slug((string) $row['team_name']),
                'code' => $code,
                'name_cn' => $this->teamNameCn((string) $row['team_name'], $code),
                'name_original' => (string) $row['team_name'],
                'country_code' => $this->countryAlpha2($code),
                'flag_emoji' => $this->flagEmoji($this->countryAlpha2($code)),
                'flag_url' => $this->flagUrl($code),
                'confederation' => $row['confederation'] ?? null,
                'group_name' => $row['group_letter'] ?? null,
                'coach_name' => $row['manager_name'] ?? null,
                'world_ranking' => $this->intOrNull($row['fifa_ranking_pre_tournament'] ?? null),
                'profile' => $this->profileLine($row),
                'source_url' => $this->sourceUrl('mominullptr'),
            ]);
            $this->teamBySourceId[$sourceId] = $teamId;
            $this->teamByCode[$code] = $teamId;
            $this->teamCodeBySourceId[$sourceId] = $code;
            $count++;
        }
        return $count;
    }

    private function importMominullptrVenues(string $path): int
    {
        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['venue_id'];
            $venueId = $this->repo->upsertVenue([
                'fifa_id' => 'mominullptr-venue-' . $sourceId,
                'name_cn' => $this->venueNameCn((string) $row['stadium_name']),
                'name_original' => (string) $row['stadium_name'],
                'city_cn' => $this->cityNameCn((string) $row['city']),
                'city_original' => (string) $row['city'],
                'country_code' => $this->countryAlpha2((string) $row['country']),
                'capacity' => $this->intOrNull($row['capacity'] ?? null),
                'source_url' => $this->sourceUrl('mominullptr'),
            ]);
            $this->venueBySourceId[$sourceId] = $venueId;
            $count++;
        }
        return $count;
    }

    private function importMominullptrStages(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tournament_stages (external_id, name, name_cn, stage_order, is_knockout, source_url, source_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               name_cn = VALUES(name_cn),
               stage_order = VALUES(stage_order),
               is_knockout = VALUES(is_knockout),
               source_url = VALUES(source_url),
               source_synced_at = NOW()'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $stageId = (int) $row['stage_id'];
            $name = (string) $row['stage_name'];
            $this->stageBySourceId[$stageId] = $this->stageNameCn($name);
            $stmt->execute([
                'mominullptr-stage-' . $stageId,
                $name,
                $this->stageNameCn($name),
                $stageId,
                $this->boolInt($row['is_knockout'] ?? false),
                $this->sourceUrl('mominullptr'),
            ]);
            $count++;
        }
        return $count;
    }

    private function importMominullptrReferees(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO referees (external_id, name_original, country_code, avg_cards_per_game, source_url, source_synced_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               name_original = VALUES(name_original),
               country_code = VALUES(country_code),
               avg_cards_per_game = VALUES(avg_cards_per_game),
               source_url = VALUES(source_url),
               source_synced_at = NOW()'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $stmt->execute([
                'mominullptr-referee-' . (int) $row['referee_id'],
                (string) $row['name'],
                strtoupper(substr((string) $row['country'], 0, 3)),
                $this->floatOrNull($row['avg_cards_per_game'] ?? null),
                $this->sourceUrl('mominullptr'),
            ]);
            $count++;
        }
        return $count;
    }

    private function importMominullptrMatches(string $path): int
    {
        $official = $this->dataRoot . '/open_datasets/pochih_worldcup2026/worldcup2026-master/data/schedule.json';
        if (is_file($official)) {
            $count = $this->importPochihMatches($official);
            $this->mapMominullptrMatchesToOfficial($path);
            return $count;
        }

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['match_id'];
            $stageName = $this->stageBySourceId[(int) $row['stage_id']] ?? '世界杯';
            $homeTeamId = $this->teamBySourceId[(int) $row['home_team_id']] ?? null;
            $awayTeamId = $this->teamBySourceId[(int) $row['away_team_id']] ?? null;
            $matchId = $this->repo->upsertMatch([
                'external_id' => 'mominullptr-match-' . $sourceId,
                'stage' => $stageName,
                'group_name' => $stageName === '小组赛' ? $this->groupForTeamId($homeTeamId) : null,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'home_score' => $this->intOrNull($row['home_score'] ?? null),
                'away_score' => $this->intOrNull($row['away_score'] ?? null),
                'home_penalty_score' => $this->intOrNull($row['home_penalty_score'] ?? null),
                'away_penalty_score' => $this->intOrNull($row['away_penalty_score'] ?? null),
                'status' => $this->matchStatus((string) ($row['status'] ?? 'Completed')),
                'starts_at' => $this->dateTime((string) $row['date'], (string) $row['kickoff_time_utc']),
                'venue_id' => $this->venueBySourceId[(int) $row['venue_id']] ?? null,
                'source_url' => $this->sourceUrl('mominullptr'),
            ]);
            $this->matchBySourceId[$sourceId] = $matchId;
            $count++;
        }
        return $count;
    }

    private function importPochihMatches(string $path): int
    {
        $payload = json_decode((string) file_get_contents($path), true);
        $matches = is_array($payload) ? ($payload['matches'] ?? []) : [];
        $sourceUrls = $this->alamyySourceUrlsByMatchNumber();
        $count = 0;

        foreach ($matches as $match) {
            if (!is_array($match) || empty($match['no'])) {
                continue;
            }
            $no = (int) $match['no'];
            $home = is_array($match['home'] ?? null) ? $match['home'] : [];
            $away = is_array($match['away'] ?? null) ? $match['away'] : [];
            $homeCode = strtoupper((string) ($home['code'] ?? ''));
            $awayCode = strtoupper((string) ($away['code'] ?? ''));
            $homeTeamId = $this->teamByCode[$homeCode] ?? null;
            $awayTeamId = $this->teamByCode[$awayCode] ?? null;
            $stage = $this->stageFromPochih($match);
            $venueId = $this->venueIdByName((string) ($match['venue'] ?? ''));

            $matchId = $this->repo->upsertMatch([
                'external_id' => 'worldcup-match-' . sprintf('%03d', $no),
                'stage' => $stage,
                'group_name' => $match['group'] ?? null,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'home_team_name' => $home['name'] ?? null,
                'away_team_name' => $away['name'] ?? null,
                'home_score' => $this->intOrNull($home['score'] ?? null),
                'away_score' => $this->intOrNull($away['score'] ?? null),
                'home_penalty_score' => $this->intOrNull($home['pen'] ?? null),
                'away_penalty_score' => $this->intOrNull($away['pen'] ?? null),
                'status' => isset($home['score'], $away['score']) ? 'finished' : 'scheduled',
                'starts_at' => !empty($match['utc']) ? date('Y-m-d H:i:s', strtotime((string) $match['utc'])) : null,
                'venue_id' => $venueId,
                'source_url' => $sourceUrls[$no] ?? $this->sourceUrl('pochih_schedule'),
            ]);
            $this->matchBySourceId[$no] = $matchId;
            $count++;
        }

        return $count;
    }

    private function mapMominullptrMatchesToOfficial(string $path): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM matches
             WHERE home_team_id = ?
               AND away_team_id = ?
               AND home_score <=> ?
               AND away_score <=> ?
               AND DATE(starts_at) = ?
             LIMIT 1'
        );
        $fallback = $this->pdo->prepare(
            'SELECT id FROM matches
             WHERE home_team_id = ?
               AND away_team_id = ?
               AND stage = ?
             ORDER BY ABS(TIMESTAMPDIFF(HOUR, starts_at, ?))
             LIMIT 1'
        );

        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['match_id'];
            $homeTeamId = $this->teamBySourceId[(int) $row['home_team_id']] ?? null;
            $awayTeamId = $this->teamBySourceId[(int) $row['away_team_id']] ?? null;
            if (!$homeTeamId || !$awayTeamId) {
                continue;
            }
            $date = (string) ($row['date'] ?? '');
            $startsAt = $this->dateTime($date, (string) ($row['kickoff_time_utc'] ?? '00:00'));
            $stmt->execute([
                $homeTeamId,
                $awayTeamId,
                $this->intOrNull($row['home_score'] ?? null),
                $this->intOrNull($row['away_score'] ?? null),
                $date,
            ]);
            $matchId = (int) ($stmt->fetchColumn() ?: 0);
            if (!$matchId) {
                $fallback->execute([
                    $homeTeamId,
                    $awayTeamId,
                    $this->stageBySourceId[(int) $row['stage_id']] ?? '世界杯',
                    $startsAt,
                ]);
                $matchId = (int) ($fallback->fetchColumn() ?: 0);
            }
            if ($matchId) {
                $this->matchBySourceId[$sourceId] = $matchId;
            }
        }
    }

    private function importMominullptrPlayers(string $path): int
    {
        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['player_id'];
            $teamId = $this->teamBySourceId[(int) $row['team_id']] ?? null;
            $playerId = $this->repo->upsertPlayer([
                'team_id' => $teamId,
                'fifa_id' => 'mominullptr-player-' . $sourceId,
                'slug' => $this->slug('mominullptr-' . $sourceId . '-' . (string) $row['player_name']),
                'name_original' => (string) $row['player_name'],
                'position' => $this->positionCode((string) ($row['position'] ?? '')),
                'shirt_number' => (($sourceId - 1) % 26) + 1,
                'birth_date' => $this->dateOnly($row['date_of_birth'] ?? null),
                'club' => $row['club_team'] ?? null,
                'caps' => $this->intOrNull($row['caps'] ?? null),
                'goals' => $this->intOrNull($row['goals'] ?? null),
                'height_cm' => $this->intOrNull($row['height_cm'] ?? null),
                'popularity_score' => $this->popularityScore($row),
                'source_url' => $this->sourceUrl('mominullptr'),
            ]);
            $this->playerBySourceId[$sourceId] = $playerId;
            $count++;
        }
        return $count;
    }

    private function importMominullptrMatchStats(string $path): int
    {
        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $matchId = $this->matchBySourceId[(int) $row['match_id']] ?? null;
            $teamId = $this->teamBySourceId[(int) $row['team_id']] ?? null;
            if (!$matchId || !$teamId) {
                continue;
            }
            $this->repo->upsertMatchStats($matchId, $teamId, [
                'possession' => $this->floatOrNull($row['possession_pct'] ?? null),
                'shots' => $this->intOrNull($row['total_shots'] ?? null),
                'shots_on_target' => $this->intOrNull($row['shots_on_target'] ?? null),
                'corners' => $this->intOrNull($row['corners'] ?? null),
                'fouls' => $this->intOrNull($row['fouls'] ?? null),
            ], $this->sourceUrl('mominullptr'));
            $count++;
        }
        return $count;
    }

    private function importMominullptrPlayerStats(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO player_statistics (player_id, external_player_id, matches_played, matches_started, minutes_played, goals, assists, shots, shots_on_target, yellow_cards, red_cards, penalty_goals, own_goals, clean_sheets, saves, goals_conceded, average_rating, data_source, source_url, source_synced_at, raw_payload_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
               player_id = VALUES(player_id),
               matches_played = VALUES(matches_played),
               matches_started = VALUES(matches_started),
               minutes_played = VALUES(minutes_played),
               goals = VALUES(goals),
               assists = VALUES(assists),
               shots = VALUES(shots),
               shots_on_target = VALUES(shots_on_target),
               yellow_cards = VALUES(yellow_cards),
               red_cards = VALUES(red_cards),
               penalty_goals = VALUES(penalty_goals),
               own_goals = VALUES(own_goals),
               clean_sheets = VALUES(clean_sheets),
               saves = VALUES(saves),
               goals_conceded = VALUES(goals_conceded),
               average_rating = VALUES(average_rating),
               data_source = VALUES(data_source),
               source_url = VALUES(source_url),
               source_synced_at = NOW(),
               raw_payload_json = VALUES(raw_payload_json)'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['player_id'];
            $stmt->execute([
                $this->playerBySourceId[$sourceId] ?? null,
                'mominullptr-player-' . $sourceId,
                $this->intOrNull($row['matches_played'] ?? null),
                $this->intOrNull($row['matches_started'] ?? null),
                $this->intOrNull($row['minutes_played'] ?? null),
                $this->intOrNull($row['goals'] ?? null),
                $this->intOrNull($row['assists'] ?? null),
                $this->intOrNull($row['shots'] ?? null),
                $this->intOrNull($row['shots_on_target'] ?? null),
                $this->intOrNull($row['yellow_cards'] ?? null),
                $this->intOrNull($row['red_cards'] ?? null),
                $this->intOrNull($row['penalty_goals'] ?? null),
                $this->intOrNull($row['own_goals'] ?? null),
                $this->intOrNull($row['clean_sheets'] ?? null),
                $this->intOrNull($row['saves'] ?? null),
                $this->intOrNull($row['goals_conceded'] ?? null),
                $this->floatOrNull($row['average_rating'] ?? null),
                $row['data_source'] ?? null,
                $this->sourceUrl('mominullptr'),
                $this->json($row),
            ]);
            $count++;
        }
        return $count;
    }

    private function importMominullptrLineups(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_lineups (external_lineup_id, match_id, player_id, team_id, is_starting_xi, tactical_position, minutes_played, source_url, source_synced_at, raw_payload_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
               match_id = VALUES(match_id),
               player_id = VALUES(player_id),
               team_id = VALUES(team_id),
               is_starting_xi = VALUES(is_starting_xi),
               tactical_position = VALUES(tactical_position),
               minutes_played = VALUES(minutes_played),
               source_url = VALUES(source_url),
               source_synced_at = NOW(),
               raw_payload_json = VALUES(raw_payload_json)'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $stmt->execute([
                'mominullptr-lineup-' . (int) $row['lineup_id'],
                $this->matchBySourceId[(int) $row['match_id']] ?? null,
                $this->playerBySourceId[(int) $row['player_id']] ?? null,
                $this->teamBySourceId[(int) $row['team_id']] ?? null,
                $this->boolInt($row['is_starting_xi'] ?? false),
                $row['tactical_position'] ?? null,
                $this->intOrNull($row['minutes_played'] ?? null),
                $this->sourceUrl('mominullptr'),
                $this->json($row),
            ]);
            $count++;
        }
        return $count;
    }

    private function importMominullptrEvents(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_events (external_event_id, match_id, minute, event_type, team_id, player_id, source_url, source_synced_at, raw_payload_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE
               match_id = VALUES(match_id),
               minute = VALUES(minute),
               event_type = VALUES(event_type),
               team_id = VALUES(team_id),
               player_id = VALUES(player_id),
               source_url = VALUES(source_url),
               source_synced_at = NOW(),
               raw_payload_json = VALUES(raw_payload_json)'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $stmt->execute([
                'mominullptr-event-' . (int) $row['event_id'],
                $this->matchBySourceId[(int) $row['match_id']] ?? null,
                $this->intOrNull($row['minute'] ?? null),
                (string) ($row['event_type'] ?? 'Event'),
                $this->teamBySourceId[(int) $row['team_id']] ?? null,
                $this->playerBySourceId[(int) $row['player_id']] ?? null,
                $this->sourceUrl('mominullptr'),
                $this->json($row),
            ]);
            $count++;
        }
        return $count;
    }

    private function importPredictionFeatures(string $path): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_prediction_features (external_match_id, match_id, home_team_id, away_team_id, feature_payload_json, source_url, source_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               match_id = VALUES(match_id),
               home_team_id = VALUES(home_team_id),
               away_team_id = VALUES(away_team_id),
               feature_payload_json = VALUES(feature_payload_json),
               source_url = VALUES(source_url),
               source_synced_at = NOW()'
        );

        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $sourceId = (int) $row['match_id'];
            $stmt->execute([
                'mominullptr-match-' . $sourceId,
                $this->matchBySourceId[$sourceId] ?? null,
                $this->teamBySourceId[(int) $row['home_team_id']] ?? null,
                $this->teamBySourceId[(int) $row['away_team_id']] ?? null,
                $this->json($row),
                $this->sourceUrl('mominullptr'),
            ]);
            $count++;
        }
        return $count;
    }

    /** @return array<string, int> */
    private function importPochih(): array
    {
        $base = $this->dataRoot . '/open_datasets/pochih_worldcup2026/worldcup2026-master/data';
        if (!is_dir($base)) {
            return [];
        }

        return [
            'schedule_flags' => $this->enrichFlagsFromPochihSchedule($base . '/schedule.json'),
            'roster_players' => $this->enrichPlayersFromPochihRosters($base . '/rosters.json'),
        ];
    }

    private function enrichFlagsFromPochihSchedule(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        $matches = is_array($payload) ? ($payload['matches'] ?? []) : [];
        $stmt = $this->pdo->prepare('UPDATE teams SET flag_url = COALESCE(?, flag_url), source_synced_at = NOW() WHERE code = ?');
        $count = 0;
        foreach ($matches as $match) {
            foreach (['home', 'away'] as $side) {
                $team = $match[$side] ?? null;
                if (!is_array($team) || empty($team['code'])) {
                    continue;
                }
                $stmt->execute([
                    $team['flag'] ?? $this->flagUrl((string) $team['code']),
                    strtoupper((string) $team['code']),
                ]);
                $count++;
            }
        }
        return $count;
    }

    private function enrichPlayersFromPochihRosters(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return 0;
        }

        $updated = 0;
        foreach ($payload as $code => $team) {
            if (!is_array($team) || str_starts_with((string) $code, '_')) {
                continue;
            }
            $teamId = $this->teamByCode[strtoupper((string) $code)] ?? $this->teamIdByCode((string) $code);
            if (!$teamId) {
                continue;
            }
            foreach (($team['players'] ?? []) as $player) {
                if (!is_array($player)) {
                    continue;
                }
                $shirt = $this->intOrNull($player['shirt'] ?? null);
                $localPlayerId = $shirt ? $this->playerIdByTeamAndShirt($teamId, $shirt) : null;
                $localPlayerId = $localPlayerId ?: $this->playerIdByTeamAndName($teamId, (string) ($player['name'] ?? ''));
                $fifaId = !empty($player['id']) ? 'fifa-' . $player['id'] : null;
                if ($localPlayerId) {
                    $this->safeUpdatePlayerFifaId($localPlayerId, $fifaId);
                    $this->pdo->prepare(
                        'UPDATE players
                         SET name_cn = COALESCE(NULLIF(?, ""), name_cn),
                             name_original = CASE WHEN CHAR_LENGTH(name_original) <= 3 THEN NULLIF(?, "") ELSE name_original END,
                             position = COALESCE(NULLIF(?, ""), position),
                             shirt_number = COALESCE(shirt_number, ?),
                             photo_url = COALESCE(NULLIF(?, ""), photo_url),
                             source_url = ?,
                             source_synced_at = NOW()
                         WHERE id = ?'
                    )->execute([
                        $player['nameZh'] ?? '',
                        $player['name'] ?? '',
                        $player['pos'] ?? '',
                        $shirt,
                        $player['picture'] ?? '',
                        $this->sourceUrl('pochih_rosters'),
                        $localPlayerId,
                    ]);
                    $updated++;
                }
            }
        }
        return $updated;
    }

    /** @return array<string, int> */
    private function importEbEmad(): array
    {
        $base = $this->dataRoot . '/open_datasets/EbEmad_FIFA-Data-Wc-2026/FIFA-Data-Wc-2026-main/data';
        if (!is_dir($base)) {
            return [];
        }

        return [
            'players_enriched' => $this->enrichPlayersFromEbEmad($base . '/wc2026_players.csv'),
        ];
    }

    private function enrichPlayersFromEbEmad(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $updated = 0;
        foreach ($this->csvRows($path) as $row) {
            $teamId = $this->teamIdByOriginalName((string) ($row['team_name'] ?? ''));
            if (!$teamId) {
                continue;
            }
            $shirt = $this->intOrNull($row['player_number'] ?? null);
            $localPlayerId = $shirt ? $this->playerIdByTeamAndShirt($teamId, $shirt) : null;
            $localPlayerId = $localPlayerId ?: $this->playerIdByTeamAndName($teamId, (string) ($row['player_name'] ?? ''));
            if (!$localPlayerId) {
                continue;
            }
            $this->safeUpdatePlayerFifaId($localPlayerId, !empty($row['player_id']) ? 'fifa-' . $row['player_id'] : null);
            $this->pdo->prepare(
                'UPDATE players
                 SET birth_date = COALESCE(?, birth_date),
                     position = COALESCE(NULLIF(?, ""), position),
                     shirt_number = COALESCE(shirt_number, ?),
                     source_synced_at = NOW()
                 WHERE id = ?'
            )->execute([
                $this->dateOnly($row['birthday'] ?? null),
                $this->positionCode((string) ($row['position'] ?? '')),
                $shirt,
                $localPlayerId,
            ]);
            $updated++;
        }
        return $updated;
    }

    /** @return array<string, int> */
    private function importAlamyy(): array
    {
        $base = $this->dataRoot . '/open_datasets/Alamyy_Worldcup26/Worldcup26-main/data/csv';
        if (!is_dir($base)) {
            return [];
        }

        return [
            'match_source_links' => $this->enrichMatchSourcesFromAlamyy($base . '/matches.csv'),
        ];
    }

    private function enrichMatchSourcesFromAlamyy(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }

        $stmt = $this->pdo->prepare('UPDATE matches SET source_url = COALESCE(NULLIF(?, ""), source_url), source_synced_at = NOW() WHERE external_id = ?');
        $count = 0;
        foreach ($this->csvRows($path) as $row) {
            $matchNumber = $this->intOrNull($row['match_number'] ?? null);
            if (!$matchNumber) {
                continue;
            }
            $stmt->execute([(string) ($row['source_url'] ?? ''), 'worldcup-match-' . sprintf('%03d', $matchNumber)]);
            $count++;
        }
        return $count;
    }

    private function rebuildStandings(): void
    {
        $this->pdo->exec('DELETE FROM standings');
        $teams = $this->pdo->query('SELECT id, group_name FROM teams WHERE group_name IS NOT NULL')->fetchAll();
        $insert = $this->pdo->prepare(
            'INSERT INTO standings (team_id, group_name, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, source_url, source_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($teams as $team) {
            $teamId = (int) $team['id'];
            $rows = $this->pdo->prepare(
                'SELECT home_team_id, away_team_id, home_score, away_score
                 FROM matches
                 WHERE stage = "小组赛"
                   AND status = "finished"
                   AND (home_team_id = ? OR away_team_id = ?)'
            );
            $rows->execute([$teamId, $teamId]);

            $stats = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0, 'points' => 0];
            foreach ($rows->fetchAll() as $match) {
                if ($match['home_score'] === null || $match['away_score'] === null) {
                    continue;
                }
                $isHome = (int) $match['home_team_id'] === $teamId;
                $for = (int) ($isHome ? $match['home_score'] : $match['away_score']);
                $against = (int) ($isHome ? $match['away_score'] : $match['home_score']);
                $stats['played']++;
                $stats['gf'] += $for;
                $stats['ga'] += $against;
                if ($for > $against) {
                    $stats['won']++;
                    $stats['points'] += 3;
                } elseif ($for === $against) {
                    $stats['drawn']++;
                    $stats['points'] += 1;
                } else {
                    $stats['lost']++;
                }
            }

            $insert->execute([
                $teamId,
                (string) $team['group_name'],
                $stats['played'],
                $stats['won'],
                $stats['drawn'],
                $stats['lost'],
                $stats['gf'],
                $stats['ga'],
                $stats['gf'] - $stats['ga'],
                $stats['points'],
                $this->sourceUrl('mominullptr'),
            ]);
        }
    }

    private function validateImportedData(): int
    {
        $checks = [
            ['team_count', '球队数量', '48', (string) $this->countTable('teams'), $this->countTable('teams') === 48],
            ['player_count', '最终注册球员数量', '1248', (string) $this->countTable('players'), $this->countTable('players') === 1248],
            ['match_count', '比赛数量', '104', (string) $this->countTable('matches'), $this->countTable('matches') === 104],
            ['venue_count', '场馆数量', '16', (string) $this->countTable('venues'), $this->countTable('venues') >= 16],
            ['team_roster_size', '每队 26 人名单', '48 teams x 26', (string) $this->badRosterTeamCount(), $this->badRosterTeamCount() === 0],
            ['match_score_consistency', 'Pochih/FIFA 与 Alamyy 赛果交叉检查', '0 mismatch', (string) $this->scoreMismatchCount(), $this->scoreMismatchCount() === 0],
            ['flag_urls', '球队图片国旗', '48', (string) $this->countWhere('teams', 'flag_url IS NOT NULL AND flag_url <> ""'), $this->countWhere('teams', 'flag_url IS NOT NULL AND flag_url <> ""') === 48],
            ['raw_records', '原始 CSV/JSON 行入库', '> 90000', (string) $this->countTable('source_records'), $this->countTable('source_records') > 90000],
        ];

        $stmt = $this->pdo->prepare(
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

        foreach ($checks as [$key, $label, $expected, $actual, $passed]) {
            $stmt->execute([
                $key,
                $label,
                $expected,
                $actual,
                $passed ? 'pass' : 'fail',
                $passed ? '已通过自动校验。' : '请打开 source_records 和源文件继续核对。',
                $this->sourceUrl('inventory'),
            ]);
        }

        return count($checks);
    }

    /** @return Generator<int, array<string, string|null>> */
    private function csvRows(string $path): Generator
    {
        if (!is_file($path)) {
            return;
        }
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return;
        }
        try {
            $headers = fgetcsv($handle);
            if (!$headers) {
                return;
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || $values === false) {
                    continue;
                }
                $row = [];
                foreach ($headers as $index => $header) {
                    $value = $values[$index] ?? null;
                    $row[(string) $header] = $value === '' ? null : $value;
                }
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int, mixed> */
    private function jsonRows(string $path): array
    {
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return [['raw' => file_get_contents($path)]];
        }
        if (array_is_list($payload)) {
            return $payload;
        }
        foreach (['matches', 'players', 'teams', 'events'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) {
                return $payload[$key];
            }
        }
        $teamRows = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && !str_starts_with($key, '_') && is_array($value)) {
                $value['_key'] = $key;
                $teamRows[] = $value;
            }
        }
        return $teamRows ?: [$payload];
    }

    private function upsertSourceFile(string $path, string $extension): int
    {
        $relative = $this->relativePath($path);
        $stmt = $this->pdo->prepare(
            'INSERT INTO source_files (dataset_key, source_name, source_path, source_url, file_type, row_count, sha256, imported_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
             ON DUPLICATE KEY UPDATE
               dataset_key = VALUES(dataset_key),
               source_name = VALUES(source_name),
               source_url = VALUES(source_url),
               file_type = VALUES(file_type),
               sha256 = VALUES(sha256),
               imported_at = NOW()'
        );
        $stmt->execute([
            $this->datasetKey($relative),
            basename($path),
            $relative,
            $this->sourceUrlForPath($relative),
            $extension,
            hash_file('sha256', $path) ?: null,
        ]);
        return (int) $this->pdo->query('SELECT id FROM source_files WHERE source_path = ' . $this->pdo->quote($relative) . ' LIMIT 1')->fetchColumn();
    }

    private function setSourceFileRowCount(int $fileId, int $rowCount): void
    {
        $this->pdo->prepare('UPDATE source_files SET row_count = ?, imported_at = NOW() WHERE id = ?')->execute([$rowCount, $fileId]);
    }

    private function safeUpdatePlayerFifaId(int $playerId, ?string $fifaId): void
    {
        if (!$fifaId) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM players WHERE fifa_id = ? LIMIT 1');
        $stmt->execute([$fifaId]);
        $existing = (int) ($stmt->fetchColumn() ?: 0);
        if ($existing && $existing !== $playerId) {
            return;
        }
        $this->pdo->prepare('UPDATE players SET fifa_id = ? WHERE id = ?')->execute([$fifaId, $playerId]);
    }

    private function playerIdByTeamAndShirt(int $teamId, int $shirt): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM players WHERE team_id = ? AND shirt_number = ? LIMIT 1');
        $stmt->execute([$teamId, $shirt]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        return $id ?: null;
    }

    private function playerIdByTeamAndName(int $teamId, string $name): ?int
    {
        $wanted = $this->nameTokens($name);
        if (!$wanted) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, name_original FROM players WHERE team_id = ?');
        $stmt->execute([$teamId]);
        foreach ($stmt->fetchAll() as $row) {
            $candidate = $this->nameTokens((string) $row['name_original']);
            if (!$candidate) {
                continue;
            }
            $hits = array_intersect($wanted, $candidate);
            if (count($hits) === count($wanted)) {
                return (int) $row['id'];
            }
            if (count($wanted) >= 2 && $wanted[count($wanted) - 1] === $candidate[count($candidate) - 1] && in_array($wanted[0], $candidate, true)) {
                return (int) $row['id'];
            }
        }
        return null;
    }

    /** @return array<int, string> */
    private function nameTokens(string $name): array
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $ascii = strtolower((string) preg_replace('/[^a-z]+/', ' ', $ascii));
        return array_values(array_filter(explode(' ', trim($ascii)), static fn (string $token): bool => strlen($token) > 1));
    }

    private function teamIdByCode(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM teams WHERE code = ? LIMIT 1');
        $stmt->execute([strtoupper($code)]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        return $id ?: null;
    }

    private function teamIdByOriginalName(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM teams WHERE name_original = ? OR name_cn = ? LIMIT 1');
        $stmt->execute([$name, $this->teamNameCn($name, '')]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        return $id ?: null;
    }

    private function groupForTeamId(?int $teamId): ?string
    {
        if (!$teamId) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT group_name FROM teams WHERE id = ? LIMIT 1');
        $stmt->execute([$teamId]);
        $group = $stmt->fetchColumn();
        return $group ? (string) $group : null;
    }

    private function badRosterTeamCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM (SELECT team_id, COUNT(*) c FROM players WHERE team_id IS NOT NULL GROUP BY team_id HAVING c <> 26) roster_check')->fetchColumn();
    }

    private function scoreMismatchCount(): int
    {
        $alamyy = $this->dataRoot . '/open_datasets/Alamyy_Worldcup26/Worldcup26-main/data/csv/matches.csv';
        if (!is_file($alamyy)) {
            return 0;
        }
        $mismatches = 0;
        $stmt = $this->pdo->prepare('SELECT home_score, away_score FROM matches WHERE external_id = ? LIMIT 1');
        foreach ($this->csvRows($alamyy) as $row) {
            $matchNumber = $this->intOrNull($row['match_number'] ?? null);
            if (!$matchNumber) {
                continue;
            }
            $stmt->execute(['worldcup-match-' . sprintf('%03d', $matchNumber)]);
            $match = $stmt->fetch();
            if (!$match) {
                $mismatches++;
                continue;
            }
            if ((int) $match['home_score'] !== (int) $row['home_score'] || (int) $match['away_score'] !== (int) $row['away_score']) {
                $mismatches++;
            }
        }
        return $mismatches;
    }

    /** @param array<int, string> $tables @return array<string, int> */
    private function tableCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $this->countTable($table);
        }
        return $counts;
    }

    private function countTable(string $table): int
    {
        return $this->tableExists($table) ? (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() : 0;
    }

    private function countWhere(string $table, string $where): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int, string> */
    private function alamyySourceUrlsByMatchNumber(): array
    {
        $path = $this->dataRoot . '/open_datasets/Alamyy_Worldcup26/Worldcup26-main/data/csv/matches.csv';
        if (!is_file($path)) {
            return [];
        }

        $urls = [];
        foreach ($this->csvRows($path) as $row) {
            $matchNumber = $this->intOrNull($row['match_number'] ?? null);
            if ($matchNumber && !empty($row['source_url'])) {
                $urls[$matchNumber] = (string) $row['source_url'];
            }
        }
        return $urls;
    }

    private function stageFromPochih(array $match): string
    {
        $stage = strtolower((string) ($match['stage'] ?? ''));
        $label = strtolower((string) ($match['stageLabel'] ?? ''));
        return match (true) {
            $stage === 'group' || str_contains($label, 'first') => '小组赛',
            $stage === 'r32' || str_contains($label, 'round of 32') => '32强',
            $stage === 'r16' || str_contains($label, 'round of 16') => '16强',
            $stage === 'qf' || str_contains($label, 'quarter') => '四分之一决赛',
            $stage === 'sf' || str_contains($label, 'semi') => '半决赛',
            $stage === 'third' || str_contains($label, 'bronze') || str_contains($label, 'third') => '季军赛',
            $stage === 'final' || str_contains($label, 'final') => '决赛',
            default => (string) ($match['stageLabel'] ?? '世界杯'),
        };
    }

    private function venueIdByName(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $wanted = $this->normalizeKey($name);
        $stmt = $this->pdo->query('SELECT id, name_original, name_cn FROM venues');
        foreach ($stmt->fetchAll() as $row) {
            $candidate = $this->normalizeKey((string) ($row['name_original'] ?? ''));
            if ($candidate === $wanted || str_contains($candidate, $wanted) || str_contains($wanted, $candidate)) {
                return (int) $row['id'];
            }
        }

        return $this->repo->upsertVenue([
            'fifa_id' => 'pochih-venue-' . $this->slug($name),
            'name_cn' => $this->venueNameCn($name),
            'name_original' => $name,
            'city_cn' => null,
            'city_original' => null,
            'country_code' => null,
            'capacity' => null,
            'source_url' => $this->sourceUrl('pochih_schedule'),
        ]);
    }

    private function normalizeKey(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', $ascii));
    }

    private function sourceType(string $category): string
    {
        return str_contains(strtolower($category), 'official') ? 'official' : 'api';
    }

    private function trustLevel(string $category): int
    {
        $category = strtolower($category);
        return match (true) {
            str_contains($category, 'official') => 100,
            str_contains($category, 'open dataset') => 92,
            str_contains($category, 'stats') => 85,
            default => 80,
        };
    }

    private function sourceUrl(string $key): string
    {
        return [
            'inventory' => 'https://huggingface.co/datasets/Mominullptr/fifa-world-cup-2026-dataset',
            'mominullptr' => 'https://huggingface.co/datasets/Mominullptr/fifa-world-cup-2026-dataset',
            'pochih_schedule' => 'https://raw.githubusercontent.com/pochih/worldcup2026/master/data/schedule.json',
            'pochih_rosters' => 'https://raw.githubusercontent.com/pochih/worldcup2026/master/data/rosters.json',
            'alamyy' => 'https://github.com/Alamyy/Worldcup26',
            'ebemad' => 'https://github.com/EbEmad/FIFA-Data-Wc-2026',
        ][$key] ?? 'https://github.com/Aqs-1733/worldcup-ai-universe';
    }

    private function sourceUrlForPath(string $path): ?string
    {
        return match (true) {
            str_contains($path, 'huggingface_mominullptr') => $this->sourceUrl('mominullptr'),
            str_contains($path, 'pochih_worldcup2026') => 'https://github.com/pochih/worldcup2026',
            str_contains($path, 'Alamyy_Worldcup26') => $this->sourceUrl('alamyy'),
            str_contains($path, 'EbEmad_FIFA-Data-Wc-2026') => $this->sourceUrl('ebemad'),
            str_contains($path, 'fifa_official') => 'https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026',
            default => null,
        };
    }

    private function datasetKey(string $relativePath): string
    {
        return match (true) {
            str_contains($relativePath, 'huggingface_mominullptr') => 'mominullptr_huggingface',
            str_contains($relativePath, 'mominullptr_github') => 'mominullptr_github',
            str_contains($relativePath, 'pochih_worldcup2026') => 'pochih_worldcup2026',
            str_contains($relativePath, 'Alamyy_Worldcup26') => 'alamyy_worldcup26',
            str_contains($relativePath, 'EbEmad_FIFA-Data-Wc-2026') => 'ebemad_wc2026',
            str_contains($relativePath, 'fifa_official') => 'fifa_official',
            default => 'collected_data',
        };
    }

    private function entityKey(array $row): ?string
    {
        foreach (['match_id', 'player_id', 'team_id', 'event_id', 'appearance_id', '_key'] as $key) {
            if (!empty($row[$key])) {
                return (string) $row[$key];
            }
        }
        return null;
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->root, '', str_replace('\\', '/', $path)), '/');
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    private function profileLine(array $row): string
    {
        $ranking = $row['fifa_ranking_pre_tournament'] ?? null;
        $elo = $row['elo_rating'] ?? null;
        return "小组 {$row['group_letter']}，{$row['confederation']}，赛前 FIFA 排名 {$ranking}，Elo {$elo}。";
    }

    private function popularityScore(array $row): float
    {
        $market = (float) ($row['market_value_eur'] ?? 0);
        $caps = (int) ($row['caps'] ?? 0);
        $goals = (int) ($row['goals'] ?? 0);
        return round(($market / 1000000) + ($caps * 1.2) + ($goals * 2.5), 2);
    }

    private function stageNameCn(string $stage): string
    {
        return [
            'Group Stage' => '小组赛',
            'Round of 32' => '32强',
            'Round of 16' => '16强',
            'Quarter-finals' => '四分之一决赛',
            'Semi-finals' => '半决赛',
            'Third-place match' => '季军赛',
            'Final' => '决赛',
        ][$stage] ?? $stage;
    }

    private function teamNameCn(string $name, string $code): ?string
    {
        $map = [
            'MEX' => '墨西哥',
            'RSA' => '南非',
            'KOR' => '韩国',
            'CZE' => '捷克',
            'CAN' => '加拿大',
            'BIH' => '波黑',
            'QAT' => '卡塔尔',
            'SUI' => '瑞士',
            'BRA' => '巴西',
            'MAR' => '摩洛哥',
            'HAI' => '海地',
            'SCO' => '苏格兰',
            'USA' => '美国',
            'PAR' => '巴拉圭',
            'AUS' => '澳大利亚',
            'TUR' => '土耳其',
            'GER' => '德国',
            'CUW' => '库拉索',
            'CIV' => '科特迪瓦',
            'ECU' => '厄瓜多尔',
            'NED' => '荷兰',
            'JPN' => '日本',
            'SWE' => '瑞典',
            'TUN' => '突尼斯',
            'BEL' => '比利时',
            'EGY' => '埃及',
            'IRN' => '伊朗',
            'NZL' => '新西兰',
            'ESP' => '西班牙',
            'CPV' => '佛得角',
            'KSA' => '沙特阿拉伯',
            'URU' => '乌拉圭',
            'FRA' => '法国',
            'SEN' => '塞内加尔',
            'IRQ' => '伊拉克',
            'NOR' => '挪威',
            'ARG' => '阿根廷',
            'ALG' => '阿尔及利亚',
            'AUT' => '奥地利',
            'JOR' => '约旦',
            'POR' => '葡萄牙',
            'COD' => '刚果民主共和国',
            'UZB' => '乌兹别克斯坦',
            'COL' => '哥伦比亚',
            'ENG' => '英格兰',
            'CRO' => '克罗地亚',
            'GHA' => '加纳',
            'PAN' => '巴拿马',
        ];
        if (isset($map[strtoupper($code)])) {
            return $map[strtoupper($code)];
        }
        $nameMap = [
            'South Korea' => '韩国',
            'Korea Republic' => '韩国',
            'Bosnia and Herzegovina' => '波黑',
            'Côte d\'Ivoire' => '科特迪瓦',
            'Cote d\'Ivoire' => '科特迪瓦',
            'IR Iran' => '伊朗',
            'Cabo Verde' => '佛得角',
            'Congo DR' => '刚果民主共和国',
        ];
        return $nameMap[$name] ?? null;
    }

    private function venueNameCn(string $name): ?string
    {
        return [
            'Mexico City Stadium (Estadio Azteca)' => '墨西哥城体育场',
            'New York New Jersey Stadium (MetLife Stadium)' => '纽约新泽西体育场',
            'Los Angeles Stadium (SoFi Stadium)' => '洛杉矶体育场',
            'Dallas Stadium (AT&T Stadium)' => '达拉斯体育场',
            'Vancouver Stadium (BC Place)' => '温哥华体育场',
            'Toronto Stadium (BMO Field)' => '多伦多体育场',
            'Guadalajara Stadium (Estadio Akron)' => '瓜达拉哈拉体育场',
        ][$name] ?? null;
    }

    private function cityNameCn(string $city): ?string
    {
        return [
            'Mexico City' => '墨西哥城',
            'East Rutherford' => '东卢瑟福',
            'Inglewood' => '英格尔伍德',
            'Arlington' => '阿灵顿',
            'Vancouver' => '温哥华',
            'Toronto' => '多伦多',
            'Zapopan' => '萨波潘',
        ][$city] ?? null;
    }

    private function countryAlpha2(string $code): ?string
    {
        $map = [
            'MEX' => 'MX',
            'RSA' => 'ZA',
            'KOR' => 'KR',
            'CZE' => 'CZ',
            'CAN' => 'CA',
            'BIH' => 'BA',
            'QAT' => 'QA',
            'SUI' => 'CH',
            'BRA' => 'BR',
            'MAR' => 'MA',
            'HAI' => 'HT',
            'SCO' => 'GB',
            'USA' => 'US',
            'PAR' => 'PY',
            'AUS' => 'AU',
            'TUR' => 'TR',
            'GER' => 'DE',
            'CUW' => 'CW',
            'CIV' => 'CI',
            'ECU' => 'EC',
            'NED' => 'NL',
            'JPN' => 'JP',
            'SWE' => 'SE',
            'TUN' => 'TN',
            'BEL' => 'BE',
            'EGY' => 'EG',
            'IRN' => 'IR',
            'NZL' => 'NZ',
            'ESP' => 'ES',
            'CPV' => 'CV',
            'KSA' => 'SA',
            'URU' => 'UY',
            'FRA' => 'FR',
            'SEN' => 'SN',
            'IRQ' => 'IQ',
            'NOR' => 'NO',
            'ARG' => 'AR',
            'ALG' => 'DZ',
            'AUT' => 'AT',
            'JOR' => 'JO',
            'POR' => 'PT',
            'COD' => 'CD',
            'UZB' => 'UZ',
            'COL' => 'CO',
            'ENG' => 'GB',
            'CRO' => 'HR',
            'GHA' => 'GH',
            'PAN' => 'PA',
        ];
        $code = strtoupper($code);
        return strlen($code) === 2 ? $code : ($map[$code] ?? null);
    }

    private function flagUrl(string $code): string
    {
        return 'https://api.fifa.com/api/v3/picture/flags-sq-4/' . strtoupper($code);
    }

    private function flagEmoji(?string $countryCode): ?string
    {
        if (!$countryCode || strlen($countryCode) !== 2) {
            return null;
        }
        $emoji = '';
        foreach (str_split(strtoupper($countryCode)) as $char) {
            $emoji .= mb_chr(0x1F1E6 + ord($char) - ord('A'), 'UTF-8');
        }
        return $emoji;
    }

    private function positionCode(string $position): ?string
    {
        $position = trim($position);
        return [
            'Goalkeeper' => 'GK',
            'Defender' => 'DEF',
            'Midfielder' => 'MID',
            'Forward' => 'FWD',
        ][$position] ?? ($position !== '' ? $position : null);
    }

    private function matchStatus(string $status): string
    {
        $status = strtolower($status);
        return match (true) {
            str_contains($status, 'complete'), str_contains($status, 'final') => 'finished',
            str_contains($status, 'live'), str_contains($status, 'progress') => 'live',
            str_contains($status, 'postpone') => 'postponed',
            str_contains($status, 'cancel') => 'cancelled',
            str_contains($status, 'schedule') => 'scheduled',
            default => 'unknown',
        };
    }

    private function dateTime(string $date, string $time): ?string
    {
        $stamp = strtotime(trim($date . ' ' . $time));
        return $stamp ? date('Y-m-d H:i:s', $stamp) : null;
    }

    private function dateOnly(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        $stamp = strtotime((string) $value);
        return $stamp ? date('Y-m-d', $stamp) : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function boolInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true) ? 1 : 0;
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?: '', '-'));
        return $slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(4));
    }
}

$defaultRoot = ROOT_PATH . '/storage/imports/worldcup2026_complete_collection_kit';
$root = $argv[1] ?? $defaultRoot;
$fresh = !in_array('--append', $argv, true);
$result = (new CollectedWorldCupImporter($root))->run($fresh);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
