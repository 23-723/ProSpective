<article class="alert-card decision-card" x-data="{ get comparison() { return decisionComparison(alert) } }">
    <div class="decision-card-heading">
        <span class="decision-icon" aria-hidden="true">
            @foreach(['sete' => 'trend', 'passing' => 'people', 'grades' => 'performance', 'improvement' => 'line-chart', 'rating' => 'star'] as $metric => $icon)
                <x-icon :name="$icon" x-show="alert.metric === '{{ $metric }}'"/>
            @endforeach
        </span>
        <h3 x-text="alert.kind === 'metric' ? alert.kpi : alert.title"></h3>
        <span class="alert-scope" x-text="alert.scope"></span>
    </div>
    <div class="alert-metric">
        <strong class="alert-value" x-text="comparison.display"></strong>
        <div class="decision-targets" x-show="comparison.target">
            <span x-text="comparison.target"></span>
            <span class="decision-gap" :title="alert.metric === 'rating' ? 'Rating points short of target' : 'Percentage points short of target'">
                <span>Gap </span><strong x-text="comparison.gap"></strong>
            </span>
        </div>
        <p class="alert-threshold" x-show="!comparison.target" x-text="alert.threshold"></p>
    </div>
    <p class="alert-explanation">
        <strong :class="statusClass(alert.severity)" x-text="alert.severity"></strong>
        <span aria-hidden="true">·</span>
        <span x-text="alert.explanation"></span>
    </p>
    <div class="alert-action">
        <strong>Recommended action</strong>
        <p x-text="alert.action"></p>
    </div>
    <button type="button" class="text-button" @click="openAlert(alert, $el)" :aria-label="'View full analysis for ' + alert.title + ', ' + alert.scope">
        View full analysis <x-icon name="chevron"/>
    </button>
</article>
