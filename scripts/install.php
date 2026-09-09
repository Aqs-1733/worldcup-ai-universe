<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$host = (string) env('DB_HOST', '127.0.0.1');
$port = (string) env('DB_PORT', '3306');
$charset = (string) env('DB_CHARSET', 'utf8mb4');
$username = (string) env('DB_USERNAME', 'root');
$password = (string) env('DB_PASSWORD', '');

$server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
    $server->exec($statement);
}

$pdo = Database::pdo();

$pdo->exec("INSERT IGNORE INTO roles (name, label) VALUES ('admin', '管理员'), ('user', '普通用户')");

$tags = [
    ['match', '比赛内容', '#16a34a'],
    ['tactics', '战术分析', '#2563eb'],
    ['team', '球队动态', '#f59e0b'],
    ['fan', '球迷内容', '#ec4899'],
    ['entertainment', '娱乐内容', '#ef4444'],
    ['fanmade', '二创内容', '#8b5cf6'],
    ['worldcup', '世界杯', '#22c55e'],
    ['football', '足球', '#06b6d4'],
    ['technology', '技术', '#64748b'],
];
$tagStmt = $pdo->prepare('INSERT IGNORE INTO tags (slug, name_cn, color) VALUES (?, ?, ?)');
foreach ($tags as $tag) {
    $tagStmt->execute($tag);
}

$sources = [
    ['fifa_teams', 'FIFA Teams', (string) env('FIFA_TEAMS_URL'), 'official', 100],
    ['fifa_fixtures', 'FIFA Scores & Fixtures', (string) env('FIFA_FIXTURES_URL'), 'official', 100],
    ['fifa_standings', 'FIFA Standings', (string) env('FIFA_STANDINGS_URL'), 'official', 100],
    ['espn_scoreboard', 'ESPN FIFA World Cup Scoreboard', (string) env('ESPN_SCOREBOARD_URL'), 'api', 88],
    ['espn_standings', 'ESPN FIFA World Cup Standings', (string) env('ESPN_STANDINGS_URL'), 'api', 88],
    ['wikipedia_squads', 'Wikipedia 2026 FIFA World Cup squads', (string) env('WIKIPEDIA_SQUADS_URL'), 'wiki', 70],
];
$sourceStmt = $pdo->prepare('INSERT INTO data_sources (source_key, name, url, source_type, trust_level) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE url = VALUES(url), trust_level = VALUES(trust_level)');
foreach ($sources as $source) {
    $sourceStmt->execute($source);
}

$newsSources = [
    ['BBC Sport Football', 'https://www.bbc.com/sport/football', 'https://feeds.bbci.co.uk/sport/football/rss.xml', 'en', 86],
    ['ESPN Soccer', 'https://www.espn.com/soccer/', 'https://www.espn.com/espn/rss/soccer/news', 'en', 84],
    ['The Guardian Football', 'https://www.theguardian.com/football', 'https://www.theguardian.com/football/rss', 'en', 82],
    ['Sky Sports Football', 'https://www.skysports.com/football', 'https://www.skysports.com/rss/12040', 'en', 78],
    ['新华社体育', 'https://www.news.cn/sports/', 'https://english.news.cn/rss/sports.xml', 'en', 82],
];
$newsStmt = $pdo->prepare('INSERT INTO news_sources (name, url, rss_url, language_code, trust_level) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rss_url = VALUES(rss_url), trust_level = VALUES(trust_level)');
foreach ($newsSources as $source) {
    $newsStmt->execute($source);
}

$memberStmt = $pdo->prepare('INSERT INTO team_members (name, student_no, role_name, bio, sort_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role_name = VALUES(role_name)');
$seedMembers = [
    ['成员1', '', '项目负责人', '负责需求整理、答辩组织和整体验收。', 1],
    ['成员2', '', '前端与交互', '负责世界杯页面、动效和用户体验。', 2],
    ['成员3', '', '后端与数据库', '负责 PHP、MySQL、登录注册和后台管理。', 3],
    ['成员4', '', '数据与文档', '负责真实数据来源、新闻同步和需求文档。', 4],
];
foreach ($seedMembers as $member) {
    $memberStmt->execute($member);
}

$adminUsername = (string) env('ADMIN_USERNAME', 'admin');
$adminEmail = (string) env('ADMIN_EMAIL', 'admin@example.com');
$adminPassword = (string) env('ADMIN_PASSWORD', 'ChangeMe2026!');
$adminStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$adminStmt->execute([$adminUsername, $adminEmail]);
$adminId = (int) ($adminStmt->fetchColumn() ?: 0);
if (!$adminId) {
    $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, survey_completed_at) VALUES (?, ?, ?, ?, NOW())')
        ->execute([$adminUsername, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), '管理员']);
    $adminId = (int) $pdo->lastInsertId();
}
$roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
$pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$adminId, $roleId]);

echo "Install complete.\n";
echo "Admin: {$adminUsername}\n";
echo "Next: php scripts/sync_worldcup.php && php scripts/sync_news.php\n";
