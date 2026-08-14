@if(isset($aiNarrative))
<div class="card analysis-note">
    <div class="analysis-note-inner">
        <div class="analysis-note-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17h4.5M10 21h4m-5.5-7.5A6 6 0 1115.5 13.5c-.8.5-1.1 1.2-1.2 2h-4.6c-.1-.8-.4-1.5-1.2-2z"></path>
            </svg>
        </div>
        <div>
            <h3>
                Ringkasan Otomatis
                <span class="badge badge-blue">berdasarkan data aktif</span>
            </h3>
            <p>
                {!! nl2br(e($aiNarrative)) !!}
            </p>
        </div>
    </div>
</div>
@endif
