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
        $url = (string) env('ESPN_SCOREBOARD_URL');
        $json = json_decode($this->http->get($url), true);
        if (!is_array($json)) {
            throw new \RuntimeException('ESPN scoreboard did not return JSON.');
        }

        $importedTeams = 0;
        $importedMatches = 0;
        foreach (($json['events'] ?? []) as $event) {
            $competition = $event['competitions'][0] ?? [];
            $teamIds = [];
            foreach (($competition['competitors'] ?? []) as $competitor) {
                $team = $competitor['team'] ?? [];
                $name = (string) ($team['displayName'] ?? $team['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $teamIds[$competitor['homeAway'] ?? count($teamIds)] = $this->repo->upsertTeam([
                    'fifa_id' => isset($team['id']) ? 'espn-' . $team['id'] : null,
                    'slug' => $this->slug($name),
                    'code' => $team['abbreviation'] ?? null,
                    'name_cn' => $this->ark->translateToChinese($name, '球队名'),
                    'name_original' => $name,
                    'country_code' => $this->countryCodeFromTeam($team),
                    'flag_emoji' => $this->flagEmoji($this->countryCodeFromTeam($team)),
                    'source_url' => $url,
                ]);
                $importedTeams++;
            }

            $home = $this->competitor($competition, 'home');
            $away = $this->competitor($competition, 'away');
            $statusName = strtolower((string) ($competition['status']['type']['name'] ?? $event['status']['type']['name'] ?? 'unknown'));
            $status = match (true) {
                str_contains($statusName, 'final') => 'finished',
                str_contains($statusName, 'in') || str_contains($statusName, 'progress') => 'live',
                str_contains($statusName, 'pre') || str_contains($statusName, 'scheduled') => 'scheduled',
                default => 'unknown',
            };

            $this->repo->upsertMatch([
                'external_id' => isset($event['id']) ? 'espn-' . $event['id'] : null,
                'stage' => $event['season']['slug'] ?? $event['shortName'] ?? 'World Cup',
                'group_name' => null,
                'home_team_id' => $teamIds['home'] ?? null,
                'away_team_id' => $teamIds['away'] ?? null,
                'home_team_name' => $home['team']['displayName'] ?? null,
                'away_team_name' => $away['team']['displayName'] ?? null,
                'home_score' => isset($home['score']) && $home['score'] !== '' ? (int) $home['score'] : null,
                'away_score' => isset($away['score']) && $away['score'] !== '' ? (int) $away['score'] : null,
                'status' => $status,
                'starts_at' => isset($event['date']) ? date('Y-m-d H:i:s', strtotime((string) $event['date'])) : null,
                'source_url' => $url,
            ]);
            $importedMatches++;
        }

        $this->markSource('espn_scoreboard', 'ok');
        return ['teams' => $importedTeams, 'matches' => $importedMatches, 'source' => $url];
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

            $teamId = $this->repo->upsertTeam([
                'slug' => $this->slug($teamName),
                'name_cn' => $this->ark->translateToChinese($teamName, '球队名'),
                'name_original' => $teamName,
                'country_code' => $this->countryCodeFromName($teamName),
                'flag_emoji' => $this->flagEmoji($this->countryCodeFromName($teamName)),
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

    private function competitor(array $competition, string $homeAway): array
    {
        foreach (($competition['competitors'] ?? []) as $competitor) {
            if (($competitor['homeAway'] ?? '') === $homeAway) {
                return $competitor;
            }
        }
        return [];
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
            'ARG' => 'AR', 'AUS' => 'AU', 'AUT' => 'AT', 'BEL' => 'BE', 'BRA' => 'BR', 'CAN' => 'CA',
            'CHI' => 'CL', 'COL' => 'CO', 'CRC' => 'CR', 'CRO' => 'HR', 'CZE' => 'CZ', 'DEN' => 'DK',
            'ECU' => 'EC', 'ENG' => 'GB', 'FRA' => 'FR', 'GER' => 'DE', 'GHA' => 'GH', 'IRN' => 'IR',
            'ITA' => 'IT', 'JPN' => 'JP', 'KOR' => 'KR', 'MEX' => 'MX', 'MAR' => 'MA', 'NED' => 'NL',
            'NOR' => 'NO', 'PAR' => 'PY', 'POL' => 'PL', 'POR' => 'PT', 'QAT' => 'QA', 'SCO' => 'GB',
            'SEN' => 'SN', 'ESP' => 'ES', 'SUI' => 'CH', 'TUN' => 'TN', 'TUR' => 'TR', 'URU' => 'UY',
            'USA' => 'US', 'WAL' => 'GB',
        ];
        return $map[$code] ?? null;
    }

    private function countryCodeFromName(string $name): ?string
    {
        $key = strtolower(trim($name));
        $map = [
            'argentina' => 'AR', 'australia' => 'AU', 'austria' => 'AT', 'belgium' => 'BE', 'brazil' => 'BR',
            'canada' => 'CA', 'chile' => 'CL', 'colombia' => 'CO', 'costa rica' => 'CR', 'croatia' => 'HR',
            'czech republic' => 'CZ', 'denmark' => 'DK', 'ecuador' => 'EC', 'england' => 'GB',
            'france' => 'FR', 'germany' => 'DE', 'ghana' => 'GH', 'iran' => 'IR', 'italy' => 'IT',
            'japan' => 'JP', 'korea republic' => 'KR', 'south korea' => 'KR', 'mexico' => 'MX',
            'morocco' => 'MA', 'netherlands' => 'NL', 'norway' => 'NO', 'paraguay' => 'PY',
            'poland' => 'PL', 'portugal' => 'PT', 'qatar' => 'QA', 'scotland' => 'GB',
            'senegal' => 'SN', 'spain' => 'ES', 'switzerland' => 'CH', 'tunisia' => 'TN',
            'turkey' => 'TR', 'turkiye' => 'TR', 'uruguay' => 'UY', 'united states' => 'US',
            'usa' => 'US', 'wales' => 'GB',
        ];
        return $map[$key] ?? null;
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
