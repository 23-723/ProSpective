@props(['metric', 'index'])

<article class="kpi-card kpi-{{ $metric['key'] }}" x-data="{ get metric() { return dashboard.kpis[{{ $index }}] } }">
    <button type="button" class="kpi-open" @click="openMetric(metric.key, $el)" title="{{ $metric['tooltip'] }}" :title="metric.tooltip">
        <span class="kpi-icon"><x-icon :name="$metric['icon'] === 'trend' ? 'performance' : $metric['icon']"/></span>
        <span class="kpi-copy">
            <span class="kpi-label" x-text="metric.title">{{ $metric['title'] }}</span>
            <span class="kpi-number">
                <span x-text="metric.display">{{ $metric['display'] }}</span>
                <small @if($metric['value'] === null) x-cloak @endif x-show="metric.value !== null" x-text="metric.unit">{{ $metric['unit'] }}</small>
            </span>
            <span class="status-badge" :class="statusClass(metric.status)">
                <span class="kpi-status-mark" aria-hidden="true"></span><span x-text="metric.status">{{ $metric['status'] }}</span>
            </span>
            <span class="kpi-target" x-text="metric.target_label">{{ $metric['target_label'] }}</span>
        </span>
    </button>
</article>
