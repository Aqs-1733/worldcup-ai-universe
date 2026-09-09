<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Response;
use App\Core\View;
use App\Repositories\AppRepository;
use App\Services\ArkClient;
use App\Services\NewsSyncService;

final class PageController
{
    private AppRepository $repo;

    public function __construct()
    {
        $this->repo = new AppRepository();
    }

    public function home(): string
    {
        return View::render('home', [
            'title' => '世界杯中控台',
            'counts' => $this->repo->counts(),
            'members' => $this->repo->teamMembers(),
            'teams' => array_slice($this->repo->teams(null, 12), 0, 12),
            'players' => array_slice($this->repo->players(null, null, 12), 0, 12),
            'matches' => $this->repo->matches(140),
            'news' => array_slice($this->repo->news([], 8), 0, 8),
            'standings' => $this->repo->standings(),
            'posts' => $this->repo->adminPosts(),
            'qualityChecks' => $this->repo->qualityChecks(),
        ]);
    }

    public function teams(): string
    {
        return View::render('teams', [
            'title' => '球队',
            'teams' => $this->repo->teams($_GET['q'] ?? null),
            'q' => (string) ($_GET['q'] ?? ''),
        ]);
    }

    public function team(string $id): string
    {
        $team = $this->repo->team($id);
        if (!$team) {
            http_response_code(404);
            return View::render('errors/404', ['title' => '球队不存在']);
        }

        return View::render('team', [
            'title' => ($team['name_cn'] ?: $team['name_original']) . ' 球队档案',
            'team' => $team,
            'players' => $this->repo->players(null, (int) $team['id'], 80),
            'comments' => $this->repo->comments('team', (int) $team['id']),
        ]);
    }

    public function players(): string
    {
        return View::render('players', [
            'title' => '球员',
            'players' => $this->repo->players($_GET['q'] ?? null),
            'teams' => $this->repo->teams(null, 100),
            'q' => (string) ($_GET['q'] ?? ''),
        ]);
    }

    public function player(string $id): string
    {
        $player = $this->repo->player($id);
        if (!$player) {
            http_response_code(404);
            return View::render('errors/404', ['title' => '球员不存在']);
        }

        return View::render('player', [
            'title' => ($player['name_cn'] ?: $player['name_original']) . ' 球员档案',
            'player' => $player,
            'comments' => $this->repo->comments('player', (int) $player['id']),
        ]);
    }

    public function worldcup(): string
    {
        return View::render('worldcup', [
            'title' => '赛程与积分',
            'matches' => $this->repo->matches(140),
            'standings' => $this->repo->standings(),
        ]);
    }

    public function news(): string
    {
        return View::render('news', [
            'title' => '新闻',
            'tags' => $this->repo->tags(),
            'articles' => $this->repo->news([
                'q' => $_GET['q'] ?? null,
                'tag' => $_GET['tag'] ?? null,
            ], 100),
            'q' => (string) ($_GET['q'] ?? ''),
            'activeTag' => (string) ($_GET['tag'] ?? ''),
        ]);
    }

    public function refreshNews(): never
    {
        Csrf::requireValid();
        try {
            $summary = (new NewsSyncService())->sync();
            Flash::set('success', "联网刷新完成：读取 {$summary['fetched']} 条，写入/更新 {$summary['inserted']} 条，失败源 {$summary['failed']} 个。");
        } catch (\Throwable $error) {
            Flash::set('error', '联网刷新失败：' . $error->getMessage());
        }
        redirect('/news');
    }

    public function refreshNewsApi(): string
    {
        try {
            $summary = (new NewsSyncService())->sync(6);
            return Response::json(['ok' => true, 'summary' => $summary]);
        } catch (\Throwable $error) {
            return Response::json(['ok' => false, 'message' => $error->getMessage()], 500);
        }
    }

    public function newsShow(string $id): string
    {
        $article = $this->repo->newsArticle((int) $id);
        if (!$article) {
            http_response_code(404);
            return View::render('errors/404', ['title' => '新闻不存在']);
        }
        return View::render('news_show', [
            'title' => $article['title_cn'] ?: $article['title_original'],
            'article' => $article,
        ]);
    }

    public function fanSpace(): string
    {
        $user = Auth::requireLogin();
        return View::render('fan_space', [
            'title' => '球迷空间',
            'user' => $user,
            'teams' => $this->repo->teams(null, 120),
            'players' => $this->repo->players(null, null, 160),
            'selected' => $this->repo->userPreferenceIds((int) $user['id']),
            'comments' => $this->repo->comments('site'),
        ]);
    }

    public function saveFanSpace(): never
    {
        Csrf::requireValid();
        $user = Auth::requireLogin();
        if (isset($_POST['comment_body']) && trim((string) $_POST['comment_body']) !== '') {
            $this->repo->addComment((int) $user['id'], (string) $_POST['comment_body']);
            Flash::set('success', '留言已发布。');
            redirect('/fan-space');
        }

        $this->repo->savePreferences(
            (int) $user['id'],
            $_POST['favorite_teams'] ?? [],
            $_POST['favorite_players'] ?? [],
            $_POST['blocked_teams'] ?? [],
            $_POST['push'] ?? []
        );
        Flash::set('success', '偏好已保存。');
        redirect('/fan-space');
    }

