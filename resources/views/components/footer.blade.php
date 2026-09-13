
<footer class="site-footer">

    <div class="footer-inner">

        <div class="footer-main">
            <div class="footer-about">
                <a class="footer-brand" href="{{ route('home') }}">{{ $storeSettings->store_name ?? 'Mobile Hub' }}</a>
                <p id="footerText">Browse online. Visit us in store or get in touch to buy.</p>
            </div>
            <nav class="footer-nav" aria-label="Footer navigation">
                <a href="{{ route('search') }}">Browse Products</a>
                <a href="{{ route('perfumes.index') }}">Perfumes</a>
            </nav>
        </div>
        <div class="footer-meta">
            <span>&copy; {{ date('Y') }} {{ $storeSettings->store_name ?? 'Mobile Hub' }}</span>
            @if (session('admin_authenticated'))
                <a href="{{ route('admin.products.index') }}" class="staff-link">Admin Dashboard <span aria-hidden="true">&nearr;</span></a>
            @else
                <a href="{{ route('admin.login') }}" class="staff-link">Staff Login <span aria-hidden="true">&nearr;</span></a>
            @endif
        </div>

    </div>

</footer>
