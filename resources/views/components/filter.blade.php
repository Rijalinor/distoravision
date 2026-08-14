@php
    $periods = \App\Models\Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

    $principalFilterRequest = new \Illuminate\Http\Request(request()->except('principal_id'));

    $principalIds = \App\Models\Transaction::withFilters($principalFilterRequest)
        ->join('products', 'transactions.product_id', '=', 'products.id')
        ->select('products.principal_id')
        ->distinct()
        ->pluck('products.principal_id');

    $principals = \App\Models\Principal::whereIn('id', $principalIds)
        ->orderBy('name')
        ->get();
    
    $start_period = request('start_period', request('period', $periods->first()));
    $end_period = request('end_period', request('period', $periods->first()));
    $principal_id = request('principal_id', 'all');
@endphp

    <div class="filter-strip">
        <label>
            <span>Dari</span>
            <select name="start_period" class="period-select">
                @foreach($periods as $p)
                    <option value="{{ $p }}" {{ $start_period === $p ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('M Y') }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span>Sampai</span>
            <select name="end_period" class="period-select">
                @foreach($periods as $p)
                    <option value="{{ $p }}" {{ $end_period === $p ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('M Y') }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="filter-principal">
            <span>Principal</span>
            <select name="principal_id" class="period-select">
                <option value="all" {{ $principal_id === 'all' ? 'selected' : '' }}>Semua</option>
                @foreach($principals as $pr)
                    <option value="{{ $pr->id }}" {{ (string)$principal_id === (string)$pr->id ? 'selected' : '' }}>
                        {{ Str::limit(str_replace('PT. ', '', $pr->name), 22) }}
                    </option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="btn btn-primary btn-compact">Terapkan</button>
    </div>
