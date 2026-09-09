<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\AppRepository;

final class AdminController
{
    private const TABLES = [
        'team_members' => '团队成员',
        'teams' => '球队',
        'players' => '球员',
        'matches' => '赛程',
        'news_articles' => '新闻',
        'admin_posts' => '发布内容',
        'comments' => '留言',
        'source_files' => '来源文件',
        'data_quality_checks' => '数据校验',
        'player_statistics' => '球员统计',
        'match_lineups' => '比赛阵容',
        'match_events' => '比赛事件',
    ];

    public function dashboard(): string
    {
        Auth::requireAdmin();
        $repo = new AppRepository();
        return View::render('admin/dashboard', [
            'title' => '后台',
            'counts' => $repo->counts(),
            'tables' => self::TABLES,
        ]);
    }

    public function table(string $table): string
    {
        Auth::requireAdmin();
        if (!isset(self::TABLES[$table])) {
            http_response_code(404);
            return View::render('errors/404', ['title' => '后台表不存在']);
        }
        $repo = new AppRepository();
        return View::render('admin/table', [
            'title' => self::TABLES[$table] . '管理',
            'table' => $table,
            'label' => self::TABLES[$table],
            'rows' => $repo->tableRows($table),
            'teams' => $repo->teams(null, 120),
        ]);
    }

    public function save(string $table): never
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        (new AppRepository())->simpleSave($table, $_POST);
        Flash::set('success', '已保存。');
        redirect('/admin/' . $table);
    }

    public function delete(string $table): never
    {
        Auth::requireAdmin();
        Csrf::requireValid();
        (new AppRepository())->deleteRow($table, (int) ($_POST['id'] ?? 0));
        Flash::set('success', '已删除。');
        redirect('/admin/' . $table);
    }
}
