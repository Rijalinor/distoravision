<div class="tab-menu-container">
    <a href="{{ route('salesmen.index') }}" class="tab-link {{ request()->routeIs('salesmen.*') ? 'active-tab' : '' }}">Performa Salesman</a>
    <a href="{{ route('analytics.salesman-profitability') }}" class="tab-link {{ request()->routeIs('analytics.salesman-profitability') ? 'active-tab' : '' }}">Profitabilitas & Efisiensi</a>
    <a href="{{ route('analytics.target-tracker') }}" class="tab-link {{ request()->routeIs('analytics.target-tracker') ? 'active-tab' : '' }}">Target Tracker</a>
</div>
