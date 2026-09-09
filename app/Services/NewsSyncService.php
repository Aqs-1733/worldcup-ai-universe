<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppRepository;
use PDO;

final class NewsSyncService
{
    public function __construct(
        private readonly AppRepository $repo = new AppRepository(),
        private readonly HttpClient $http = new HttpClient(),
        private readonly ArkClient $ark = new ArkClient(),
    ) {
    }

    /** @return array{fetched:int,inserted:int,failed:int,errors:array<int,string>} */
    public function sync(int $maxPerSource = 12): array
    {
        $pdo = Database::pdo();
        $sources = $pdo->query('SELECT * FROM news_sources WHERE is_enabled = 1 AND rss_url IS NOT NULL ORDER BY trust_level DESC')->fetchAll();
        $result = ['fetched' => 0, 'inserted' => 0, 'failed' => 0, 'errors' => []];

        foreach ($sources as $source) {
            try {
                $xmlBody = $this->http->get((string) $source['rss_url']);
                $xml = @simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$xml) {
                    throw new \RuntimeException('RSS parse failed.');
                }

                $items = $xml->channel->item ?? $xml->entry ?? [];
                $count = 0;
                foreach ($items as $item) {
                    if ($count >= $maxPerSource) {
                        break;
                    }
                    $article = $this->articleFromItem((array) $source, $item);
                    if (!$article) {
                        continue;
                    }
                    $tags = $this->classify($article['title_original'] . ' ' . ($article['summary_original'] ?? ''));
                    $articleId = $this->repo->upsertNews($article, $tags);
                    $result['fetched']++;
                    if ($articleId > 0) {
                        $result['inserted']++;
                    }
                    $count++;
                }

                $stmt = $pdo->prepare('UPDATE news_sources SET last_synced_at = NOW(), last_error = NULL WHERE id = ?');
                $stmt->execute([$source['id']]);
            } catch (\Throwable $error) {
                $result['failed']++;
                $result['errors'][] = $source['name'] . ': ' . $error->getMessage();
                $stmt = $pdo->prepare('UPDATE news_sources SET last_error = ? WHERE id = ?');
                $stmt->execute([$error->getMessage(), $source['id']]);
            }
        }

        return $result;
    }

    private function articleFromItem(array $source, mixed $item): ?array
    {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link['href'] ?? $item->link ?? ''));
        if ($title === '' || $link === '') {
            return null;
        }

        $summary = trim(strip_tags((string) ($item->description ?? $item->summary ?? '')));
        $content = trim(strip_tags((string) ($item->children('content', true)->encoded ?? $summary)));
        $publishedRaw = trim((string) ($item->pubDate ?? $item->published ?? $item->updated ?? ''));
        $publishedAt = $publishedRaw ? date('Y-m-d H:i:s', strtotime($publishedRaw) ?: time()) : null;
        $language = (string) ($source['language_code'] ?? 'en');
        $titleCn = is_chinese_text($title) ? $title : $this->ark->translateToChinese($title, '新闻标题');
        $summaryCn = $summary ? (is_chinese_text($summary) ? $summary : $this->ark->translateToChinese($summary, '新闻摘要')) : null;
        $contentCn = $content ? (is_chinese_text($content) ? $content : $this->ark->translateToChinese(mb_substr($content, 0, 1800), '新闻正文')) : null;

        return [
            'source_id' => $source['id'],
            'source_name' => $source['name'],
            'source_url' => $link,
            'title_cn' => $titleCn,
            'title_original' => $title,
            'summary_cn' => $summaryCn,
            'summary_original' => $summary ?: null,
            'content_cn' => $contentCn,
            'content_original' => $content ?: null,
            'language_code' => $language,
            'published_at' => $publishedAt,
            'credibility_score' => (int) $source['trust_level'],
            'translation_status' => ($titleCn || $summaryCn || $contentCn) ? 'translated' : 'none',
            'raw_payload' => ['rss_title' => $title, 'rss_link' => $link],
        ];
    }

    /** @return array<int, string> */
    private function classify(string $text): array
    {
        $text = mb_strtolower($text);
        $rules = [
            'match' => ['match', 'fixture', 'score', 'goal', '赛程', '比分', '进球', '淘汰赛', '小组赛'],
            'tactics' => ['tactic', 'formation', 'lineup', 'pressing', '战术', '阵型', '首发'],
            'team' => ['squad', 'team', 'coach', 'training', '球队', '名单', '教练', '训练'],
            'fan' => ['fans', 'supporter', 'stadium', '球迷', '看台', '助威'],
            'entertainment' => ['music', 'celebrity', 'culture', 'entertainment', '娱乐', '音乐', '活动'],
            'fanmade' => ['poster', 'meme', 'video', 'creative', '二创', '海报', '短视频'],
            'worldcup' => ['world cup', 'fifa', '世界杯'],
            'football' => ['football', 'soccer', '足球'],
            'technology' => ['ai', 'data', 'var', 'technology', '技术', '人工智能', '数据'],
        ];

        $tags = [];
        foreach ($rules as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) {
                    $tags[] = $slug;
                    break;
                }
            }
        }
        return $tags ?: ['worldcup'];
    }
}
