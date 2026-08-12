<?php

namespace App\Support\Admin;

use App\Models\Admin;

/**
 * 后台主导航定义。
 *
 * 抽出来是为了让左侧边栏和顶栏共用同一份菜单与选中判定，
 * 避免两处各写一遍导致高亮不一致。
 */
final class AdminNavigation
{
    /** 菜单键 => lucide 图标名。 */
    private const ICONS = [
        'dashboard' => 'layout-dashboard',
        'analytics' => 'bar-chart-3',
        'tasks' => 'list-checks',
        'distribution' => 'share-2',
        'articles' => 'file-text',
        'materials' => 'library',
        'ai_config' => 'sparkles',
        'site_settings' => 'settings',
        'admin_users' => 'users',
    ];

    /**
     * 路由名 => 菜单键，用于子页面回推高亮项。
     *
     * @var array<string, string>
     */
    private const SUB_MAP = [
        'admin.analytics' => 'analytics',
        'admin.analytics.content' => 'analytics',
        'admin.analytics.traffic' => 'analytics',
        'admin.analytics.ai-visibility' => 'analytics',
        'admin.analytics.leads' => 'analytics',
        'admin.analytics.distribution' => 'analytics',
        'admin.system-updates.index' => 'dashboard',
        'admin.system-updates.check' => 'dashboard',
        'admin.system-updates.plan' => 'dashboard',
        'admin.system-updates.backup' => 'dashboard',
        'admin.tasks.create' => 'tasks',
        'admin.tasks.edit' => 'tasks',
        'admin.distribution.index' => 'distribution',
        'admin.distribution.create' => 'distribution',
        'admin.distribution.store' => 'distribution',
        'admin.distribution.edit' => 'distribution',
        'admin.distribution.update' => 'distribution',
        'admin.distribution.show' => 'distribution',
        'admin.distribution.jobs' => 'distribution',
        'admin.distribution.retry' => 'distribution',
        'admin.distribution.health' => 'distribution',
        'admin.distribution.pause' => 'distribution',
        'admin.distribution.activate' => 'distribution',
        'admin.distribution.rotate-secret' => 'distribution',
        'admin.articles.create' => 'articles',
        'admin.articles.edit' => 'articles',
        'admin.manual-publications.index' => 'articles',
        'admin.manual-publications.create' => 'articles',
        'admin.manual-publications.show' => 'articles',
        'admin.manual-publications.edit' => 'articles',
        'admin.manual-publications.settings.index' => 'articles',
        'admin.categories.index' => 'materials',
        'admin.categories.create' => 'materials',
        'admin.categories.edit' => 'materials',
        'admin.authors.index' => 'materials',
        'admin.authors.create' => 'materials',
        'admin.authors.edit' => 'materials',
        'admin.authors.detail' => 'materials',
        'admin.keyword-libraries.index' => 'materials',
        'admin.keyword-libraries.create' => 'materials',
        'admin.keyword-libraries.edit' => 'materials',
        'admin.keyword-libraries.detail' => 'materials',
        'admin.keyword-libraries.detail.update' => 'materials',
        'admin.keyword-libraries.keywords.store' => 'materials',
        'admin.keyword-libraries.keywords.delete' => 'materials',
        'admin.keyword-libraries.import' => 'materials',
        'admin.title-libraries.index' => 'materials',
        'admin.title-libraries.create' => 'materials',
        'admin.title-libraries.edit' => 'materials',
        'admin.title-libraries.detail' => 'materials',
        'admin.title-libraries.titles.store' => 'materials',
        'admin.title-libraries.titles.delete' => 'materials',
        'admin.title-libraries.import' => 'materials',
        'admin.title-libraries.ai-generate' => 'materials',
        'admin.title-libraries.ai-generate.submit' => 'materials',
        'admin.image-libraries.index' => 'materials',
        'admin.image-libraries.create' => 'materials',
        'admin.image-libraries.edit' => 'materials',
        'admin.image-libraries.detail' => 'materials',
        'admin.image-libraries.images.upload' => 'materials',
        'admin.image-libraries.images.delete' => 'materials',
        'admin.image-libraries.detail.update' => 'materials',
        'admin.knowledge-bases.index' => 'materials',
        'admin.knowledge-bases.create' => 'materials',
        'admin.knowledge-bases.edit' => 'materials',
        'admin.knowledge-bases.detail' => 'materials',
        'admin.knowledge-bases.upload' => 'materials',
        'admin.knowledge-bases.detail.update' => 'materials',
        'admin.url-import' => 'materials',
        'admin.ai-models.index' => 'ai_config',
        'admin.ai-source-providers.index' => 'ai_config',
        'admin.ai-prompts' => 'ai_config',
        'admin.site-settings.sensitive-words' => 'site_settings',
        'admin.site-settings.sensitive-words.store' => 'site_settings',
        'admin.site-settings.sensitive-words.delete' => 'site_settings',
        'admin.security-settings.index' => 'site_settings',
        'admin.security-settings.words.store' => 'site_settings',
        'admin.security-settings.words.delete' => 'site_settings',
        'admin.api-tokens.index' => 'admin_users',
        'admin.api-tokens.store' => 'admin_users',
        'admin.api-tokens.revoke' => 'admin_users',
        'admin.admin-activity-logs' => 'admin_users',
    ];

    /**
     * @return array<string, array{route:string,name:string,icon:string}>
     */
    public static function items(?Admin $admin): array
    {
        $isSuperAdmin = $admin !== null
            && method_exists($admin, 'canManageProtectedWorkflows')
            && $admin->canManageProtectedWorkflows();

        $menu = [
            'dashboard' => ['route' => 'admin.dashboard', 'name' => __('admin.nav.dashboard')],
            'analytics' => ['route' => 'admin.analytics', 'name' => __('admin.nav.analytics')],
            'tasks' => ['route' => 'admin.tasks.index', 'name' => __('admin.nav.tasks')],
            'distribution' => ['route' => 'admin.distribution.index', 'name' => __('admin.nav.distribution')],
            'articles' => ['route' => 'admin.articles.index', 'name' => __('admin.nav.articles')],
            'materials' => ['route' => 'admin.materials.index', 'name' => __('admin.nav.materials')],
            'ai_config' => ['route' => 'admin.ai.configurator', 'name' => __('admin.nav.ai_config')],
            'site_settings' => ['route' => 'admin.site-settings.index', 'name' => __('admin.nav.site_settings')],
        ];

        if (! $isSuperAdmin) {
            unset($menu['distribution']);
        } else {
            $menu['admin_users'] = ['route' => 'admin.admin-users.index', 'name' => __('admin.nav.admin_users')];
        }

        foreach ($menu as $key => $item) {
            $menu[$key]['icon'] = self::ICONS[$key] ?? 'circle';
        }

        return $menu;
    }

    /** 当前页面对应的菜单键；子页面按路由名回推到父级。 */
    public static function resolveActive(string $activeMenu, ?string $routeName): string
    {
        if ($activeMenu !== '') {
            return $activeMenu;
        }

        if ($routeName !== null && isset(self::SUB_MAP[$routeName])) {
            return self::SUB_MAP[$routeName];
        }

        return '';
    }
}
