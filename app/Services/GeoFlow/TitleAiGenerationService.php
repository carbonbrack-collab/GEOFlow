<?php

namespace App\Services\GeoFlow;

use App\Models\AiModel;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Throwable;

use function Laravel\Ai\agent;

/**
 * 标题 AI 生成服务。
 *
 * 该服务负责：
 * 1. 基于 ai_models 配置发起真实模型调用；
 * 2. 在模型不可用时使用模板兜底，保证流程可用性；
 * 3. 输出统一结构，便于控制器处理入库逻辑。
 */
class TitleAiGenerationService
{
    /**
     * 复用统一 API Key 解密组件，避免标题生成链路与其他 AI 链路出现差异。
     */
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly AiUsageQuotaService $usageQuota,
    ) {}

    /**
     * 生成标题列表。
     *
     * @param  list<string>  $keywords
     * @return array{
     *   titles:list<string>,
     *   fallback_used:bool,
     *   fallback_reason:?string
     * }
     */
    public function generateTitles(
        AiModel $aiModel,
        array $keywords,
        int $count,
        string $style,
        string $customPrompt = ''
    ): array {
        $reservation = null;

        try {
            $reservation = $this->usageQuota->reserveModel($aiModel);
            if ($reservation === null) {
                throw new \RuntimeException('ai_daily_limit_reached');
            }

            $content = $this->requestTitlesFromModel($aiModel, $keywords, $count, $style, $customPrompt);
            $titles = $this->parseGeneratedTitles($content);
            if ($titles !== []) {
                try {
                    $this->usageQuota->recordModelSuccess($reservation);
                } catch (Throwable $exception) {
                    report($exception);
                }

                return [
                    'titles' => $titles,
                    'fallback_used' => false,
                    'fallback_reason' => null,
                ];
            }
        } catch (Throwable $exception) {
            if ($reservation instanceof AiUsageReservation) {
                $this->usageQuota->releaseModel($reservation);
            }

            return [
                'titles' => $this->generateMockTitles($keywords, $count, $style),
                'fallback_used' => true,
                'fallback_reason' => $exception->getMessage(),
            ];
        }

        if ($reservation instanceof AiUsageReservation) {
            $this->usageQuota->releaseModel($reservation);
        }

        return [
            'titles' => $this->generateMockTitles($keywords, $count, $style),
            'fallback_used' => true,
            'fallback_reason' => 'empty_result',
        ];
    }

    /**
     * 请求真实模型生成标题。
     *
     * @param  list<string>  $keywords
     */
    private function requestTitlesFromModel(
        AiModel $aiModel,
        array $keywords,
        int $count,
        string $style,
        string $customPrompt
    ): string {
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($aiModel->api_url ?? ''));
        if ($providerUrl === '') {
            throw new \RuntimeException('ai_url_missing');
        }

        $apiKey = $this->decryptApiKey((string) ($aiModel->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('ai_key_missing');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) ($aiModel->model_id ?? ''));
        $providerName = OpenAiRuntimeProvider::registerProvider('title_ai', $driver, $providerUrl, $apiKey);

        // 关键词是哪种语言，标题就用哪种语言：整套提示词随之切换，
        // 比只在末尾加一句「保持一致」可靠得多。
        if ($this->keywordsAreEnglish($keywords)) {
            $styleMap = [
                'professional' => 'professional and precise',
                'attractive' => 'eye-catching',
                'seo' => 'SEO-optimised',
                'creative' => 'creative and fresh',
                'question' => 'question-style',
            ];
            $styleDescription = $styleMap[$style] ?? 'professional and precise';
            $keywordsText = implode(', ', $keywords);

            $systemPrompt = "You write article headlines. Produce {$styleDescription} headlines from the given keywords.";
            $userPrompt = "Write {$count} {$styleDescription} article headlines for these keywords:\n\n{$keywordsText}\n\n";
            if ($customPrompt !== '') {
                $userPrompt .= "Additional requirements: {$customPrompt}\n\n";
            }
            $userPrompt .= "Rules:\n1. One headline per line\n2. Make them readable and compelling\n3. Suitable for search engines\n4. No numbering or bullet marks\n5. Output the headlines only\n6. Write every headline in English — never mix in another language";
        } else {
            $styleMap = [
                'professional' => '专业严谨的',
                'attractive' => '吸引眼球的',
                'seo' => 'SEO优化的',
                'creative' => '创意新颖的',
                'question' => '疑问式的',
            ];
            $styleDescription = $styleMap[$style] ?? '专业严谨的';
            $keywordsText = implode('、', $keywords);

            $systemPrompt = "你是一个专业的内容标题生成专家。请根据提供的关键词生成{$styleDescription}文章标题。";
            $userPrompt = "请基于以下关键词生成 {$count} 个{$styleDescription}文章标题：\n\n关键词：{$keywordsText}\n\n";
            if ($customPrompt !== '') {
                $userPrompt .= "额外要求：{$customPrompt}\n\n";
            }
            $userPrompt .= "要求：\n1. 每个标题独占一行\n2. 标题要有吸引力和可读性\n3. 适合搜索引擎优化\n4. 不要添加序号或其他标记\n5. 直接输出标题内容\n6. 标题必须与关键词使用同一种语言，不要中英混排";
        }

        try {
            $response = agent($systemPrompt)->prompt(
                $userPrompt,
                [],
                $providerName,
                (string) ($aiModel->model_id ?? '')
            );
        } catch (Throwable $exception) {
            throw new \RuntimeException(OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl), 0, $exception);
        }

        $rawContent = (string) ($response->text ?? '');
        $content = OpenAiRuntimeProvider::normalizeGeneratedText($rawContent);

        if ($content === '') {
            if (OpenAiRuntimeProvider::looksLikeSseCompletionPayload($rawContent)) {
                throw new \RuntimeException('ai_empty_stream_content');
            }

            throw new \RuntimeException('ai_empty_content');
        }

        return $content;
    }

    /**
     * 解析模型输出文本为标题列表。
     *
     * @return list<string>
     */
    private function parseGeneratedTitles(string $content): array
    {
        $content = $this->stripReasoning($content);

        $titles = [];
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $title = $this->cleanTitleLine($line);
            if ($title === '' || $this->looksLikeCommentary($title)) {
                continue;
            }
            $titles[] = $title;
        }

        return array_values(array_unique($titles));
    }

    /** 推理模型会输出 <think> 思考块，里面不是标题。 */
    private function stripReasoning(string $content): string
    {
        $content = (string) preg_replace('/<think\b[^>]*>.*?<\/think>/isu', '', $content);

        // 思考块被截断时只剩闭合标签，取其后的内容。
        $pos = strripos($content, '</think>');
        if ($pos !== false) {
            $content = substr($content, $pos + 8);
        }

        // 开标签没有闭合：整段都是思考过程，取最后一段空行之后的内容。
        if (stripos($content, '<think') !== false) {
            $parts = preg_split('/\R{2,}/u', $content) ?: [];
            $content = (string) end($parts);
            $content = (string) preg_replace('/<think\b[^>]*>/iu', '', $content);
        }

        return trim($content);
    }

    private function cleanTitleLine(string $line): string
    {
        $title = trim($line);

        // 列表符号、引用标记与序号
        $title = (string) preg_replace('/^[\-\*\+>\s]+/u', '', $title);
        $title = (string) preg_replace('/^\d+[\.\)\-、\s]*/u', '', $title);

        // 「关键词 → 标题」「关键词 - 标题」这类回显，只取箭头后的部分
        if (preg_match('/^.{1,80}?\s*(?:→|->|=>|：:)\s*(.+)$/u', $title, $m) === 1) {
            $title = $m[1];
        }

        // 包裹用的引号与星号。必须用带 u 标志的正则：trim() 的字符集按字节
        // 处理，中文引号会被拆成单字节，进而削坏相邻汉字。
        $title = (string) preg_replace('/^[\s"\'`*《》“”‘’]+|[\s"\'`*《》“”‘’]+$/u', '', $title);

        return trim($title);
    }

    /** 模型常在标题之间插入解说，这些不是标题。 */
    private function looksLikeCommentary(string $title): bool
    {
        // 阈值放宽：中文标题天然短，过滤靠标签与解说特征而非长度。
        if (mb_strlen($title) < 4 || mb_strlen($title) > 200) {
            return true;
        }

        if (str_ends_with($title, ':') || str_ends_with($title, '：')) {
            return true;
        }

        if (preg_match('/^<\/?[a-z]/i', $title) === 1) {
            return true;
        }

        $meta = [
            'let me', 'here are', 'here is', 'sure,', 'okay,', 'i will', "i'll ",
            'these titles', 'the following', 'as requested', 'note:', 'output:',
            '以下是', '好的', '这些标题', '如下',
        ];
        $lower = mb_strtolower($title);
        foreach ($meta as $needle) {
            if (str_starts_with($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解密 ai_models 中的 API Key（兼容旧系统 enc:v1 格式）。
     */
    private function decryptApiKey(string $storedApiKey): string
    {
        return $this->apiKeyCrypto->decrypt($storedApiKey);
    }

    /**
     * @return list<string>
     */
    /** 关键词整体是否为英文：无汉字且含足够拉丁字母。 */
    private function keywordsAreEnglish(array $keywords): bool
    {
        $text = implode(' ', array_map(static fn ($k): string => (string) $k, $keywords));
        preg_match_all('/\p{Han}/u', $text, $cjk);
        preg_match_all('/[A-Za-z]/', $text, $latin);

        return count($cjk[0] ?? []) === 0 && count($latin[0] ?? []) >= 3;
    }

    private function generateMockTitles(array $keywords, int $count, string $style): array
    {
        $styleTemplates = [
            'professional' => [
                '{keyword}的深度分析与研究',
                '关于{keyword}的专业见解',
                '{keyword}行业发展趋势报告',
            ],
            'attractive' => [
                '你绝对不知道的{keyword}秘密',
                '揭秘{keyword}背后的故事',
                '{keyword}让人意想不到的用途',
            ],
            'seo' => [
                '{keyword}完整指南：从入门到精通',
                '{keyword}常见问题解答大全',
                '如何选择最适合的{keyword}方案',
            ],
            'creative' => [
                '重新定义{keyword}的可能性',
                '如果{keyword}会说话，它会告诉你什么？',
                '当{keyword}遇上创新思维',
            ],
            'question' => [
                '{keyword}真的有用吗？',
                '为什么{keyword}如此重要？',
                '{keyword}的未来在哪里？',
            ],
        ];

        $templates = $styleTemplates[$style] ?? $styleTemplates['professional'];
        $titles = [];
        for ($index = 0; $index < $count; $index++) {
            $keyword = $keywords[array_rand($keywords)];
            $template = $templates[array_rand($templates)];
            $titles[] = str_replace('{keyword}', $keyword, $template);
        }

        return $titles;
    }
}
