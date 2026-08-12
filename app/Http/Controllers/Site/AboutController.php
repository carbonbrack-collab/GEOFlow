<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Site\SiteSettingsBag;
use App\Support\Site\SiteThemeViewResolver;
use Illuminate\View\View;

/**
 * 站点「关于」页。
 */
class AboutController extends Controller
{
    public function index(): View
    {
        $map = SiteSettingsBag::all();
        $siteTitle = (string) ($map['site_name'] ?? config('geoflow.site_name', config('app.name')));
        $siteDescription = (string) ($map['site_description'] ?? config('geoflow.site_description', ''));
        $siteKeywords = (string) ($map['site_keywords'] ?? config('geoflow.site_keywords', ''));
        $pageDescription = $siteDescription !== '' ? $siteDescription : ($siteTitle.' 的关于页面。');

        return SiteThemeViewResolver::first('about', [
            'activeNav' => 'about',
            'siteTitle' => $siteTitle,
            'siteDescription' => $siteDescription,
            'siteKeywords' => $siteKeywords,
            'pageTitle' => '关于'.($siteTitle !== '' ? ' '.$siteTitle : ''),
            'pageDescription' => $pageDescription,
            'pageKeywords' => $siteKeywords,
            'pageOgType' => 'website',
            'canonicalUrl' => route('site.about'),
        ]);
    }
}
