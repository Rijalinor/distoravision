<div class="tab-menu-container" style="margin-bottom: 1.5rem;">
    <a href="{{ route('sales-per.stock') }}" class="tab-link {{ request()->routeIs('sales-per.stock') ? 'active-tab' : '' }}">Analisis Stok (Sales Per)</a>
    <a href="{{ route('inventory.forecast') }}" class="tab-link {{ request()->routeIs('inventory.forecast') ? 'active-tab' : '' }}">Restock 1 Bulan</a>
    <a href="{{ route('inventory.forecast.multi-period') }}" class="tab-link {{ request()->routeIs('inventory.forecast.multi-period') ? 'active-tab' : '' }}">Prediksi 6 Bulan (Expiry Risk)</a>
</div>
