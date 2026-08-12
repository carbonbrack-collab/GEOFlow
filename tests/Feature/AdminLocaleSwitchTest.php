<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminWeb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 后台默认只开放简体中文，多语言能力仍保留在配置里，
     * 这里显式打开全部语言以继续验证切换逻辑。
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['geoflow.admin_locales' => 'zh_CN,en,ja,es,ru,pt_BR']);
    }

    public function test_admin_supported_locales_include_new_languages(): void
    {
        $this->assertSame([
            'zh_CN',
            'en',
            'ja',
            'es',
            'ru',
            'pt_BR',
        ], array_keys(AdminWeb::supportedLocales()));
    }

    public function test_admin_locale_switch_accepts_new_languages(): void
    {
        foreach (['ja', 'es', 'ru', 'pt_BR'] as $locale) {
            $this->from(route('admin.login'))
                ->get(route('admin.locale.switch', ['locale' => $locale]))
                ->assertRedirect(route('admin.login'))
                ->assertSessionHas('locale', $locale);
        }
    }

    public function test_admin_dashboard_renders_new_locale_core_copy(): void
    {
        $admin = Admin::query()->create([
            'username' => 'locale_admin',
            'password' => 'secret-123',
            'email' => 'locale-admin@example.com',
            'display_name' => 'Locale Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $expectations = [
            'ja' => 'ダッシュボード',
            'es' => 'Panel',
            'ru' => 'Панель',
            'pt_BR' => 'Painel',
        ];

        foreach ($expectations as $locale => $heading) {
            $this->actingAs($admin, 'admin')
                ->withSession(['locale' => $locale])
                ->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee($heading)
                ->assertDontSee('dashboard.heading');
        }
    }
}
