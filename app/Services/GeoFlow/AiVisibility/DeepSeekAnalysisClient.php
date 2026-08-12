<?php

namespace App\Services\GeoFlow\AiVisibility;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\AiVisibilityRun;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RuntimeException;
use Throwable;

final class DeepSeekAnalysisClient
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly AiVisibilityResultNormalizer $normalizer,
    ) {}

    /**
     * @param  list<AiVisibilitySourceData>  $sources
     * @param  array<string,mixed>  $options
     */
    public function analyze(AiModel $model, string $prompt, array $sources = [], array $options = []): AiVisibilityResult
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException(__('admin.runtime.deepseek.prompt_empty'));
        }

        $modelId = trim((string) ($model->model_id ?? ''));
        if ($modelId === '') {
            throw new RuntimeException(__('admin.runtime.deepseek.model_empty'));
        }

        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) ($model->api_url ?? ''));
        if ($providerUrl === '') {
            throw new RuntimeException(__('admin.runtime.deepseek.url_empty'));
        }

        $apiKey = $this->apiKeyCrypto->decrypt((string) ($model->getRawOriginal('api_key') ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException(__('admin.runtime.deepseek.key_empty'));
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, $modelId);
        $providerName = OpenAiRuntimeProvider::registerProvider('ai_visibility_deepseek', $driver, $providerUrl, $apiKey);
        $maxTokens = (int) ($options['max_tokens']
            ?? $model->max_tokens
            ?? config('geoflow.ai_visibility.default_analysis_max_tokens', 4096));
        $agent = new MarkdownContentWriterAgent(
            instructions: '你是 GEO/AI 可见性分析助手。请基于输入的 AI 回答和信源做可执行分析，明确区分事实、推断和投放建议。',
            maxTokens: $maxTokens > 0 ? $maxTokens : null,
        );

        $fullPrompt = $this->buildPrompt($prompt, $sources);
        $request = [
            'provider_url' => $providerUrl,
            'model_id' => $modelId,
            'prompt' => $fullPrompt,
            'source_count' => count($sources),
        ];

        $startedAt = hrtime(true);
        try {
            $response = $agent->prompt($fullPrompt, [], $providerName, $modelId);
        } catch (Throwable $exception) {
            throw new RuntimeException(__('admin.runtime.deepseek.failed', ['message' => OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl)]), 0, $exception);
        }
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $rawText = (string) ($response->text ?? '');
        $answerText = OpenAiRuntimeProvider::normalizeGeneratedText($rawText);
        if ($answerText === '') {
            throw new RuntimeException(__('admin.runtime.deepseek.empty'));
        }

        $usage = $this->extractUsage($response);

        return $this->normalizer->normalizeTextAnalysis(
            providerType: AiVisibilityRun::PROVIDER_DEEPSEEK_ANALYSIS,
            providerKey: 'deepseek',
            modelId: $modelId,
            answerText: $answerText,
            sources: $sources,
            usage: $usage,
            request: $request,
            rawResponse: [
                'text' => $rawText,
                'usage' => $usage,
            ],
            latencyMs: $latencyMs,
        );
    }

    /**
     * @param  list<AiVisibilitySourceData>  $sources
     */
    private function buildPrompt(string $prompt, array $sources): string
    {
        if ($sources === []) {
            return $prompt;
        }

        $sourceLines = [];
        foreach ($sources as $source) {
            $title = $source->title !== null ? $source->title : 'Untitled source';
            $url = $source->url !== null ? ' - '.$source->url : '';
            $summary = $source->summary ?? $source->snippet ?? '';
            $summary = $summary !== '' ? "\n摘要：".mb_substr($summary, 0, 500, 'UTF-8') : '';
            $sourceLines[] = sprintf('[%s] %s%s%s', $source->citationKey ?? 'S?', $title, $url, $summary);
        }

        return $prompt."\n\n可用信源如下（这些是外部搜索/工具返回的信源，不代表 DeepSeek 原生引用）：\n".implode("\n\n", $sourceLines);
    }

    /**
     * @return array<string,mixed>
     */
    private function extractUsage(object $response): array
    {
        $usage = $response->usage ?? null;
        if ($usage instanceof Arrayable) {
            return $usage->toArray();
        }
        if ($usage instanceof JsonSerializable) {
            $serialized = $usage->jsonSerialize();

            return is_array($serialized) ? $serialized : [];
        }

        return is_array($usage) ? $usage : [];
    }
}
