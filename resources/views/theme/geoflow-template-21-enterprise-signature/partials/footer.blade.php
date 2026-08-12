<footer class="ent-footer">
    <div class="ent-shell">
        <div class="ent-footer__bottom">
            <div>
                {{ $footerCopyright !== '' ? $footerCopyright : '© '.date('Y').' '.$siteName.'. All rights reserved.' }}
                @include('site.partials.footer-filing')
            </div>
        </div>
    </div>
</footer>
