<?php

namespace App\Providers;

use App\Models\Admin;
use App\Services\Admin\AdminUpdateMetadataService;
use App\Services\Admin\AdminWelcomeModalService;
use App\Services\GeoFlow\ArticleGeoFlowService;
use App\Services\GeoFlow\HorizonMetricsAdapter;
use App\Services\GeoFlow\JobQueueService;
use App\Services\GeoFlow\TaskLifecycleService;
use App\Services\GeoFlow\TaskMonitoringQueryService;
use App\Support\GeoFlow\OutboundHttpProxy;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JobQueueService::class);
        $this->app->singleton(HorizonMetricsAdapter::class);
        $this->app->singleton(TaskMonitoringQueryService::class);
        $this->app->singleton(TaskLifecycleService::class);
        $this->app->singleton(ArticleGeoFlowService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-sensitive', function (Request $request): array {
            $adminId = (int) ($request->user('admin')?->getAuthIdentifier() ?? 0);

            return [
                Limit::perMinute(5)->by('admin-sensitive:admin:'.$adminId),
                Limit::perMinute(5)->by('admin-sensitive:admin-ip:'.$adminId.'|'.$request->ip()),
            ];
        });

        Http::globalMiddleware(OutboundHttpProxy::middleware());

        View::composer(['site.layout', 'theme.*.layout'], SiteLayoutComposer::class);

        View::composer('admin.layouts.app', function ($view): void {
            $admin = auth('admin')->user();
            $view->with(
                'adminWelcomeModalPayload',
                $admin instanceof Admin ? app(AdminWelcomeModalService::class)->buildModalPayload($admin) : null
            );
            $view->with(
                'adminUpdateNotificationPayload',
                $admin instanceof Admin ? app(AdminUpdateMetadataService::class)->buildNotificationPayload() : null
            );
        });
    }
}
