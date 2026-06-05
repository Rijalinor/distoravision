<div class="tab-menu-container">
    <a href="{{ route('analytics.margin') }}" class="tab-link {{ request()->routeIs('analytics.margin') ? 'active-tab' : '' }}">Profitabilitas (Margin)</a>
    <a href="{{ route('analytics.promo-uplift') }}" class="tab-link {{ request()->routeIs('analytics.promo-uplift') ? 'active-tab' : '' }}">Evaluasi Efek Promo</a>
</div>
