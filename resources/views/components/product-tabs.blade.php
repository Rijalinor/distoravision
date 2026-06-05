<div class="tab-menu-container">
    <a href="{{ route('products.index') }}" class="tab-link {{ request()->routeIs('products.*') ? 'active-tab' : '' }}">Performa Produk</a>
    <a href="{{ route('analytics.product-trajectory') }}" class="tab-link {{ request()->routeIs('analytics.product-trajectory') ? 'active-tab' : '' }}">Product Trajectory</a>
    <a href="{{ route('analytics.pareto') }}" class="tab-link {{ request()->routeIs('analytics.pareto') ? 'active-tab' : '' }}">Analisa Pareto</a>
    <a href="{{ route('analytics.cross-selling') }}" class="tab-link {{ request()->routeIs('analytics.cross-selling') ? 'active-tab' : '' }}">Peluang Keranjang</a>
</div>
