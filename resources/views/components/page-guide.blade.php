@props([
    'title',
    'description',
    'steps' => [],
    'metrics' => [],
])

<details class="page-guide">
    <summary>
        <span>Panduan cepat</span>
        <strong>{{ $title }}</strong>
        <small>{{ $description }}</small>
    </summary>

    <div class="page-guide-body">
        @if(! empty($steps))
            <div class="page-guide-list">
                <div class="page-guide-list-title">Alur pakai</div>
                @foreach($steps as $step)
                    <div class="page-guide-item">
                        <span>{{ $loop->iteration }}</span>
                        <p>{{ $step }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if(! empty($metrics))
            <div class="page-guide-list">
                <div class="page-guide-list-title">Yang perlu dibaca</div>
                @foreach($metrics as $label => $note)
                    <div class="page-guide-metric">
                        <strong>{{ $label }}</strong>
                        <p>{{ $note }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</details>
