<?php

namespace App\Support\GeoFlow;

use App\Models\Article;
use Illuminate\Support\Str;

final class ArticleWorkflow
{
    public static function normalizeState(string $status, string $reviewStatus, ?string $publishedAt = null): array
    {
        $allowedStatuses = ['draft', 'published', 'private'];
        $allowedReviewStatuses = ['pending', 'approved', 'rejected', 'auto_approved'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'draft';
        }

        if (! in_array($reviewStatus, $allowedReviewStatuses, true)) {
            $reviewStatus = 'pending';
        }

        if (in_array($reviewStatus, ['pending', 'rejected'], true)) {
            $status = 'draft';
        }

        if ($status === 'published' && in_array($reviewStatus, ['pending', 'rejected'], true)) {
            $reviewStatus = 'approved';
        }

        if ($status !== 'published' && $reviewStatus === 'auto_approved') {
            $status = 'published';
        }

        if ($status === 'published' && $reviewStatus === 'pending') {
            $reviewStatus = 'approved';
        }

        if ($status === 'published') {
            $publishedAt = $publishedAt ?: date('Y-m-d H:i:s');
        } else {
            $publishedAt = null;
        }

        return [
            'status' => $status,
            'review_status' => $reviewStatus,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * 由标题生成 URL 别名。
     *
     * 原实现直接返回随机串，URL 里没有任何关键词信号（/article/1v4iu27b/）。
     * 现在优先用标题，转不出可用字符时（例如纯中文标题）才回落随机串。
     */
    public static function generateUniqueSlug(string $title, ?int $excludeArticleId = null): string
    {
        $base = self::slugFromTitle($title);
        $slug = $base !== '' ? $base : self::randomSlug(8);

        $attempt = 1;
        while (true) {
            try {
                $q = Article::withTrashed()->where('slug', $slug);
                if ($excludeArticleId !== null) {
                    $q->where('id', '!=', $excludeArticleId);
                }

                if (! $q->exists()) {
                    return $slug;
                }

                $attempt++;
                if ($base === '' || $attempt > 20) {
                    $slug = self::randomSlug(8);

                    continue;
                }

                $slug = $base.'-'.$attempt;
            } catch (\Throwable) {
                return self::randomSlug(8);
            }
        }
    }

    /** 标题转 URL 别名：小写、连字符分隔，按词边界截到 70 字符内。 */
    public static function slugFromTitle(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            return '';
        }

        if (mb_strlen($slug) > 70) {
            $slug = mb_substr($slug, 0, 70);
            $lastDash = mb_strrpos($slug, '-');
            if ($lastDash !== false && $lastDash >= 30) {
                $slug = mb_substr($slug, 0, $lastDash);
            }
        }

        return trim($slug, '-');
    }

    private static function randomSlug(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $slug;
    }
}