    public function onboarding(): string
    {
        $user = Auth::requireLogin();
        if (!empty($user['survey_completed_at'])) {
            redirect('/');
        }
        return View::render('onboarding', [
            'title' => '首次偏好',
            'teams' => $this->repo->teams(null, 120),
            'players' => $this->repo->players(null, null, 160),
        ]);
    }

    public function saveOnboarding(): never
    {
        Csrf::requireValid();
        $user = Auth::requireLogin();
        $skipped = isset($_POST['skip']);
        $this->repo->savePreferences(
            (int) $user['id'],
            $skipped ? [] : ($_POST['favorite_teams'] ?? []),
            $skipped ? [] : ($_POST['favorite_players'] ?? []),
            $skipped ? [] : ($_POST['blocked_teams'] ?? []),
            $skipped ? [] : ($_POST['push'] ?? []),
            $skipped
        );
        Flash::set('success', $skipped ? '已跳过问卷，之后可以在球迷空间补充。' : '偏好已保存。');
        redirect('/');
    }

    public function aiStudio(): string
    {
        return View::render('ai_studio', [
            'title' => 'AI 创作',
            'textReady' => (new ArkClient())->textReady(),
            'imageReady' => (new ArkClient())->imageReady(),
            'question' => (string) ($_GET['q'] ?? ''),
        ]);
    }

    public function aiStudioPost(): string
    {
        Csrf::requireValid();
        $ark = new ArkClient();
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        $tone = trim((string) ($_POST['tone'] ?? '中立、清楚'));
        $team = trim((string) ($_POST['target_team'] ?? '')) ?: null;
        $result = null;
        $image = null;
        $error = null;

        try {
            if ($prompt === '') {
                throw new \RuntimeException('请输入创作内容。');
            }
            $result = $ark->chat($prompt, $tone, $team);
            $this->repo->recordAiGeneration([
                'user_id' => Auth::id(),
                'generation_type' => 'chat',
                'prompt' => $prompt,
                'tone' => $tone,
                'target_team' => $team,
                'response_text' => $result,
                'model_name' => env('ARK_MODEL'),
            ]);

            if (!empty($_POST['generate_image'])) {
                $imagePrompt = trim((string) ($_POST['image_prompt'] ?? ''));
                $image = $ark->image($imagePrompt !== '' ? $imagePrompt : $prompt);
                $this->repo->recordAiGeneration([
                    'user_id' => Auth::id(),
                    'generation_type' => 'image',
                    'prompt' => $imagePrompt !== '' ? $imagePrompt : $prompt,
                    'tone' => $tone,
                    'target_team' => $team,
                    'image_url' => $image['url'] ?? $image['b64'] ?? null,
                    'model_name' => env('ARK_IMAGE_MODEL'),
                ]);
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
            $this->repo->recordAiGeneration([
                'user_id' => Auth::id(),
                'generation_type' => !empty($_POST['generate_image']) ? 'image' : 'chat',
                'prompt' => $prompt,
                'tone' => $tone,
                'target_team' => $team,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        return View::render('ai_studio', [
            'title' => 'AI 创作',
            'textReady' => $ark->textReady(),
            'imageReady' => $ark->imageReady(),
            'question' => $prompt,
            'tone' => $tone,
            'targetTeam' => $team,
            'result' => $result,
            'image' => $image,
            'error' => $error,
        ]);
    }

    public function vision(): string
    {
        return View::render('vision', ['title' => '视觉分析']);
    }

    public function visionPost(): string
    {
        Csrf::requireValid();
        $analysis = null;
        $error = null;
        $uploadedPath = null;

        try {
            if (empty($_FILES['image']['tmp_name'])) {
                throw new \RuntimeException('请选择图片。');
            }
            $info = getimagesize($_FILES['image']['tmp_name']);
            if (!$info) {
                throw new \RuntimeException('只支持真实图片文件。');
            }
            $ext = image_type_to_extension($info[2], false) ?: 'jpg';
            $name = 'vision-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target = ROOT_PATH . '/public/uploads/' . $name;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                throw new \RuntimeException('上传失败。');
            }
            $uploadedPath = '/uploads/' . $name;
            $analysis = '已保存图片。当前 PHP 版本保留视觉分析入口；若要接入图像大模型，可在 ARK 支持多模态模型后从后台配置。';
            $this->repo->recordAiGeneration([
                'user_id' => Auth::id(),
                'generation_type' => 'vision',
                'prompt' => $name,
                'response_text' => $analysis,
            ]);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return View::render('vision', [
            'title' => '视觉分析',
            'analysis' => $analysis,
            'error' => $error,
            'uploadedPath' => $uploadedPath,
        ]);
    }
}
