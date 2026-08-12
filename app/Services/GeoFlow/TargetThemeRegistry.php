<?php

namespace App\Services\GeoFlow;

/**
 * 目标站点主题登记表。
 *
 * 每套主题是 resources/target-themes/ 下的一个 CSS 文件，文件名即主题键。
 * 新增主题只需放一个 CSS 文件，不需要改代码。
 */
final class TargetThemeRegistry
{
    public const DEFAULT_KEY = 'default';

    public const CLASS_PREFIX = 'target-theme-';

    /**
     * 历史 template_key 到主题键的别名，保证已部署渠道升级后外观不变。
     *
     * @var array<string, string>
     */
    private const LEGACY_ALIASES = [
        'apparel-sourcing-intelligence' => 'apparel',
        'boutiquesourcingpro' => 'boutique',
        'fashion-insight' => 'fashion',
    ];

    /** @var list<string>|null */
    private static ?array $cachedKeys = null;

    public static function directory(): string
    {
        return base_path('resources/target-themes');
    }

    /**
     * 可用主题键，按字母序；default 永远排在最前。
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        if (self::$cachedKeys !== null) {
            return self::$cachedKeys;
        }

        $keys = [];
        foreach (glob(self::directory().'/*.css') ?: [] as $path) {
            $key = strtolower(trim(basename($path, '.css')));
            if ($key !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) === 1) {
                $keys[] = $key;
            }
        }

        sort($keys);
        $keys = array_values(array_unique(array_merge([self::DEFAULT_KEY], $keys)));

        return self::$cachedKeys = $keys;
    }

    /** 测试或热更新后清空缓存。 */
    public static function flush(): void
    {
        self::$cachedKeys = null;
    }

    /**
     * 把渠道的 template_key 解析成主题键。
     *
     * 依次尝试：精确匹配 → 历史别名 → 子串包含（长键优先，避免 default 抢占）。
     */
    public static function resolveKey(string $templateKey): string
    {
        $value = strtolower(trim($templateKey));
        if ($value === '') {
            return self::DEFAULT_KEY;
        }

        $keys = self::keys();
        if (in_array($value, $keys, true)) {
            return $value;
        }

        foreach (self::LEGACY_ALIASES as $needle => $key) {
            if (str_contains($value, $needle) && in_array($key, $keys, true)) {
                return $key;
            }
        }

        $candidates = array_values(array_filter($keys, static fn (string $k): bool => $k !== self::DEFAULT_KEY));
        usort($candidates, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($candidates as $key) {
            if (str_contains($value, $key)) {
                return $key;
            }
        }

        return self::DEFAULT_KEY;
    }

    /** 主题键对应的 body class。 */
    public static function bodyClass(string $templateKey): string
    {
        return self::CLASS_PREFIX.self::resolveKey($templateKey);
    }

    /**
     * 供目标站点自行解析用的映射表：匹配串 => body class。
     *
     * 顺序即匹配优先级，目标站点按序做 str_contains。
     *
     * @return array<string, string>
     */
    public static function matchMap(): array
    {
        $map = [];
        foreach (self::LEGACY_ALIASES as $needle => $key) {
            if (in_array($key, self::keys(), true)) {
                $map[$needle] = self::CLASS_PREFIX.$key;
            }
        }

        $candidates = array_values(array_filter(
            self::keys(),
            static fn (string $k): bool => $k !== self::DEFAULT_KEY
        ));
        usort($candidates, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($candidates as $key) {
            $map[$key] = self::CLASS_PREFIX.$key;
        }

        return $map;
    }

    /** 全部主题的 CSS，随站点包一起下发，便于目标站点后续切换主题。 */
    public static function css(): string
    {
        $blocks = [];
        foreach (self::keys() as $key) {
            $path = self::directory().'/'.$key.'.css';
            if (! is_file($path)) {
                continue;
            }
            $css = trim((string) file_get_contents($path));
            if ($css !== '') {
                $blocks[] = $css;
            }
        }

        return implode("\n", $blocks);
    }
}
