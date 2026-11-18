@props(['metric'])

<div class="chart-legend">
    <span><i class="legend-square" aria-hidden="true"></i><span x-text="'Meets target ≥ ' + metricDefinition('{{ $metric }}').target + '%'"></span></span>
    <span><i class="legend-square below-target" aria-hidden="true"></i><span x-text="'Below target ' + metricDefinition('{{ $metric }}').critical + '% to <' + metricDefinition('{{ $metric }}').target + '%'"></span></span>
    <span><i class="legend-square danger" aria-hidden="true"></i><span x-text="'Critical below ' + metricDefinition('{{ $metric }}').critical + '%'"></span></span>
</div>
