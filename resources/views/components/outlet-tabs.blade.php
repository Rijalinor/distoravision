<div class="tab-menu-container">
    <a href="{{ route('outlets.index') }}" class="tab-link {{ request()->routeIs('outlets.*') ? 'active-tab' : '' }}">Performa Outlet</a>
    <a href="{{ route('analytics.outlet-trajectory') }}" class="tab-link {{ request()->routeIs('analytics.outlet-trajectory') ? 'active-tab' : '' }}">Trend & Trajectory</a>
    <a href="{{ route('analytics.cohort') }}" class="tab-link {{ request()->routeIs('analytics.cohort') ? 'active-tab' : '' }}">Cohort Analysis</a>
    <a href="{{ route('analytics.restock-predictor') }}" class="tab-link {{ request()->routeIs('analytics.restock-predictor') ? 'active-tab' : '' }}">Restock Predictor</a>
</div>
