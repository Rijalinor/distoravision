@php
    $periods = \App\Models\Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
    $principals = \App\Models\Principal::orderBy('name')->get();
    
    $start_period = request('start_period', request('period', $periods->first()));
    $end_period = request('end_period', request('period', $periods->first()));
    $principal_id = request('principal_id', 'all');
@endphp

    <!-- Start Period -->
    <select name="start_period" class="period-select">
        @foreach($periods as $p)
            <option value="{{ $p }}" {{ $start_period === $p ? 'selected' : '' }}>
                {{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}
            </option>
        @endforeach
    </select>
    
    <span style="color:var(--text-muted);font-size:0.8rem;">s/d</span>

    <!-- End Period -->
    <select name="end_period" class="period-select">
        @foreach($periods as $p)
            <option value="{{ $p }}" {{ $end_period === $p ? 'selected' : '' }}>
                {{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}
            </option>
        @endforeach
    </select>

    <!-- Principal Filter -->
    <select name="principal_id" class="period-select" style="max-width:200px;">
        <option value="all" {{ $principal_id === 'all' ? 'selected' : '' }}>Semua Principal</option>
        @foreach($principals as $pr)
            <option value="{{ $pr->id }}" {{ (string)$principal_id === (string)$pr->id ? 'selected' : '' }}>
                {{ Str::limit(str_replace('PT. ', '', $pr->name), 20) }}
            </option>
        @endforeach
    </select>

    <button type="submit" class="btn btn-primary" style="padding:0.4rem 0.75rem;font-size:0.75rem;">Filter</button>
