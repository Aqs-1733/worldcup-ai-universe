<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AppRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $tables = ['teams', 'players', 'matches', 'news_articles', 'comments', 'users', 'team_members', 'source_files', 'source_records', 'data_quality_checks'];
        $counts = [];
        foreach ($tables as $table) {
            try {
                $counts[$table] = (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            } catch (\Throwable) {
                $counts[$table] = 0;
            }
        }
        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    public function qualityChecks(int $limit = 8): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM data_quality_checks ORDER BY status <> "fail", checked_at DESC, id LIMIT ?');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function teamMembers(): array
    {
        return $this->pdo->query('SELECT * FROM team_members WHERE is_visible = 1 ORDER BY sort_order, id')->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function adminPosts(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.display_name, u.username
             FROM admin_posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.status = "published"
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function teams(?string $query = null, int $limit = 200): array
    {
        $sql = 'SELECT t.*, COUNT(p.id) AS player_count
                FROM teams t
                LEFT JOIN players p ON p.team_id = t.id';
        $params = [];
        if ($query) {
            $sql .= ' WHERE t.name_cn LIKE ? OR t.name_original LIKE ? OR t.code LIKE ?';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' GROUP BY t.id ORDER BY COALESCE(t.group_name, "ZZ"), COALESCE(t.world_ranking, 999), t.name_original LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function team(int|string $id): ?array
    {
        $column = is_numeric($id) ? 'id' : 'slug';
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$id]);
        $team = $stmt->fetch();
        return $team ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function players(?string $query = null, ?int $teamId = null, int $limit = 300): array
    {
        $where = [];
        $params = [];
        if ($query) {
            $where[] = '(p.name_cn LIKE ? OR p.name_original LIKE ? OR p.club LIKE ? OR t.name_cn LIKE ? OR t.name_original LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($teamId) {
            $where[] = 'p.team_id = ?';
            $params[] = $teamId;
        }

        $sql = 'SELECT p.*, t.name_cn AS team_name_cn, t.name_original AS team_name_original, t.flag_emoji, t.flag_url, t.code AS team_code
                FROM players p
                LEFT JOIN teams t ON t.id = p.team_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.popularity_score DESC, COALESCE(p.caps, 0) DESC, COALESCE(p.goals, 0) DESC, p.name_original LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function player(int|string $id): ?array
    {
        $column = is_numeric($id) ? 'p.id' : 'p.slug';
        $stmt = $this->pdo->prepare(
            "SELECT p.*, t.name_cn AS team_name_cn, t.name_original AS team_name_original, t.flag_emoji, t.flag_url, t.code AS team_code
             FROM players p
             LEFT JOIN teams t ON t.id = p.team_id
             WHERE {$column} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $player = $stmt->fetch();
        return $player ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function matches(int $limit = 120): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*,
                    ht.name_cn AS home_name_cn, ht.name_original AS home_name_original, ht.flag_emoji AS home_flag, ht.flag_url AS home_flag_url, ht.code AS home_code,
                    at.name_cn AS away_name_cn, at.name_original AS away_name_original, at.flag_emoji AS away_flag, at.flag_url AS away_flag_url, at.code AS away_code,
                    v.name_cn AS venue_name_cn, v.name_original AS venue_name_original
             FROM matches m
             LEFT JOIN teams ht ON ht.id = m.home_team_id
             LEFT JOIN teams at ON at.id = m.away_team_id
             LEFT JOIN venues v ON v.id = m.venue_id
             ORDER BY COALESCE(m.starts_at, "2099-12-31"), m.id
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function standings(): array
    {
        $rows = $this->pdo->query(
            'SELECT s.*, t.name_cn, t.name_original, t.flag_emoji, t.flag_url, t.code
             FROM standings s
             JOIN teams t ON t.id = s.team_id
             ORDER BY s.group_name, s.points DESC, s.goal_difference DESC, s.goals_for DESC'
        )->fetchAll();

        $groups = [];
        foreach ($rows as $row) {
            $groups[(string) $row['group_name']][] = $row;
        }
        return $groups;
    }

    /** @return array<int, array<string, mixed>> */
    public function news(array $filters = [], int $limit = 80): array
    {
        $where = [];
        $params = [];
        $join = 'LEFT JOIN news_article_tags nat ON nat.article_id = n.id LEFT JOIN tags tg ON tg.id = nat.tag_id';
        if (!empty($filters['q'])) {
            $where[] = '(n.title_cn LIKE ? OR n.title_original LIKE ? OR n.summary_cn LIKE ? OR n.summary_original LIKE ? OR n.source_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['tag'])) {
            $where[] = 'tg.slug = ?';
            $params[] = $filters['tag'];
        }

        $sql = 'SELECT n.*, GROUP_CONCAT(DISTINCT tg.name_cn ORDER BY tg.id SEPARATOR ",") AS tag_names,
                       GROUP_CONCAT(DISTINCT tg.slug ORDER BY tg.id SEPARATOR ",") AS tag_slugs
                FROM news_articles n ' . $join;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY n.id ORDER BY COALESCE(n.published_at, n.fetched_at) DESC LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function newsArticle(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, GROUP_CONCAT(DISTINCT tg.name_cn ORDER BY tg.id SEPARATOR ",") AS tag_names
             FROM news_articles n
             LEFT JOIN news_article_tags nat ON nat.article_id = n.id
             LEFT JOIN tags tg ON tg.id = nat.tag_id
             WHERE n.id = ?
             GROUP BY n.id
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function tags(): array
    {
        return $this->pdo->query('SELECT * FROM tags ORDER BY id')->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function comments(string $entityType = 'site', ?int $entityId = null, int $limit = 50): array
    {
        $params = [$entityType];
        $sql = 'SELECT c.*, u.display_name, u.username
                FROM comments c
                JOIN users u ON u.id = c.user_id
                WHERE c.status = "visible" AND c.entity_type = ?';
        if ($entityId) {
            $sql .= ' AND c.entity_id = ?';
            $params[] = $entityId;
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT ' . max(1, $limit);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function addComment(int $userId, string $body, string $entityType = 'site', ?int $entityId = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO comments (user_id, entity_type, entity_id, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $entityType, $entityId, trim($body)]);
    }

    /** @return array<string, mixed> */
    public function userPreferenceIds(int $userId): array
    {
        return [
            'teams' => $this->ids('SELECT team_id FROM user_favorite_teams WHERE user_id = ?', [$userId]),
            'players' => $this->ids('SELECT player_id FROM user_favorite_players WHERE user_id = ?', [$userId]),
            'blocked_teams' => $this->ids('SELECT team_id FROM user_blocked_teams WHERE user_id = ?', [$userId]),
            'prefs' => $this->one('SELECT * FROM fan_preferences WHERE user_id = ?', [$userId]),
        ];
    }

    /** @param array<int|string> $teamIds @param array<int|string> $playerIds @param array<int|string> $blockedTeamIds */
    public function savePreferences(int $userId, array $teamIds, array $playerIds, array $blockedTeamIds, array $pushPrefs, bool $skipped = false): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach (['user_favorite_teams', 'user_favorite_players', 'user_blocked_teams'] as $table) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
            }

            $insertTeam = $this->pdo->prepare('INSERT IGNORE INTO user_favorite_teams (user_id, team_id) VALUES (?, ?)');
            foreach ($this->cleanIds($teamIds) as $id) {
                $insertTeam->execute([$userId, $id]);
            }

            $insertPlayer = $this->pdo->prepare('INSERT IGNORE INTO user_favorite_players (user_id, player_id) VALUES (?, ?)');
            foreach ($this->cleanIds($playerIds) as $id) {
                $insertPlayer->execute([$userId, $id]);
            }

            $insertBlocked = $this->pdo->prepare('INSERT IGNORE INTO user_blocked_teams (user_id, team_id) VALUES (?, ?)');
            foreach ($this->cleanIds($blockedTeamIds) as $id) {
                $insertBlocked->execute([$userId, $id]);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO fan_preferences (user_id, push_match, push_tactics, push_team, push_fanmade, push_entertainment)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   push_match = VALUES(push_match),
                   push_tactics = VALUES(push_tactics),
                   push_team = VALUES(push_team),
                   push_fanmade = VALUES(push_fanmade),
                   push_entertainment = VALUES(push_entertainment)'
            );
            $stmt->execute([
                $userId,
                !empty($pushPrefs['match']) ? 1 : 0,
                !empty($pushPrefs['tactics']) ? 1 : 0,
                !empty($pushPrefs['team']) ? 1 : 0,
                !empty($pushPrefs['fanmade']) ? 1 : 0,
                !empty($pushPrefs['entertainment']) ? 1 : 0,
            ]);

            $column = $skipped ? ', survey_skipped_at = NOW()' : '';
            $this->pdo->prepare("UPDATE users SET survey_completed_at = NOW() {$column} WHERE id = ?")->execute([$userId]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function upsertTeam(array $team): int
    {
        $slug = $team['slug'] ?? $this->slug((string) ($team['name_original'] ?? $team['name_cn'] ?? 'team'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO teams (fifa_id, slug, code, name_cn, name_original, country_code, flag_emoji, flag_url, confederation, group_name, coach_name, world_ranking, profile, source_url, source_synced_at)
             VALUES (:fifa_id, :slug, :code, :name_cn, :name_original, :country_code, :flag_emoji, :flag_url, :confederation, :group_name, :coach_name, :world_ranking, :profile, :source_url, NOW())
             ON DUPLICATE KEY UPDATE
               code = COALESCE(VALUES(code), code),
               name_cn = COALESCE(VALUES(name_cn), name_cn),
               name_original = VALUES(name_original),
               country_code = COALESCE(VALUES(country_code), country_code),
               flag_emoji = COALESCE(VALUES(flag_emoji), flag_emoji),
               flag_url = COALESCE(VALUES(flag_url), flag_url),
               confederation = COALESCE(VALUES(confederation), confederation),
               group_name = COALESCE(VALUES(group_name), group_name),
               coach_name = COALESCE(VALUES(coach_name), coach_name),
               world_ranking = COALESCE(VALUES(world_ranking), world_ranking),
               profile = COALESCE(VALUES(profile), profile),
               source_url = COALESCE(VALUES(source_url), source_url),
               source_synced_at = NOW()'
        );
        $stmt->execute([
            'fifa_id' => $team['fifa_id'] ?? null,
            'slug' => $slug,
            'code' => $team['code'] ?? null,
            'name_cn' => $team['name_cn'] ?? null,
            'name_original' => $team['name_original'] ?? $team['name_cn'] ?? $slug,
            'country_code' => $team['country_code'] ?? null,
            'flag_emoji' => $team['flag_emoji'] ?? null,
            'flag_url' => $team['flag_url'] ?? null,
            'confederation' => $team['confederation'] ?? null,
            'group_name' => $team['group_name'] ?? null,
            'coach_name' => $team['coach_name'] ?? null,
            'world_ranking' => $team['world_ranking'] ?? null,
            'profile' => $team['profile'] ?? null,
            'source_url' => $team['source_url'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $row = $this->one('SELECT id FROM teams WHERE slug = ? OR fifa_id = ? LIMIT 1', [$slug, $team['fifa_id'] ?? null]);
        return (int) ($row['id'] ?? 0);
    }

    public function upsertPlayer(array $player): int
    {
        $slug = $player['slug'] ?? $this->slug((string) ($player['name_original'] ?? $player['name_cn'] ?? 'player'));
        $score = (float) ($player['popularity_score'] ?? 0);
        if (!$score) {
            $score = ((int) ($player['caps'] ?? 0) * 1.2) + ((int) ($player['goals'] ?? 0) * 2.5);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO players (team_id, fifa_id, slug, name_cn, name_original, position, shirt_number, birth_date, age, club, caps, goals, height_cm, photo_url, popularity_score, source_url, source_synced_at)
             VALUES (:team_id, :fifa_id, :slug, :name_cn, :name_original, :position, :shirt_number, :birth_date, :age, :club, :caps, :goals, :height_cm, :photo_url, :popularity_score, :source_url, NOW())
             ON DUPLICATE KEY UPDATE
               team_id = COALESCE(VALUES(team_id), team_id),
               name_cn = COALESCE(VALUES(name_cn), name_cn),
               name_original = VALUES(name_original),
               position = COALESCE(VALUES(position), position),
               shirt_number = COALESCE(VALUES(shirt_number), shirt_number),
               birth_date = COALESCE(VALUES(birth_date), birth_date),
               age = COALESCE(VALUES(age), age),
               club = COALESCE(VALUES(club), club),
               caps = COALESCE(VALUES(caps), caps),
               goals = COALESCE(VALUES(goals), goals),
               height_cm = COALESCE(VALUES(height_cm), height_cm),
               photo_url = COALESCE(VALUES(photo_url), photo_url),
               popularity_score = GREATEST(popularity_score, VALUES(popularity_score)),
               source_url = COALESCE(VALUES(source_url), source_url),
               source_synced_at = NOW()'
        );
        $stmt->execute([
            'team_id' => $player['team_id'] ?? null,
            'fifa_id' => $player['fifa_id'] ?? null,
            'slug' => $slug,
            'name_cn' => $player['name_cn'] ?? null,
            'name_original' => $player['name_original'] ?? $player['name_cn'] ?? $slug,
            'position' => $player['position'] ?? null,
            'shirt_number' => $player['shirt_number'] ?? null,
            'birth_date' => $player['birth_date'] ?? null,
            'age' => $player['age'] ?? null,
            'club' => $player['club'] ?? null,
            'caps' => $player['caps'] ?? null,
            'goals' => $player['goals'] ?? null,
            'height_cm' => $player['height_cm'] ?? null,
            'photo_url' => $player['photo_url'] ?? null,
            'popularity_score' => $score,
            'source_url' => $player['source_url'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $row = $this->one('SELECT id FROM players WHERE slug = ? OR fifa_id = ? LIMIT 1', [$slug, $player['fifa_id'] ?? null]);
        return (int) ($row['id'] ?? 0);
    }

    public function upsertMatch(array $match): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO matches (external_id, stage, group_name, home_team_id, away_team_id, home_team_name, away_team_name, home_score, away_score, home_penalty_score, away_penalty_score, status, starts_at, venue_id, source_url, source_synced_at)
             VALUES (:external_id, :stage, :group_name, :home_team_id, :away_team_id, :home_team_name, :away_team_name, :home_score, :away_score, :home_penalty_score, :away_penalty_score, :status, :starts_at, :venue_id, :source_url, NOW())
             ON DUPLICATE KEY UPDATE
               stage = VALUES(stage),
               group_name = COALESCE(VALUES(group_name), group_name),
               home_team_id = COALESCE(VALUES(home_team_id), home_team_id),
               away_team_id = COALESCE(VALUES(away_team_id), away_team_id),
               home_team_name = COALESCE(VALUES(home_team_name), home_team_name),
               away_team_name = COALESCE(VALUES(away_team_name), away_team_name),
               home_score = VALUES(home_score),
               away_score = VALUES(away_score),
               home_penalty_score = VALUES(home_penalty_score),
               away_penalty_score = VALUES(away_penalty_score),
               status = VALUES(status),
               starts_at = COALESCE(VALUES(starts_at), starts_at),
               venue_id = COALESCE(VALUES(venue_id), venue_id),
               source_url = COALESCE(VALUES(source_url), source_url),
               source_synced_at = NOW()'
        );
        $stmt->execute([
            'external_id' => $match['external_id'] ?? null,
            'stage' => $match['stage'] ?? 'unknown',
            'group_name' => $match['group_name'] ?? null,
            'home_team_id' => $match['home_team_id'] ?? null,
            'away_team_id' => $match['away_team_id'] ?? null,
            'home_team_name' => $match['home_team_name'] ?? null,
            'away_team_name' => $match['away_team_name'] ?? null,
            'home_score' => $match['home_score'] ?? null,
            'away_score' => $match['away_score'] ?? null,
            'home_penalty_score' => $match['home_penalty_score'] ?? null,
            'away_penalty_score' => $match['away_penalty_score'] ?? null,
            'status' => $match['status'] ?? 'unknown',
            'starts_at' => $match['starts_at'] ?? null,
            'venue_id' => $match['venue_id'] ?? null,
            'source_url' => $match['source_url'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $row = $this->one('SELECT id FROM matches WHERE external_id = ? LIMIT 1', [$match['external_id'] ?? null]);
        return (int) ($row['id'] ?? 0);
    }

    public function upsertVenue(array $venue): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO venues (fifa_id, name_cn, name_original, city_cn, city_original, country_code, capacity, source_url)
             VALUES (:fifa_id, :name_cn, :name_original, :city_cn, :city_original, :country_code, :capacity, :source_url)
             ON DUPLICATE KEY UPDATE
               name_cn = COALESCE(VALUES(name_cn), name_cn),
               name_original = VALUES(name_original),
               city_cn = COALESCE(VALUES(city_cn), city_cn),
               city_original = COALESCE(VALUES(city_original), city_original),
               country_code = COALESCE(VALUES(country_code), country_code),
               capacity = COALESCE(VALUES(capacity), capacity),
               source_url = COALESCE(VALUES(source_url), source_url)'
        );
        $stmt->execute([
            'fifa_id' => $venue['fifa_id'] ?? null,
            'name_cn' => $venue['name_cn'] ?? null,
            'name_original' => $venue['name_original'] ?? 'Unknown Venue',
            'city_cn' => $venue['city_cn'] ?? null,
            'city_original' => $venue['city_original'] ?? null,
            'country_code' => $venue['country_code'] ?? null,
            'capacity' => $venue['capacity'] ?? null,
            'source_url' => $venue['source_url'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $row = $this->one('SELECT id FROM venues WHERE fifa_id = ? OR name_original = ? LIMIT 1', [$venue['fifa_id'] ?? null, $venue['name_original'] ?? null]);
        return (int) ($row['id'] ?? 0);
    }

    public function upsertMatchStats(int $matchId, ?int $teamId, array $stats, ?string $sourceUrl = null): void
    {
        if (!$matchId || !$teamId) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_stats (match_id, team_id, possession, shots, shots_on_target, corners, fouls, yellow_cards, red_cards, expected_goals, source_url, source_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               possession = VALUES(possession),
               shots = VALUES(shots),
               shots_on_target = VALUES(shots_on_target),
               corners = VALUES(corners),
               fouls = VALUES(fouls),
               yellow_cards = VALUES(yellow_cards),
               red_cards = VALUES(red_cards),
               expected_goals = VALUES(expected_goals),
               source_url = COALESCE(VALUES(source_url), source_url),
               source_synced_at = NOW()'
        );
        $stmt->execute([
            $matchId,
            $teamId,
            $stats['possession'] ?? null,
            $stats['shots'] ?? null,
            $stats['shots_on_target'] ?? null,
            $stats['corners'] ?? null,
            $stats['fouls'] ?? null,
            $stats['yellow_cards'] ?? null,
            $stats['red_cards'] ?? null,
            $stats['expected_goals'] ?? null,
            $sourceUrl,
        ]);
    }

    /** @param array<int, string> $tagSlugs */
    public function upsertNews(array $article, array $tagSlugs): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO news_articles (source_id, source_name, source_url, title_cn, title_original, summary_cn, summary_original, content_cn, content_original, language_code, published_at, fetched_at, credibility_score, translation_status, raw_payload_json)
             VALUES (:source_id, :source_name, :source_url, :title_cn, :title_original, :summary_cn, :summary_original, :content_cn, :content_original, :language_code, :published_at, NOW(), :credibility_score, :translation_status, :raw_payload_json)
             ON DUPLICATE KEY UPDATE
               title_cn = COALESCE(VALUES(title_cn), title_cn),
               title_original = VALUES(title_original),
               summary_cn = COALESCE(VALUES(summary_cn), summary_cn),
               summary_original = COALESCE(VALUES(summary_original), summary_original),
               content_cn = COALESCE(VALUES(content_cn), content_cn),
               content_original = COALESCE(VALUES(content_original), content_original),
               language_code = VALUES(language_code),
               published_at = COALESCE(VALUES(published_at), published_at),
               fetched_at = NOW(),
               credibility_score = VALUES(credibility_score),
               translation_status = VALUES(translation_status),
               raw_payload_json = VALUES(raw_payload_json)'
        );
        $stmt->execute([
            'source_id' => $article['source_id'] ?? null,
            'source_name' => $article['source_name'] ?? 'Unknown',
            'source_url' => $article['source_url'],
            'title_cn' => $article['title_cn'] ?? null,
            'title_original' => $article['title_original'],
            'summary_cn' => $article['summary_cn'] ?? null,
            'summary_original' => $article['summary_original'] ?? null,
            'content_cn' => $article['content_cn'] ?? null,
            'content_original' => $article['content_original'] ?? null,
            'language_code' => $article['language_code'] ?? 'en',
            'published_at' => $article['published_at'] ?? null,
            'credibility_score' => $article['credibility_score'] ?? 80,
            'translation_status' => $article['translation_status'] ?? 'none',
            'raw_payload_json' => json_encode($article['raw_payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $row = $this->one('SELECT id FROM news_articles WHERE source_url = ? LIMIT 1', [$article['source_url']]);
        $articleId = (int) ($row['id'] ?? 0);
        if ($articleId > 0) {
            $this->pdo->prepare('DELETE FROM news_article_tags WHERE article_id = ?')->execute([$articleId]);
            $insert = $this->pdo->prepare('INSERT IGNORE INTO news_article_tags (article_id, tag_id) SELECT ?, id FROM tags WHERE slug = ?');
            foreach (array_unique($tagSlugs) as $slug) {
                $insert->execute([$articleId, $slug]);
            }
        }

        return $articleId;
    }

    public function recordAiGeneration(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_generations (user_id, generation_type, prompt, tone, target_team, response_text, image_url, model_name, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['generation_type'] ?? 'chat',
            $data['prompt'] ?? '',
            $data['tone'] ?? null,
            $data['target_team'] ?? null,
            $data['response_text'] ?? null,
            $data['image_url'] ?? null,
            $data['model_name'] ?? null,
            $data['status'] ?? 'success',
            $data['error_message'] ?? null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function tableRows(string $table, int $limit = 80): array
    {
        $allowed = ['teams', 'players', 'matches', 'news_articles', 'team_members', 'admin_posts', 'comments', 'source_files', 'data_quality_checks', 'player_statistics', 'match_lineups', 'match_events'];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        return $this->pdo->query("SELECT * FROM {$table} ORDER BY id DESC LIMIT " . max(1, $limit))->fetchAll();
    }

    public function simpleSave(string $table, array $payload): void
    {
        $allowed = [
            'team_members' => ['name', 'student_no', 'role_name', 'bio', 'photo_url', 'sort_order', 'is_visible'],
            'admin_posts' => ['title', 'body', 'post_type', 'status', 'published_at'],
            'teams' => ['code', 'name_cn', 'name_original', 'country_code', 'flag_emoji', 'flag_url', 'confederation', 'group_name', 'coach_name', 'world_ranking', 'profile', 'source_url'],
            'players' => ['team_id', 'name_cn', 'name_original', 'position', 'shirt_number', 'birth_date', 'age', 'club', 'caps', 'goals', 'height_cm', 'photo_url', 'popularity_score', 'source_url'],
            'matches' => ['stage', 'group_name', 'home_team_id', 'away_team_id', 'home_team_name', 'away_team_name', 'home_score', 'away_score', 'status', 'starts_at', 'source_url'],
            'news_articles' => ['source_name', 'source_url', 'title_cn', 'title_original', 'summary_cn', 'summary_original', 'content_cn', 'content_original', 'language_code', 'published_at', 'credibility_score', 'translation_status'],
        ];
        if (!isset($allowed[$table])) {
            return;
        }

        $payload = $this->applyAdminDefaults($table, $payload);

        $id = isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : null;
        $fields = array_values(array_filter($allowed[$table], static fn (string $field): bool => array_key_exists($field, $payload)));
        if (!$fields) {
            return;
        }

        if (!$id && in_array($table, ['teams', 'players'], true)) {
            $payload['slug'] = $this->slug((string) ($payload['name_original'] ?? $payload['name_cn'] ?? uniqid($table . '-', true)));
            $fields[] = 'slug';
        }

        if ($id) {
            $set = implode(', ', array_map(static fn (string $field): string => "{$field} = ?", $fields));
            $values = array_map(static fn (string $field): mixed => $payload[$field] === '' ? null : $payload[$field], $fields);
            $values[] = $id;
            $this->pdo->prepare("UPDATE {$table} SET {$set} WHERE id = ?")->execute($values);
            return;
        }

        $columns = implode(', ', $fields);
        $holders = implode(', ', array_fill(0, count($fields), '?'));
        $values = array_map(static fn (string $field): mixed => $payload[$field] === '' ? null : $payload[$field], $fields);
        $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$holders})")->execute($values);
    }

    private function applyAdminDefaults(string $table, array $payload): array
    {
        $defaults = [
            'team_members' => ['role_name' => '成员', 'sort_order' => 0, 'is_visible' => 1],
            'teams' => ['name_original' => $payload['name_cn'] ?? 'Team'],
            'players' => ['name_original' => $payload['name_cn'] ?? 'Player', 'popularity_score' => 0],
            'matches' => ['stage' => 'World Cup', 'status' => 'unknown'],
            'news_articles' => ['source_name' => '手工录入', 'title_original' => $payload['title_cn'] ?? 'Untitled', 'language_code' => 'zh-CN', 'credibility_score' => 80, 'translation_status' => 'none'],
            'admin_posts' => ['post_type' => 'announcement', 'status' => 'published'],
        ][$table] ?? [];

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $payload) || $payload[$key] === '') {
                $payload[$key] = $value;
            }
        }
        return $payload;
    }

    public function deleteRow(string $table, int $id): void
    {
        $allowed = ['teams', 'players', 'matches', 'news_articles', 'team_members', 'admin_posts', 'comments'];
        if (!in_array($table, $allowed, true)) {
            return;
        }
        $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
    }

    /** @return array<int, int> */
    private function ids(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<int|string> $ids @return array<int, int> */
    private function cleanIds(array $ids): array
    {
        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?: '', '-'));
        return $slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(4));
    }
}
