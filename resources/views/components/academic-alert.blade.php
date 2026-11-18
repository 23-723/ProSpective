<article class="alert-card">
    <div class="alert-top">
        <span class="alert-severity" :class="statusClass(alert.severity)"><span class="status-dot" aria-hidden="true"></span><span x-text="alert.severity"></span></span>
        <span class="alert-scope" x-text="alert.scope"></span>
    </div>
    <h3 x-text="alert.title"></h3>
    <div class="alert-metric">
        <strong class="alert-value" x-text="alert.display"></strong>
        <p class="alert-threshold" x-text="alert.threshold"></p>
    </div>
    <p class="alert-period" x-text="alert.period"></p>
    <p class="alert-explanation" x-text="alert.explanation"></p>
    <div class="alert-action">
        <strong>Recommended action</strong>
        <p x-text="alert.action"></p>
    </div>
    <button type="button" class="text-button" @click="openAlert(alert, $el)" :aria-label="'View evidence for ' + alert.title + ', ' + alert.scope">View evidence <x-icon name="arrow"/></button>
</article>
