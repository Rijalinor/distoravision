@props([
    'title',
    'description',
    'steps' => [],
    'metrics' => [],
    'details' => [],
])

<details class="page-guide">
    <summary>
        <span>?</span>
        <strong>Bantuan</strong>
    </summary>

    <div class="page-guide-body">
        <div class="page-guide-main">
            <span class="page-guide-eyebrow">Panduan cepat</span>
            <h2>{{ $title }}</h2>
            <p>{{ $description }}</p>
        </div>

        @if(! empty($details))
            <div class="page-guide-list">
                <div class="page-guide-list-title">Ringkasan lengkap</div>
                @foreach($details as $detail)
                    <div class="page-guide-metric">
                        <strong>{{ $detail['title'] }}</strong>
                        <p>{{ $detail['body'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

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
