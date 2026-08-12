@extends('site.layout')

@push('head')
    @php
        $schemaAtContext = chr(64).'context';
        $schemaAtType = chr(64).'type';
        $aboutSchema = [
            $schemaAtContext => 'https://schema.org',
            $schemaAtType => 'AboutPage',
            'name' => '关于',
            'description' => $pageDescription,
            'url' => $canonicalUrl ?? route('site.about'),
        ];
    @endphp
    <x-json-ld :data="$aboutSchema" />
@endpush

@section('content')
    <div class="site-container article-page px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <article class="article-shell article-detail-shell">
            <div class="article-detail-pad">
                <header class="article-rail mb-10">
                    <p class="text-sm font-medium text-blue-600 mb-4">开源项目</p>
                    <h1 class="article-hero-title font-semibold text-gray-900 mb-4 leading-tight">关于</h1>
                    <p class="article-kicker text-gray-600 max-w-3xl">
                        {{ $siteDescription !== '' ? $siteDescription : '在这里补充站点简介。' }}
                    </p>
                </header>

                <div class="article-prose article-rail max-w-none">
                    @include('site.partials.about-content')
                </div>
            </div>
        </article>
    </div>
@endsection
