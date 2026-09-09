<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppRepository;

final class WorldCupSyncService
{
    public function __construct(
        private readonly AppRepository $repo = new AppRepository(),
        private readonly HttpClient $http = new HttpClient(),
        private readonly ArkClient $ark = new ArkClient(),
    ) {
    }

    /** @return array<string, int|string> */
    public function syncScoreboard(): array
    {
        $url = $this->scoreboardUrl();
        $json = json_decode($this->http->get($url), true);
        if (!is_array($json)) {
            throw new \RuntimeException('ESPN scoreboard did not return JSON.');
        }

        $importedTeams = 0;
        $importedMatches = 0;
        $importedPlayers = 0;
        $teamMap = [];

        foreach (($json['events'] ?? []) as $event) {
            $competition = $event['competitions'][0] ?? [];
            $teamIds = [];

            foreach (($competition['competitors'] ?? []) as $competitor) {
                $team = $competitor['team'] ?? [];
                $name = (string) ($team['displayName'] ?? $team['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $countryCode = $this->countryCodeFromTeam($team);
                $localTeamId = $this->repo->upsertTeam([
                    'fifa_id' => isset($team['id']) ? 'espn-' . $team['id'] : null,
                    'slug' => $this->slug($name),
                    'code' => $team['abbreviation'] ?? null,
                    'name_cn' => $this->ark->translateToChinese($name, '球队名'),
                    'name_original' => $name,
                    'country_code' => $countryCode,
                    'flag_emoji' => $this->flagEmoji($countryCode),
                    'source_url' => $url,
                ]);

                $teamIds[$competitor['homeAway'] ?? count($teamIds)] = $localTeamId;
                if (!empty($team['id'])) {
                    $teamMap[(string) $team['id']] = $localTeamId;
                }
                $importedTeams++;
            }

            $home = $this->competitor($competition, 'home');
            $away = $this->competitor($competition, 'away');
            $status = $this->status($competition, $event);
            $note = (string) ($competition['altGameNote'] ?? '');
            $venueId = $this->venueId($competition, $url);
            $eventUrl = $this->eventLink($event) ?: $url;

            $matchId = $this->repo->upsertMatch([
                'external_id' => isset($event['id']) ? 'espn-' . $event['id'] : null,
                'stage' => $this->stageFromNote($note, (string) ($event['season']['slug'] ?? $event['shortName'] ?? 'World Cup')),
                'group_name' => $this->groupFromNote($note),
                'home_team_id' => $teamIds['home'] ?? null,
                'away_team_id' => $teamIds['away'] ?? null,
                'home_team_name' => $home['team']['displayName'] ?? null,
                'away_team_name' => $away['team']['displayName'] ?? null,
                'home_score' => isset($home['score']) && $home['score'] !== '' ? (int) $home['score'] : null,
                'away_score' => isset($away['score']) && $away['score'] !== '' ? (int) $away['score'] : null,
                'status' => $status,
                'starts_at' => isset($event['date']) ? date('Y-m-d H:i:s', strtotime((string) $event['date'])) : null,
                'venue_id' => $venueId ?: null,
                'source_url' => $eventUrl,
            ]);

            $this->syncCompetitionStats($matchId, $competition, $teamMap, $eventUrl);
            $importedPlayers += $this->syncEventAthletes($competition, $teamMap, $eventUrl);
            $importedMatches++;
        }

        foreach ($teamMap as $espnId => $localTeamId) {
            $importedPlayers += $this->syncEspnRoster((string) $espnId, (int) $localTeamId);
        }

        $this->markSource('espn_scoreboard', 'ok');
        return [
            'teams' => $importedTeams,
            'matches' => $importedMatches,
            'players' => $importedPlayers,
            'source' => $url,
        ];
    }

    /** @return array<string, int|string> */
    public function syncWikipediaSquads(int $maxTeams = 80): array
    {
        $url = (string) env('WIKIPEDIA_SQUADS_URL');
        $html = $this->http->get($url);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $headlines = $xpath->query('//h2|//h3|//h4');
        $teams = 0;
        $players = 0;

        foreach ($headlines ?: [] as $headline) {
            if ($teams >= $maxTeams) {
                break;
            }
            $teamName = trim(preg_replace('/\[[^\]]+\]/', '', $headline->textContent) ?: '');
            if ($teamName === '' || preg_match('/squads|notes|references|external links/i', $teamName)) {
                continue;
            }

            $table = $this->nextWikiTable($headline);
            if (!$table) {
                continue;
            }

            $countryCode = $this->countryCodeFromName($teamName);
            $teamId = $this->repo->upsertTeam([
                'slug' => $this->slug($teamName),
                'name_cn' => $this->ark->translateToChinese($teamName, '球队名'),
                'name_original' => $teamName,
                'country_code' => $countryCode,
                'flag_emoji' => $this->flagEmoji($countryCode),
                'source_url' => $url,
            ]);
            $teams++;

            foreach ($xpath->query('.//tr', $table) ?: [] as $row) {
                $cells = $xpath->query('./td|./th', $row);
                if (!$cells || $cells->length < 3) {
                    continue;
                }

                $values = [];
                foreach ($cells as $cell) {
                    $values[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?: '');
                }
                $name = $this->guessPlayerName($values);
                if (!$name || preg_match('/player|no\.|pos\./i', $name)) {
                    continue;
                }

                $this->repo->upsertPlayer([
                    'team_id' => $teamId ?: null,
                    'slug' => $this->slug($teamName . '-' . $name),
                    'name_cn' => $this->ark->translateToChinese($name, '球员姓名'),
                    'name_original' => $name,
                    'position' => $this->guessPosition($values),
                    'shirt_number' => $this->guessNumber($values),
                    'caps' => $this->guessTrailingNumber($values, -2),
                    'goals' => $this->guessTrailingNumber($values, -1),
                    'club' => $this->guessClub($values),
                    'source_url' => $url,
                ]);
                $players++;
            }
        }

        $this->markSource('wikipedia_squads', 'ok');
        return ['teams' => $teams, 'players' => $players, 'source' => $url];
    }

    private function scoreboardUrl(): string
    {
        $url = (string) env('ESPN_SCOREBOARD_URL');
        $dates = (string) env('WORLDCUP_SCOREBOARD_DATES', '20260611-20260719');
        $limit = (string) env('WORLDCUP_SCOREBOARD_LIMIT', '400');
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query(['dates' => $dates, 'limit' => $limit]);
    }

    private function competitor(array $competition, string $homeAway): array
    {
        foreach (($competition['competitors'] ?? []) as $competitor) {
            if (($competitor['homeAway'] ?? '') === $homeAway) {
                return $competitor;
            }
        }
        return [];
    }

    private function status(array $competition, array $event): string
    {
        $statusName = strtolower((string) ($competition['status']['type']['name'] ?? $event['status']['type']['name'] ?? 'unknown'));
        return match (true) {
            str_contains($statusName, 'final') || str_contains($statusName, 'full_time') => 'finished',
            str_contains($statusName, 'in') || str_contains($statusName, 'progress') => 'live',
            str_contains($statusName, 'pre') || str_contains($statusName, 'scheduled') => 'scheduled',
            default => 'unknown',
        };
    }

    private function venueId(array $competition, string $sourceUrl): ?int
    {
        $venue = $competition['venue'] ?? [];
        if (empty($venue['fullName'])) {
            return null;
        }

        return $this->repo->upsertVenue([
            'fifa_id' => isset($venue['id']) ? 'espn-' . $venue['id'] : null,
            'name_cn' => $this->ark->translateToChinese((string) $venue['fullName'], '球场名'),
            'name_original' => $venue['fullName'],
            'city_original' => $venue['address']['city'] ?? null,
            'country_code' => $this->countryCodeFromCountryName((string) ($venue['address']['country'] ?? '')),
            'source_url' => $sourceUrl,
        ]);
    }

    private function syncCompetitionStats(int $matchId, array $competition, array $teamMap, string $sourceUrl): void
    {
        foreach (($competition['competitors'] ?? []) as $competitor) {
            $espnId = (string) ($competitor['team']['id'] ?? '');
            $teamId = $teamMap[$espnId] ?? null;
            $stats = [];
            foreach (($competitor['statistics'] ?? []) as $stat) {
                $name = $stat['name'] ?? '';
                $value = isset($stat['displayValue']) ? (float) preg_replace('/[^0-9.]/', '', (string) $stat['displayValue']) : null;
                match ($name) {
                    'possessionPct' => $stats['possession'] = $value,
                    'totalShots' => $stats['shots'] = $value !== null ? (int) $value : null,
                    'shotsOnTarget' => $stats['shots_on_target'] = $value !== null ? (int) $value : null,
                    'wonCorners' => $stats['corners'] = $value !== null ? (int) $value : null,
                    'foulsCommitted' => $stats['fouls'] = $value !== null ? (int) $value : null,
                    default => null,
                };
            }
            $this->repo->upsertMatchStats($matchId, $teamId, $stats, $sourceUrl);
        }
    }

    private function syncEventAthletes(array $competition, array $teamMap, string $sourceUrl): int
    {
        $count = 0;
        foreach (($competition['details'] ?? []) as $detail) {
            foreach (($detail['athletesInvolved'] ?? []) as $athlete) {
                $name = (string) ($athlete['fullName'] ?? $athlete['displayName'] ?? '');
                if ($name === '') {
                    continue;
                }

                $espnTeamId = (string) ($athlete['team']['id'] ?? '');
                $teamId = $teamMap[$espnTeamId] ?? null;
                $this->repo->upsertPlayer([
                    'team_id' => $teamId,
                    'fifa_id' => isset($athlete['id']) ? 'espn-' . $athlete['id'] : null,
                    'slug' => $this->slug(($espnTeamId ?: 'event') . '-' . $name),
                    'name_cn' => $this->ark->translateToChinese($name, '球员姓名'),
                    'name_original' => $name,
                    'position' => $athlete['position'] ?? null,
                    'shirt_number' => isset($athlete['jersey']) ? (int) $athlete['jersey'] : null,
                    'photo_url' => $athlete['headshot'] ?? null,
                    'popularity_score' => 100,
                    'source_url' => $sourceUrl,
                ]);
                $count++;
            }
        }
        return $count;
    }

    private function syncEspnRoster(string $espnTeamId, int $localTeamId): int
    {
        $url = "https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/teams/{$espnTeamId}/roster";
        try {
            $json = json_decode($this->http->get($url), true);
        } catch (\Throwable) {
            return 0;
        }
        if (!is_array($json)) {
            return 0;
        }

        $count = 0;
        foreach (($json['athletes'] ?? []) as $athlete) {
            $name = (string) ($athlete['fullName'] ?? $athlete['displayName'] ?? '');
            if ($name === '') {
                continue;
            }

            $this->repo->upsertPlayer([
                'team_id' => $localTeamId,
                'fifa_id' => isset($athlete['id']) ? 'espn-' . $athlete['id'] : null,
                'slug' => $this->slug($espnTeamId . '-' . ($athlete['slug'] ?? $name)),
                'name_cn' => $this->ark->translateToChinese($name, '球员姓名'),
                'name_original' => $name,
                'position' => $athlete['position']['abbreviation'] ?? $athlete['position']['displayName'] ?? null,
                'shirt_number' => isset($athlete['jersey']) ? (int) $athlete['jersey'] : null,
                'birth_date' => isset($athlete['dateOfBirth']) ? date('Y-m-d', strtotime((string) $athlete['dateOfBirth'])) : null,
                'age' => $athlete['age'] ?? null,
                'club' => $athlete['defaultTeam']['displayName'] ?? null,
                'height_cm' => isset($athlete['height']) ? (int) round(((float) $athlete['height']) * 2.54) : null,
                'photo_url' => $athlete['headshot']['href'] ?? $athlete['headshot'] ?? null,
                'popularity_score' => $this->playerPopularity($athlete),
                'source_url' => $url,
            ]);
            $count++;
        }
        return $count;
    }

    private function playerPopularity(array $athlete): float
    {
        $score = 40.0;
        if (!empty($athlete['headshot'])) {
            $score += 8;
        }
        if (!empty($athlete['links']) && is_array($athlete['links'])) {
            $score += min(20, count($athlete['links']) * 1.4);
        }
        if (!empty($athlete['jersey'])) {
            $score += 4;
        }
        $position = $athlete['position']['abbreviation'] ?? '';
        if (in_array($position, ['F', 'M', 'FW', 'MF'], true)) {
            $score += 5;
        }
        return $score;
    }

    private function nextWikiTable(\DOMNode $node): ?\DOMElement
    {
        $cursor = $node->nextSibling;
        while ($cursor) {
            if ($cursor instanceof \DOMElement && $cursor->tagName === 'table' && str_contains((string) $cursor->getAttribute('class'), 'wikitable')) {
                return $cursor;
            }
            if ($cursor instanceof \DOMElement && in_array($cursor->tagName, ['h2', 'h3'], true)) {
                return null;
            }
            $cursor = $cursor->nextSibling;
        }
        return null;
    }

    /** @param array<int, string> $values */
    private function guessPlayerName(array $values): ?string
    {
        foreach ($values as $value) {
            if (preg_match('/[A-Za-z][A-Za-z .\'-]{2,}/', $value) && !preg_match('/^(GK|DF|MF|FW|No\.?|Pos\.?)$/i', $value)) {
                return trim($value);
            }
        }
        return null;
    }

    /** @param array<int, string> $values */
    private function guessPosition(array $values): ?string
    {
        foreach ($values as $value) {
            if (preg_match('/^(GK|DF|MF|FW)$/i', $value)) {
                return strtoupper($value);
            }
        }
        return null;
    }

    /** @param array<int, string> $values */
    private function guessNumber(array $values): ?int
    {
        foreach ($values as $value) {
            if (preg_match('/^\d{1,2}$/', $value)) {
                return (int) $value;
            }
        }
        return null;
    }

    /** @param array<int, string> $values */
    private function guessTrailingNumber(array $values, int $offset): ?int
    {
        $value = $values[count($values) + $offset] ?? null;
        return is_string($value) && preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    /** @param array<int, string> $values */
    private function guessClub(array $values): ?string
    {
        return count($values) >= 5 ? end($values) ?: null : null;
    }

    private function stageFromNote(string $note, string $fallback): string
    {
        if (str_contains($note, ',')) {
            return trim((string) substr($note, strpos($note, ',') + 1));
        }
        return $fallback ?: 'World Cup';
    }

    private function groupFromNote(string $note): ?string
    {
        return preg_match('/Group\s+([A-Z])/i', $note, $m) ? strtoupper($m[1]) : null;
    }

    private function eventLink(array $event): ?string
    {
        foreach (($event['links'] ?? []) as $link) {
            if (in_array('summary', $link['rel'] ?? [], true) && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }
        return null;
    }

    private function markSource(string $key, string $status, ?string $error = null): void
    {
        $stmt = Database::pdo()->prepare('UPDATE data_sources SET last_synced_at = NOW(), last_status = ?, last_error = ? WHERE source_key = ?');
        $stmt->execute([$status, $error, $key]);
    }

    private function countryCodeFromTeam(array $team): ?string
    {
        $code = strtoupper((string) ($team['abbreviation'] ?? ''));
        if (strlen($code) === 2) {
            return $code;
        }
        $map = [
            'ALG' => 'DZ', 'ARG' => 'AR', 'AUS' => 'AU', 'AUT' => 'AT', 'BEL' => 'BE', 'BRA' => 'BR',
            'CAN' => 'CA', 'CHI' => 'CL', 'COL' => 'CO', 'CRC' => 'CR', 'CRO' => 'HR', 'CZE' => 'CZ',
            'DEN' => 'DK', 'ECU' => 'EC', 'EGY' => 'EG', 'ENG' => 'GB', 'FRA' => 'FR', 'GER' => 'DE',
            'GHA' => 'GH', 'IRN' => 'IR', 'ITA' => 'IT', 'JPN' => 'JP', 'KOR' => 'KR', 'MAR' => 'MA',
            'MEX' => 'MX', 'NED' => 'NL', 'NGA' => 'NG', 'NOR' => 'NO', 'PAR' => 'PY', 'POL' => 'PL',
            'POR' => 'PT', 'QAT' => 'QA', 'RSA' => 'ZA', 'SCO' => 'GB', 'SEN' => 'SN', 'ESP' => 'ES',
            'SUI' => 'CH', 'TUN' => 'TN', 'TUR' => 'TR', 'URU' => 'UY', 'USA' => 'US', 'WAL' => 'GB',
        ];
        return $map[$code] ?? null;
    }

    private function countryCodeFromName(string $name): ?string
    {
        $key = strtolower(trim($name));
        $map = [
            'algeria' => 'DZ', 'argentina' => 'AR', 'australia' => 'AU', 'austria' => 'AT',
            'belgium' => 'BE', 'brazil' => 'BR', 'canada' => 'CA', 'chile' => 'CL',
            'colombia' => 'CO', 'costa rica' => 'CR', 'croatia' => 'HR', 'czech republic' => 'CZ',
            'czechia' => 'CZ', 'denmark' => 'DK', 'ecuador' => 'EC', 'egypt' => 'EG',
            'england' => 'GB', 'france' => 'FR', 'germany' => 'DE', 'ghana' => 'GH',
            'iran' => 'IR', 'italy' => 'IT', 'japan' => 'JP', 'korea republic' => 'KR',
            'south korea' => 'KR', 'mexico' => 'MX', 'morocco' => 'MA', 'netherlands' => 'NL',
            'nigeria' => 'NG', 'norway' => 'NO', 'paraguay' => 'PY', 'poland' => 'PL',
            'portugal' => 'PT', 'qatar' => 'QA', 'scotland' => 'GB', 'senegal' => 'SN',
            'south africa' => 'ZA', 'spain' => 'ES', 'switzerland' => 'CH', 'tunisia' => 'TN',
            'turkey' => 'TR', 'turkiye' => 'TR', 'uruguay' => 'UY', 'united states' => 'US',
            'usa' => 'US', 'wales' => 'GB',
        ];
        return $map[$key] ?? null;
    }

    private function countryCodeFromCountryName(string $name): ?string
    {
        $map = ['Mexico' => 'MX', 'USA' => 'US', 'United States' => 'US', 'Canada' => 'CA'];
        return $map[$name] ?? null;
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

    private function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?: '', '-'));
        return $slug !== '' ? $slug : 'item-' . bin2hex(random_bytes(4));
    }
}
