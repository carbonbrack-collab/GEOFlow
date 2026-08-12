@php
    $adminBrandName = \App\Support\AdminWeb::siteName();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (is_array($anonymousUsageTelemetryPayload ?? null))
        <meta name="geoflow-telemetry-endpoint" content="{{ $anonymousUsageTelemetryPayload['endpoint'] }}">
        <meta name="geoflow-telemetry-event" content="{{ $anonymousUsageTelemetryPayload['event'] }}">
        <meta name="geoflow-telemetry-instance" content="{{ $anonymousUsageTelemetryPayload['instance_id'] }}">
        <meta name="geoflow-telemetry-user" content="{{ $anonymousUsageTelemetryPayload['user_hash'] }}">
        <meta name="geoflow-telemetry-version" content="{{ $anonymousUsageTelemetryPayload['version'] }}">
        <meta name="geoflow-telemetry-interval" content="{{ $anonymousUsageTelemetryPayload['interval_seconds'] }}">
    @endif
    <title>@isset($pageTitle){{ $pageTitle }} — @endisset{{ $adminBrandName }}</title>
    <script src="{{ asset('js/tailwindcss.play-cdn.js') }}"></script>
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    @stack('styles')
</head>
<body class="bg-gray-50">
@include('admin.partials.sidebar', [
    'adminBrandName' => $adminBrandName,
    'activeMenu' => $activeMenu ?? '',
])
<div class="lg:pl-60">
@include('admin.partials.header', [
    'adminBrandName' => $adminBrandName,
    'adminSiteName' => $adminSiteName ?? $adminBrandName,
    'pageTitle' => $pageTitle ?? '',
    'activeMenu' => $activeMenu ?? '',
])
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if (session('message'))
            <div class="admin-flash-alert mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-flash-alert mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>
@include('admin.partials.footer')
</div>
{{-- 项目说明弹窗已停用：属于 GEOFlow 品牌文案，且内含外链。 --}}
{{-- 匿名统计信标已移除：本系统不向任何第三方发送数据。 --}}
@vite('resources/js/app.js')
@stack('scripts')
</body>
</html>
