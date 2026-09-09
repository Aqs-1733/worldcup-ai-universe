<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ArkClient
{
    public function __construct(private readonly HttpClient $http = new HttpClient())
    {
    }

    public function textReady(): bool
    {
        return (bool) env('ARK_API_KEY') && (bool) env('ARK_MODEL');
    }

    public function imageReady(): bool
    {
        return (bool) env('ARK_API_KEY') && (bool) env('ARK_IMAGE_MODEL');
    }

    public function chat(string $prompt, string $tone = '中立', ?string $team = null, int $timeout = 90): string
    {
        if (!$this->textReady()) {
            throw new RuntimeException('未配置 ARK 文本模型：请在 .env 设置 ARK_API_KEY 和 ARK_MODEL。');
        }

        $system = '你是世界杯数据平台的中文体育编辑。回答要基于已给出的事实，不编造未确认比赛、球员或新闻。';
        if ($team) {
            $system .= ' 用户关注的球队是：' . $team . '。';
        }
        $system .= ' 语气要求：' . $tone . '。';

        $json = $this->http->postJson($this->endpoint('/chat/completions'), [
            'model' => (string) env('ARK_MODEL'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.4,
        ], $this->headers(), $timeout);

        return (string) ($json['choices'][0]['message']['content'] ?? '');
    }

    public function translateToChinese(string $text, string $kind = '体育资讯'): ?string
    {
        $text = trim($text);
        if ($text === '' || is_chinese_text($text) || !$this->textReady()) {
            return is_chinese_text($text) ? $text : null;
        }

        try {
            return $this->chat("请把下面{$kind}翻译成简体中文，只输出译文，不要添加解释：\n\n{$text}", '准确、简洁');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{url:?string,b64:?string,raw:array<string,mixed>} */
    public function image(string $prompt): array
    {
        if (!$this->imageReady()) {
            throw new RuntimeException('未配置 ARK 图片生成模型：请在 .env 设置 ARK_API_KEY 和 ARK_IMAGE_MODEL。');
        }

        $payload = [
            'model' => (string) env('ARK_IMAGE_MODEL'),
            'prompt' => mb_substr(trim($prompt), 0, 300),
            'size' => (string) env('ARK_IMAGE_SIZE', '1024x1024'),
            'response_format' => (string) env('ARK_IMAGE_RESPONSE_FORMAT', 'url'),
        ];

        $json = $this->http->postJson($this->endpoint('/images/generations', 'ARK_IMAGE_BASE_URL'), $payload, $this->headers(), 180);
        $item = $json['data'][0] ?? [];
        return [
            'url' => isset($item['url']) ? (string) $item['url'] : null,
            'b64' => isset($item['b64_json']) ? 'data:image/png;base64,' . $item['b64_json'] : null,
            'raw' => $json,
        ];
    }

    private function endpoint(string $path, string $baseEnv = 'ARK_OPENAI_BASE_URL'): string
    {
        $base = rtrim((string) env($baseEnv, 'https://ark.cn-beijing.volces.com/api/v3'), '/');
        return $base . $path;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . (string) env('ARK_API_KEY')];
    }
}
