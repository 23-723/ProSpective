<!DOCTYPE html>
<html lang="en">
<head>
    @php($systemDescription = 'An Integrated Academic Performance and Teaching Effectiveness Analytics and Decision Support System for Program Chairs')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ config('app.name') }}. {{ $systemDescription }}. Academic case-study prototype using demonstration data.">
    <meta name="theme-color" content="#a5191d">
    <title>Academic Analytics Overview · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('prospective-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="academicDashboard" @keydown.escape.window="closeDetail()">
<a href="#main-content" class="skip-link">Skip to dashboard</a>
<header class="top-header">
    <div class="header-inner">
        <a href="{{ route('dashboard') }}" class="brand" aria-label="{{ config('app.name') }} dashboard home">
            <img class="brand-mark" src="{{ asset('prospective-logo.png') }}" width="1314" height="1197" alt="">
            <span><strong>{{ config('app.name') }}</strong><small>Academic Analytics Dashboard</small></span>
        </a>
        @php($manilaNow = now('Asia/Manila'))
        <time class="header-clock" x-data="manilaClock" datetime="{{ $manilaNow->toIso8601String() }}" :datetime="isoDateTime" title="Philippine time">
            <x-icon name="clock"/>
            <span class="header-clock-date" x-text="dateLabel">{{ $manilaNow->format('M j, Y') }}</span>
            <span class="header-clock-time" x-text="timeLabel">{{ $manilaNow->format('h:i:s A') }}</span>
        </time>
        <div class="header-actions">
            <span class="academic-indicator"><span class="status-dot"></span> A.Y. <span x-text="prettyYear(dashboard.filters.academic_year)">{{ str_replace('-', '–', $dashboard['filters']['academic_year']) }}</span></span>
            <button type="button" id="alerts-toggle" class="notification-button" popovertarget="academic-alerts" aria-label="Academic alerts" :aria-label="'Academic alerts (' + dashboard.alerts.length + ' active in the applied scope)'">
                <x-icon name="bell"/>
                <span class="notification-count" x-show="dashboard.alerts.length" x-text="dashboard.alerts.length" aria-hidden="true" x-cloak></span>
            </button>
            <div class="profile">
                <button type="button" class="profile-button" popovertarget="program-profile" aria-label="Program Chair menu">
                    <span class="avatar" aria-hidden="true">PC</span>
                    <span class="profile-copy"><strong>Program Chair</strong><small>BS Information Technology</small></span>
                    <x-icon name="chevron-down"/>
                </button>
            </div>
        </div>
    </div>
    <section id="academic-alerts" class="header-popover alerts-popover" popover aria-labelledby="alerts-title">
        <div class="section-heading">
            <div><h2 id="alerts-title">Academic Alerts</h2><p x-text="dashboard.alerts.length + ' active · ' + periodLabel()"></p></div>
            <button type="button" class="icon-button" popovertarget="academic-alerts" popovertargetaction="hide" aria-label="Close alerts"><x-icon name="close"/></button>
        </div>
        <div class="alerts-grid" x-show="dashboard.alerts.length">
            <template x-for="alert in visibleAlerts" :key="alert.id"><x-academic-alert/></template>
        </div>
        <p class="no-alerts" x-show="!dashboard.alerts.length">No active alerts for the applied scope.</p>
        <button type="button" class="text-button more-alerts" x-show="dashboard.alerts.length > 3" @click="showAllAlerts = !showAllAlerts" x-text="showAllAlerts ? 'Show fewer alerts' : 'View all ' + dashboard.alerts.length + ' alerts'"></button>
    </section>
    <div id="program-profile" class="header-popover profile-popover" popover>
        <strong>BS Information Technology</strong>
        <p>College of Informatics and Computing Sciences</p>
        <a href="#performance-monitoring" @click="$el.closest('[popover]').hidePopover()">Performance monitoring <x-icon name="arrow"/></a>
    </div>
</header>
<main id="main-content" :aria-busy="loading">
    <section class="dashboard-hero" aria-labelledby="dashboard-title">
        <img class="hero-campus" src="{{ asset('campus-banner-sharp.jpg') }}" width="2061" height="763" alt="" fetchpriority="high">
        <div class="hero-inner">
            <p class="hero-kicker">Program Performance &amp; Insights</p>
            <div class="hero-heading">
                <h1 id="dashboard-title">Academic Analytics Overview</h1>
                <p>{{ $systemDescription }}</p>
            </div>
            <ul class="hero-context" aria-label="Institution and program">
                <li>Batangas State University – ARASOF-Nasugbu</li>
                <li>CICS</li>
                <li>BSIT</li>
            </ul>
        </div>
    </section>

    <div class="dashboard-container">
    <div class="error-banner" x-show="error" x-cloak role="alert"><x-icon name="alert"/><span x-text="error"></span><button @click="refresh()">Retry</button></div>
    <div class="loading-line" x-show="loading" x-cloak role="status">Updating academic insights…</div>
    <noscript><div class="error-banner">Enable JavaScript to use interactive charts, filters, and details. No university connection is required.</div></noscript>

    <section class="card filters-card" aria-labelledby="filter-title">
        <form @submit.prevent="applyFilters()">
            <div class="filter-heading">
                <div class="filter-intro">
                    <span class="filter-icon"><x-icon name="filter"/></span>
                    <div>
                        <h2 id="filter-title">Academic Filters</h2>
                        <p class="applied-scope" aria-live="polite"><strong>Applied scope</strong> <span x-text="periodLabel()"></span> <span aria-hidden="true">·</span> <span x-text="scopeLabel()"></span></p>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="button filter-reset" @click="resetAll()" :disabled="loading"><x-icon name="reset"/> Reset All</button>
                    <button type="submit" class="button button-primary filter-apply" :disabled="loading"><x-icon name="filter"/> Apply Filters</button>
                </div>
            </div>
            <div class="filters-grid">
                <x-academic-filter name="academic_year" label="Academic Year"/>
                <x-academic-filter name="semester" label="Semester"/>
                <x-academic-filter name="year_level" label="Year Level" placeholder="All Year Levels"/>
                <x-academic-filter name="section" label="Section" placeholder="All Sections"/>
                <x-academic-filter name="subject" label="Subject" placeholder="All Subjects"/>
                <x-academic-filter name="faculty" label="Faculty" placeholder="All Faculty"/>
            </div>
            <p class="pending-hint" x-show="hasDraftChanges" x-cloak role="status">Apply Filters to update the dashboard.</p>
        </form>
    </section>

    <section class="kpi-section" aria-label="Key performance indicators">
        <div class="kpi-grid">
            @foreach($dashboard['kpis'] as $index => $metric)
                <x-kpi-card :metric="$metric" :index="$index"/>
            @endforeach
        </div>
    </section>

    <section class="decision-section" aria-labelledby="decision-title">
        <div class="section-heading">
            <div>
                <h2 id="decision-title">Decision Support and Insights</h2>
                <p><span x-text="dashboard.alerts.length + ' active alerts'"></span> · <span x-text="periodLabel()"></span></p>
            </div>
        </div>
        <div class="decision-alerts" x-cloak>
            <template x-for="alert in decisionAlerts.slice(0, 3)" :key="alert.id"><x-decision-alert/></template>
        </div>
        <details class="decision-more" x-show="dashboard.alerts.length > 3" x-cloak>
            <summary class="text-button">
                <x-icon name="chevron"/>
                <span x-text="'Other alerts (' + (dashboard.alerts.length - 3) + ')'"></span>
            </summary>
            <div class="decision-alerts">
                <template x-for="alert in decisionAlerts.slice(3)" :key="alert.id"><x-decision-alert/></template>
            </div>
        </details>
        <p class="card decision-empty" x-show="!dashboard.alerts.length" x-cloak x-text="dashboard.summary.classes ? 'No active alerts for the applied scope.' : 'No class records are available for this selection.'"></p>
    </section>

    <section class="analytics-section" aria-labelledby="analytics-title">
        <div class="analytics-heading">
            <h2 id="analytics-title">Explore the Bigger Picture</h2>
            <p>Follow evaluation trends and discover patterns across year levels.</p>
        </div>
        <div class="charts-grid">
            <article id="sete-rating-trends" class="card chart-card" aria-labelledby="trend-heading" tabindex="-1">
                <div class="chart-heading">
                    <span class="chart-icon"><x-icon name="line-chart"/></span>
                    <div class="chart-heading-copy">
                        <h3 id="trend-heading">SETE Rating Trends</h3>
                        <p>Response-weighted ratings for the applied scope, compared across semesters.</p>
                    </div>
                    <span class="chart-type-label">{{ count($dashboard['charts']['trend']) }} semesters</span>
                </div>
                <div class="chart-legend">
                    <span><i class="legend-line" aria-hidden="true"></i> Weighted average rating</span>
                    <span><i class="legend-point" aria-hidden="true"></i> Selected period</span>
                </div>
                <div class="chart-plot">
                    <div class="chart-canvas">
                        <canvas id="trend-chart" role="img" aria-label="Response-weighted SETE ratings on a 1 to 5 scale. Use the period buttons below to open semester details."></canvas>
                        <div class="chart-empty" x-show="!dashboard.charts.trend.some(period => period.rating.value !== null)" x-cloak>
                            <strong>No reportable rating data.</strong>
                            <span x-text="'At least ' + dashboard.metadata.minimum_responses + ' valid responses per class are required.'"></span>
                        </div>
                    </div>
                    <div class="chart-axis-buttons trend-period-buttons" role="group" aria-label="Explore semester comparisons">
                        <template x-for="period in dashboard.charts.trend" :key="period.label">
                            <button type="button" @click="openPeriod(period, $el)" :aria-current="period.selected ? 'date' : null" :aria-label="'View ' + period.label + ', ' + formatMetric(period.rating.value, 'rating') + ', ' + period.rating.responses + ' reportable responses'">
                                <span x-text="period.semester === '1' ? '1st' : '2nd'"></span>
                                <span class="chart-period-year" x-text="prettyYear(period.academic_year)"></span>
                                <span class="chart-period-year-compact" x-text="period.academic_year.split('-').map(year => year.slice(-2)).join('–')"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </article>

            <article id="student-passing-rates" class="card chart-card" aria-labelledby="passing-heading" tabindex="-1">
                <div class="chart-heading">
                    <span class="chart-icon"><x-icon name="people"/></span>
                    <div class="chart-heading-copy">
                        <h3 id="passing-heading">Student Passing Rate by Year Level</h3>
                        <p>Passing records as a share of records with final grades.</p>
                    </div>
                </div>
                <x-metric-legend metric="passing"/>
                <div class="chart-plot">
                    <div class="chart-canvas">
                        <canvas id="passing-chart" role="img" aria-label="Passing rate by year level. Use the year-level buttons below to filter."></canvas>
                        <div class="chart-empty" x-show="!dashboard.charts.years.some(year => year.passing_rate !== null)" x-cloak>No graded records for this selection.</div>
                    </div>
                    <div class="chart-axis-buttons chart-filter-buttons" role="group" aria-label="Filter by year level">
                        <template x-for="year in dashboard.charts.years" :key="year.value">
                            <button type="button" @click="selectYear(year.value)" :aria-pressed="dashboard.filters.year_level === year.value" :disabled="loading || year.classes === 0" :aria-label="year.label + ', passing rate ' + percent(year.passing_rate)" x-text="year.label"></button>
                        </template>
                    </div>
                </div>
            </article>

            <article id="sete-completion-rates" class="card chart-card completion-card" aria-labelledby="completion-heading" tabindex="-1">
                <div class="completion-layout">
                    <div class="completion-chart">
                        <div class="chart-heading">
                            <span class="chart-icon"><x-icon name="layers"/></span>
                            <div class="chart-heading-copy">
                                <h3 id="completion-heading">SETE Completion by Year Level</h3>
                                <p>Required student evaluations successfully submitted.</p>
                            </div>
                        </div>
                        <x-metric-legend metric="sete"/>
                        <div class="chart-canvas completion-canvas">
                            <canvas id="completion-chart" role="img" :aria-label="'SETE completion by year level. ' + dashboard.charts.years.map(year => year.label + ' ' + percent(year.sete_rate)).join(', ') + '. Use the year-level buttons in Student Passing Rate or the academic filters to change the selection.'"></canvas>
                            <div class="chart-empty" x-show="!dashboard.charts.years.some(year => year.sete_rate !== null)" x-cloak>No required evaluations for this selection.</div>
                        </div>
                    </div>
                    <div class="completion-insight">
                        <span class="chart-icon"><x-icon name="people"/></span>
                        <div class="insight-copy">
                            <h4 class="insight-eyebrow">Participation Snapshot</h4>
                            <p class="insight-total">
                                <strong x-text="dashboard.summary.submitted.toLocaleString()">{{ number_format($dashboard['summary']['submitted']) }}</strong>
                                <span> / <span x-text="dashboard.summary.required.toLocaleString()">{{ number_format($dashboard['summary']['required']) }}</span></span>
                            </p>
                            <p>Student-subject evaluations submitted</p>
                        </div>
                        <div class="insight-progress">
                            <div class="insight-track" x-show="dashboard.summary.sete_rate !== null" aria-hidden="true"><span :style="{ width: (dashboard.summary.sete_rate ?? 0) + '%' }"></span></div>
                            <p class="target-note"><span x-text="percent(dashboard.summary.sete_rate)"></span> · <span x-text="metricDefinition('sete').target_label"></span></p>
                            <button type="button" class="text-button" @click="openMetric('sete', $el)">Explore participation <x-icon name="arrow"/></button>
                        </div>
                    </div>
                </div>
                <p class="sete-interpretation">SETE results should be interpreted alongside other academic and professional performance indicators and should not be used as the sole measure of teaching effectiveness.</p>
            </article>
        </div>
    </section>

    <section id="performance-monitoring" class="monitoring-section" aria-labelledby="monitoring-title">
        <div class="monitoring-heading">
            <div class="monitoring-heading-copy">
                <h2 id="monitoring-title">Academic Performance Monitoring</h2>
                <p>Class records, recorded grade submissions, and calculated rates. Student counts are student-subject enrollments.</p>
            </div>
            <button type="button" class="button button-outline monitoring-export" @click="exportCsv()" :disabled="tableBusy">
                <x-icon name="download"/> Export CSV
            </button>
        </div>

        <div class="card monitoring-table-card" :aria-busy="tableBusy">
            <div class="monitoring-toolbar">
                <label class="search-field">
                    <x-icon name="search"/>
                    <input type="search" x-model="search" @input="queueTableSearch()" :disabled="loading" placeholder="Search section, subject, or faculty…" aria-label="Search monitoring records" aria-describedby="monitoring-search-scope">
                </label>
                <span id="monitoring-search-scope" class="sr-only">Search narrows the monitoring table and CSV export only.</span>
                <p class="monitoring-record-total" title="Matching class records" x-text="dashboard.table.total + (dashboard.table.total === 1 ? ' record' : ' records')">
                    {{ $dashboard['table']['total'] }} {{ $dashboard['table']['total'] === 1 ? 'record' : 'records' }}
                </p>
            </div>
            <div class="monitoring-table-error" x-show="tableError" x-cloak role="alert">
                <span x-text="tableError"></span>
                <button type="button" class="text-button" @click="refreshTable()">Retry</button>
            </div>
            <div class="table-scroll" role="region" tabindex="0" aria-label="Scrollable academic monitoring table">
                <table class="monitoring-table" aria-labelledby="monitoring-title">
                    @php($monitoringColumns = [
                        'year_level' => ['Year Level', 8],
                        'section' => ['Section', 7],
                        'subject' => ['Subject Code', 9],
                        'subject_name' => ['Subject Name', 17],
                        'faculty_name' => ['Faculty', 13],
                        'total_students' => ['Students', 7],
                        'passing_rate' => ['Passing Rate', 9],
                        'sete_rate' => ['SETE Completion', 12],
                        'grade_status' => ['Grade Submission', 12],
                    ])
                    <colgroup>
                        @foreach($monitoringColumns as [, $width])
                            <col style="width: {{ $width }}%">
                        @endforeach
                        <col style="width: 6%">
                    </colgroup>
                    <thead>
                        <tr>
                            @foreach($monitoringColumns as $key => [$label])
                                <th scope="col"
                                    @if($dashboard['table']['sort'] === $key) aria-sort="{{ $dashboard['table']['direction'] === 'asc' ? 'ascending' : 'descending' }}" @endif
                                    :aria-sort="dashboard.table.sort === '{{ $key }}' ? (dashboard.table.direction === 'asc' ? 'ascending' : 'descending') : null">
                                    <button type="button" class="table-sort" @click="sortBy('{{ $key }}')" :disabled="loading" title="Sort by {{ $label }}">
                                        <span class="table-sort-label">{{ $label }}</span>
                                        <x-icon name="arrow" class="table-sort-arrow"/>
                                    </button>
                                </th>
                            @endforeach
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="record in paginatedRows" :key="record.id">
                            <tr>
                                <td x-text="record.year_label"></td>
                                <td><span class="section-tag" x-text="record.section"></span></td>
                                <td class="subject-code" x-text="record.subject"></td>
                                <td class="subject-name-cell" x-text="record.subject_name"></td>
                                <td class="faculty-name-cell" x-text="record.faculty_name"></td>
                                <td class="numeric" x-text="record.total_students"></td>
                                <td>
                                    <span class="table-rate" :class="statusClass(record.passing_status)" :title="record.passing_status + ' · ' + record.passing_count + ' passing / ' + record.graded_count + ' graded records'" x-text="percent(record.passing_rate)"></span>
                                </td>
                                <td>
                                    <div class="rate-with-track">
                                        <span :class="statusClass(record.sete_status)" :title="record.sete_status + ' · ' + record.sete_submitted + ' of ' + record.sete_required + ' required evaluations'" x-text="percent(record.sete_rate)"></span>
                                        <span class="mini-track" aria-hidden="true" x-show="record.sete_rate !== null"><i :style="{ width: record.sete_rate + '%' }" :class="statusClass(record.sete_status)"></i></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge grade-badge" :class="gradeClass(record.grade_status)" x-text="record.grade_status" :title="'Deadline: ' + record.grade_deadline + '. ' + (record.grades_submitted_at ? 'Submitted: ' + record.grades_submitted_at : 'Not submitted as of dataset update')"></span>
                                </td>
                                <td>
                                    <button type="button" class="row-action" @click="openRecord(record, false, $el)" :aria-label="'View details for ' + record.section + ' ' + record.subject" title="View details">
                                        <x-icon name="eye"/>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="table-empty" x-show="dashboard.table.total === 0" x-cloak>
                <x-icon name="search"/>
                <strong>No academic records match this selection.</strong>
                <span>Try another search or reset the academic filters.</span>
                <button type="button" class="text-button" @click="resetAll()" :disabled="tableBusy">Reset all filters</button>
            </div>
            <div class="pagination">
                <span x-text="paginationLabel" role="status"></span>
                <div class="pagination-controls">
                    <label>Rows <select x-model.number="pageSize" @change="page = 1" :disabled="tableBusy" aria-label="Rows per page"><option value="8">8</option><option value="16">16</option><option value="32">32</option></select></label>
                    <nav aria-label="Monitoring pages">
                        <button type="button" class="page-button previous-page" @click="page--" :disabled="tableBusy || page <= 1" aria-label="Previous page"><x-icon name="chevron"/></button>
                        <template x-for="number in pageNumbers" :key="number">
                            <button type="button" class="page-button" @click="page = number" :disabled="tableBusy" :aria-label="'Page ' + number" :aria-current="page === number ? 'page' : null" x-text="number"></button>
                        </template>
                        <button type="button" class="page-button" @click="page++" :disabled="tableBusy || page >= totalPages" aria-label="Next page"><x-icon name="chevron"/></button>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <footer class="dashboard-footer" role="contentinfo">
        <span>© 2026 {{ config('app.name') }}. All rights reserved.</span>
        <span>Version 1.0.0</span>
    </footer>
    </div>
</main>

<dialog id="detail-dialog" class="detail-dialog" :class="{ 'compact-detail-dialog': isCompactDetail, 'record-dialog': detail?.type === 'record', 'kpi-dialog': isKpiDetail && detail?.type !== 'record' }" @click="if ($event.target === $el) closeDetail()" @cancel.prevent="closeDetail()" aria-labelledby="detail-title">
    <div class="detail-shell" x-show="detail">
        <div class="detail-header">
            <div>
                <span class="eyebrow" x-text="detail?.type === 'record' ? 'Class details' : detail?.type === 'period' ? 'SETE rating trends' : detail?.alert ? 'Alert analysis' : isKpiDetail ? 'KPI details' : 'Aggregated academic insights'"></span>
                <h2 id="detail-title" tabindex="-1" x-text="detail?.title"></h2>
                <p class="detail-subtitle" x-show="isCompactDetail" x-text="detail?.type === 'record' ? detail.record.subject_name : (detail?.alert?.period ?? detail?.period ?? periodLabel())"></p>
            </div>
            <button type="button" class="icon-button" @click="closeDetail()" aria-label="Close details" autofocus><x-icon name="close"/></button>
        </div>
        <nav class="detail-breadcrumbs" x-show="history.length > 0 || !isCompactDetail" aria-label="Detail breadcrumbs"><button @click="closeDetail()">Dashboard Overview</button><template x-for="(crumb, index) in breadcrumbs" :key="index"><span><x-icon name="chevron"/><button @click="goBackTo(index)" :aria-current="index === breadcrumbs.length - 1 ? 'location' : null" x-text="crumb"></button></span></template></nav>
        <div class="detail-content">
            <p class="detail-period" x-show="!isCompactDetail" x-text="periodLabel()"></p>
            <template x-if="detailKpi">
                <div class="detail-kpi-summary">
                    <div class="detail-kpi-top">
                        <div class="detail-kpi-result">
                            <strong x-text="formatMetric(detailKpi.value, detailKpi.key)"></strong>
                            <span class="status-badge" :class="statusClass(detailKpi.status)" x-text="detailKpi.status"></span>
                        </div>
                        <span class="detail-kpi-target" x-text="detailKpi.target_label"></span>
                    </div>
                    <p x-show="detailKpi.key !== 'grades'" x-text="detailKpi.description"></p>
                    <template x-if="detailKpi.key === 'grades'">
                        <div>
                            <p x-text="groupContext(dashboard.summary, 'grades')"></p>
                            <ul class="detail-kpi-counts">
                                <li x-text="dashboard.summary.on_time + ' on time'"></li>
                                <li x-text="dashboard.summary.late + ' late'"></li>
                                <li x-text="dashboard.summary.pending + ' pending'"></li>
                                <li x-text="dashboard.summary.overdue + ' overdue'"></li>
                            </ul>
                            <p x-text="dashboard.summary.grades_submitted + ' completed overall · ' + percent(dashboard.summary.grade_completion_rate)"></p>
                        </div>
                    </template>
                    <details class="detail-kpi-method">
                        <summary>How it’s calculated</summary>
                        <p x-text="detailKpi.formula"></p>
                        <p x-text="detailKpi.critical_label"></p>
                        <p x-show="detailKpi.key === 'improvement'">The comparison uses the same filters and unrounded ratings.</p>
                    </details>
                </div>
            </template>
            <template x-if="detail?.alert">
                <div class="detail-alert">
                    <section class="detail-kpi-summary alert-detail-summary" aria-labelledby="alert-metric-heading">
                        <div class="detail-kpi-top">
                            <h3 id="alert-metric-heading" x-text="detail.alert.kind === 'submission_status' ? 'Class submission status' : detail.alert.kpi"></h3>
                            <span class="alert-detail-scope" x-text="detail.alert.scope"></span>
                        </div>
                        <div class="detail-kpi-result">
                            <strong x-text="detail.alert.display"></strong>
                            <span class="status-badge" :class="statusClass(detail.alert.severity)" x-text="detail.alert.severity"></span>
                        </div>
                        <p x-text="detail.alert.explanation"></p>
                        <p class="alert-detail-threshold" x-text="detail.alert.threshold"></p>
                    </section>
                    <section class="record-group alert-detail-action" aria-labelledby="alert-action-heading">
                        <h3 id="alert-action-heading">Recommended action</h3>
                        <p x-text="detail.alert.action"></p>
                    </section>
                </div>
            </template>
            <template x-if="detail?.type === 'groups'">
                <section aria-labelledby="kpi-group-heading">
                    <h3 id="kpi-group-heading" class="detail-section-heading" x-text="history.length ? 'By section' : 'By year level'"></h3>
                    <div class="drill-groups kpi-groups">
                        <template x-for="group in detail.groups" :key="group.label">
                            <button type="button" class="drill-group" @click="descend(group)">
                                <span>
                                    <strong x-text="group.label"></strong>
                                    <small x-text="groupContext(group.summary, detail.metric)"></small>
                                </span>
                                <span class="drill-value" :class="statusClass(group.summary[detail.metric + '_status'])">
                                    <strong x-text="summaryValue(group.summary, detail.metric)"></strong>
                                    <x-icon name="chevron"/>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p class="empty-detail" x-show="!detail.groups.length">No classes match this selection.</p>
                </section>
            </template>
            <template x-if="detail?.type === 'records'">
                <div>
                    <h3 class="detail-section-heading" x-show="isKpiDetail || detail?.alert" x-text="detail?.alert?.kind === 'metric' ? 'Supporting classes' : detail?.alert ? 'Affected classes' : 'Classes'"></h3>
                    <div class="drill-groups">
                        <template x-for="record in detail.records" :key="record.id">
                            <button type="button" class="drill-group" @click="openRecord(record, true)">
                                <span>
                                    <strong x-text="record.subject + ' · ' + record.subject_name"></strong>
                                    <small x-text="record.section + ' · ' + record.faculty_name"></small>
                                </span>
                                <span class="drill-value" :class="detail.metric === 'grades' ? gradeClass(record.grade_status) : statusClass(record[detail.metric + '_status'])">
                                    <strong x-text="recordMetric(record, detail.metric)"></strong>
                                    <x-icon name="chevron"/>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p class="empty-detail" x-show="!detail.records.length">No matching classes to display.</p>
                </div>
            </template>
            <template x-if="detail?.type === 'record'">
                <div class="record-details">
                    <dl class="record-fields record-context">
                        <div><dt>Faculty</dt><dd x-text="detail.record.faculty_name"></dd></div>
                        <div><dt>Academic period</dt><dd x-text="detail.record.semester_label + ' · ' + prettyYear(detail.record.academic_year)"></dd></div>
                        <div><dt>Year level</dt><dd x-text="detail.record.year_label"></dd></div>
                    </dl>
                    <template x-for="group in recordDetailGroups(detail.record)" :key="group.title">
                        <section class="record-group">
                            <h3 x-text="group.title"></h3>
                            <dl class="record-fields">
                                <template x-for="field in group.fields" :key="field.label">
                                    <div><dt x-text="field.label"></dt><dd x-text="field.value"></dd></div>
                                </template>
                            </dl>
                        </section>
                    </template>
                    <template x-if="detail.record.rating === null">
                        <div class="privacy-note"><x-icon name="shield"/><span x-text="detail.record.rating_note"></span></div>
                    </template>
                </div>
            </template>
            <template x-if="detail?.type === 'improvement'">
                <div>
                    <h3 class="detail-section-heading" x-show="isKpiDetail || detail?.alert">Faculty comparison</h3>
                    <p class="detail-helper" x-show="dashboard.improvement.previous_period" x-text="'Previous period · ' + dashboard.improvement.previous_period + (isKpiDetail ? '' : '. The comparison uses the same filters and reportable ratings in both periods.')"></p>
                    <div class="improvement-grid" x-show="dashboard.improvement.comparable">
                        <strong>Faculty</strong><strong>Previous</strong><strong>Current</strong><strong>Change</strong>
                        <template x-for="faculty in dashboard.improvement.details" :key="faculty.faculty">
                            <div class="improvement-row">
                                <span x-text="faculty.faculty"></span>
                                <span x-text="formatMetric(faculty.previous, 'rating')"></span>
                                <span x-text="formatMetric(faculty.current, 'rating')"></span>
                                <span :class="{ 'text-success': faculty.comparison === 'improved', 'text-danger': faculty.comparison === 'declined' }" x-text="faculty.comparison"></span>
                            </div>
                        </template>
                    </div>
                    <p class="empty-detail" x-show="!dashboard.improvement.comparable">N/A — no comparable faculty results for this selection.</p>
                </div>
            </template>
            <template x-if="detail?.type === 'period'">
                <div class="period-details">
                    <div class="detail-kpi-summary period-summary">
                        <div class="detail-kpi-top">
                            <h3 class="period-rating-label">Weighted SETE rating</h3>
                            <span class="status-badge is-neutral" x-show="detail.periodData.selected">Reporting period</span>
                        </div>
                        <div class="detail-kpi-result"><strong x-text="formatMetric(detail.periodData.rating.value, 'rating')"></strong></div>
                        <p x-text="detail.periodData.rating.responses + ' reportable responses'"></p>
                    </div>
                    <section class="record-group" aria-labelledby="period-performance-heading">
                        <h3 id="period-performance-heading">Academic performance</h3>
                        <dl class="record-fields period-fields">
                            <div>
                                <dt>SETE completion</dt>
                                <dd x-text="percent(detail.periodData.summary.sete_rate)"></dd>
                                <small x-show="detail.periodData.summary.required" x-text="detail.periodData.summary.submitted + ' of ' + detail.periodData.summary.required + ' submitted'"></small>
                            </div>
                            <div>
                                <dt>Passing rate</dt>
                                <dd x-text="percent(detail.periodData.summary.passing_rate)"></dd>
                                <small x-show="detail.periodData.summary.graded" x-text="detail.periodData.summary.passing + ' of ' + detail.periodData.summary.graded + ' passed'"></small>
                            </div>
                        </dl>
                    </section>
                    <p class="privacy-note" x-show="detail.periodData.rating.withheld">Responses from small class groups are excluded from the weighted rating.</p>
                    <details class="detail-kpi-method period-method">
                        <summary>About this comparison</summary>
                        <p>The same academic filters apply across semesters. Ratings are weighted by valid responses.</p>
                    </details>
                </div>
            </template>
        </div>
        <div class="detail-footer" x-show="history.length > 0 || detail?.type === 'record' || !isCompactDetail">
            <span><x-icon name="shield"/> Aggregated reporting</span>
            <div x-show="history.length">
                <button type="button" class="button button-outline button-small" @click="back()">Back</button>
            </div>
        </div>
    </div>
</dialog>
<div class="toast" x-show="toast" x-transition x-cloak role="status" x-text="toast"></div>
<script id="dashboard-data" type="application/json">{!! json_encode($dashboard, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</body>
</html>
