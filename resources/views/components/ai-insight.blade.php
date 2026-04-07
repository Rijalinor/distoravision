@if(isset($aiNarrative))
<div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid var(--accent-blue); background: linear-gradient(90deg, rgba(59,130,246,0.1) 0%, rgba(15,23,42,0) 50%);">
    <div style="display:flex; gap:1rem; align-items:flex-start; padding: 0.5rem;">
        <div style="font-size: 2rem; line-height:1;">💡</div>
        <div>
            <h3 style="margin-bottom:0.5rem; color:var(--text-color); font-size:1.1rem; display:flex; align-items:center; gap:0.5rem;">
                Distora AI Insight
                <span class="badge badge-blue" style="font-size:0.65rem;">S1-Level</span>
            </h3>
            <p style="color:var(--text-muted); font-size:1.05rem; line-height:1.6; margin:0;">
                {!! nl2br(e($aiNarrative)) !!}
            </p>
        </div>
    </div>
</div>
@endif
