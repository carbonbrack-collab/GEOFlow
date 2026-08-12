@php
    $currentAdmin = auth('admin')->user();
    $adminBrandName = $adminBrandName ?? \App\Support\AdminWeb::siteName();
    $menu = \App\Support\Admin\AdminNavigation::items($currentAdmin);
    $resolvedActive = \App\Support\Admin\AdminNavigation::resolveActive(
        $activeMenu ?? '',
        request()->route()?->getName(),
    );
    $isSuperAdmin = $currentAdmin
        && method_exists($currentAdmin, 'canManageProtectedWorkflows')
        && $currentAdmin->canManageProtectedWorkflows();
@endphp

<div id="admin-sidebar-backdrop"
     class="fixed inset-0 z-30 hidden bg-gray-900/40 lg:hidden"
     onclick="toggleAdminSidebar()"></div>

<aside id="admin-sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-5">
        <a href="{{ route('admin.dashboard') }}" class="truncate text-lg font-semibold text-gray-900">{{ $adminBrandName }}</a>
    </div>

    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
        @foreach ($menu as $key => $item)
            <a href="{{ route($item['route']) }}"
               @if($resolvedActive === $key) aria-current="page" @endif
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors
                      @if($resolvedActive === $key) bg-blue-50 font-medium text-blue-600 @else text-gray-600 hover:bg-gray-50 hover:text-gray-900 @endif">
                <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4 shrink-0"></i>
                <span class="truncate">{{ $item['name'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-gray-100 px-3 py-3">
        @if ($isSuperAdmin)
            <a href="{{ route('admin.admin-activity-logs') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 hover:text-gray-900">
                <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0"></i>
                <span class="truncate">{{ __('admin.nav.activity_logs') }}</span>
            </a>
            <a href="{{ route('admin.api-tokens.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 hover:text-gray-900">
                <i data-lucide="key-round" class="h-4 w-4 shrink-0"></i>
                <span class="truncate">{{ __('admin.nav.api_tokens') }}</span>
            </a>
        @endif
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                <i data-lucide="log-out" class="h-4 w-4 shrink-0"></i>
                <span class="truncate">{{ __('admin.button.logout') }}</span>
            </button>
        </form>
    </div>
</aside>

<script>
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        if (!sidebar || !backdrop) return;
        const open = sidebar.classList.toggle('-translate-x-full');
        backdrop.classList.toggle('hidden', open);
    }
</script>
