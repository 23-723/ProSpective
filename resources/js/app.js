import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import '@fontsource/poppins/400.css';
import '@fontsource/poppins/500.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';

Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = '#3f4b68';
Chart.defaults.plugins.legend.display = false;

const yearLabels = { 1: 'First Year', 2: 'Second Year', 3: 'Third Year', 4: 'Fourth Year' };
const percentNumber = new Intl.NumberFormat('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: false });
const ratingNumber = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: false });

function chartGradient(chart, start, end, horizontal = false) {
    const area = chart.chartArea;
    if (!area) return start;
    const gradient = horizontal
        ? chart.ctx.createLinearGradient(area.left, 0, area.right, 0)
        : chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
    gradient.addColorStop(0, start);
    gradient.addColorStop(1, end);
    return gradient;
}

function createManilaClock() {
    const timeZone = 'Asia/Manila';
    const dateFormat = new Intl.DateTimeFormat('en-US', { timeZone, month: 'short', day: 'numeric', year: 'numeric' });
    const timeFormat = new Intl.DateTimeFormat('en-US', { timeZone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    let timer;

    return {
        timestamp: Date.now(),
        init() {
            timer = window.setInterval(() => { this.timestamp = Date.now(); }, 1000);
        },
        destroy() {
            window.clearInterval(timer);
        },
        get isoDateTime() { return new Date(this.timestamp).toISOString(); },
        get dateLabel() { return dateFormat.format(this.timestamp); },
        get timeLabel() { return timeFormat.format(this.timestamp); },
    };
}

function createDashboard() {
    let charts = {};
    let requestController;
    let tableRequestController;
    let searchTimer;
    let toastTimer;
    let lastFocus;
    let lastLocationSearch;

    return {
        dashboard: JSON.parse(document.getElementById('dashboard-data').textContent),
        draft: {}, search: '', sort: 'section', direction: 'asc',
        loading: false, error: '', toast: '', page: 1, pageSize: 8,
        tableLoading: false, tableError: '',
        showAllAlerts: false, detail: null, history: [], breadcrumbs: [],

        init() {
            this.draft = { ...this.dashboard.filters };
            this.search = this.dashboard.table.search;
            this.sort = this.dashboard.table.sort;
            this.direction = this.dashboard.table.direction;
            lastLocationSearch = window.location.search;
            this.$nextTick(async () => { await document.fonts.ready; this.renderCharts(); });
            window.addEventListener('popstate', () => {
                if (window.location.search === lastLocationSearch) return;
                lastLocationSearch = window.location.search;
                const params = new URLSearchParams(lastLocationSearch);
                const filters = { ...this.dashboard.defaults };
                Object.keys(filters).forEach(key => { if (params.has(key)) filters[key] = params.get(key); });
                this.draft = filters;
                this.search = params.get('search') || '';
                this.sort = params.get('sort') || 'section';
                this.direction = params.get('direction') || 'asc';
                this.refresh(filters, false);
            });
        },

        destroy() { Object.values(charts).forEach(chart => chart.destroy()); requestController?.abort(); this.cancelTableUpdate(); },
        prettyYear(value) { return value.replace('-', '–'); },
        periodLabel() { return `${this.dashboard.filters.semester === '1' ? 'First' : 'Second'} Semester · ${this.prettyYear(this.dashboard.filters.academic_year)}`; },
        scopeLabel() {
            const { year_level, section, subject, faculty } = this.dashboard.filters;
            const facultyName = this.dashboard.options.faculty.find(option => option.value === faculty)?.label;
            return [yearLabels[year_level], section, subject, facultyName].filter(Boolean).join(' · ') || 'All BSIT classes';
        },
        metricDefinition(key) { return this.dashboard.kpis.find(metric => metric.key === key); },
        percent(value) { return value === null ? 'N/A' : `${percentNumber.format(value)}%`; },
        formatMetric(value, metric) {
            if (value === null) return 'N/A';
            return metric === 'rating' ? `${ratingNumber.format(value)} ${this.metricDefinition('rating').unit}` : this.percent(value);
        },
        statusClass(status) { return ({ 'Meets Target': 'is-success', 'Below Target': 'is-amber', Critical: 'is-danger' })[status] || 'is-neutral'; },
        gradeClass(status) { return ({ 'On time': 'is-success', 'Submitted late': 'is-amber', Overdue: 'is-danger', Pending: 'is-neutral' })[status]; },

        availableOptions(key) {
            if (!['section', 'subject', 'faculty'].includes(key)) return this.dashboard.options[key];
            const keys = ['academic_year', 'semester', 'college', 'program', 'year_level'];
            if (key !== 'section') keys.push('section');
            if (key === 'faculty') keys.push('subject');
            const rows = this.dashboard.catalog.filter(row => keys.every(k => !this.draft[k] || row[k] === this.draft[k]));
            const options = new Map();
            rows.forEach(row => { if (row[key]) options.set(row[key], { value: row[key], label: key === 'subject' ? `${row.subject} · ${row.subject_name}` : key === 'faculty' ? row.faculty_name : row.section }); });
            return Array.from(options.values()).sort((a, b) => a.label.localeCompare(b.label));
        },
        dependencyChanged() { ['section', 'subject', 'faculty'].forEach(key => { if (this.draft[key] && !this.availableOptions(key).some(option => option.value === this.draft[key])) this.draft[key] = ''; }); },
        get hasDraftChanges() { return Object.keys(this.draft).some(key => this.draft[key] !== this.dashboard.filters[key]); },
        get visibleAlerts() { return this.showAllAlerts ? this.dashboard.alerts : this.dashboard.alerts.slice(0, 3); },
        get decisionAlerts() {
            const bySeverity = new Map();
            for (const alert of this.dashboard.alerts) {
                if (!bySeverity.has(alert.severity)) bySeverity.set(alert.severity, []);
                bySeverity.get(alert.severity).push(alert);
            }
            return [...bySeverity.values()].flatMap(alerts => {
                const firstByMetric = new Map();
                const repeats = [];
                for (const alert of alerts) {
                    if (firstByMetric.has(alert.metric)) repeats.push(alert);
                    else firstByMetric.set(alert.metric, alert);
                }
                return [...firstByMetric.values(), ...repeats];
            });
        },
        decisionComparison(alert) {
            if (alert.kind !== 'metric') {
                return { display: alert.display, target: '', gap: '' };
            }

            const metric = this.metricDefinition(alert.metric);
            const gap = Math.max(0, metric.target - alert.value);
            return {
                display: alert.display,
                target: metric.target_label,
                gap: metric.unit === '%' ? percentNumber.format(gap) + ' pp' : ratingNumber.format(gap) + ' points',
            };
        },
        get isKpiDetail() {
            const root = this.history[0] ?? this.detail;
            return Boolean(root?.metric && !root.alert);
        },
        get isCompactDetail() {
            return this.isKpiDetail || Boolean(this.detail?.alert) || ['record', 'period'].includes(this.detail?.type);
        },
        get detailKpi() { return this.detail?.metric && !this.detail.alert && this.history.length === 0 ? this.metricDefinition(this.detail.metric) : null; },
        get tableBusy() { return this.loading || this.tableLoading; },
        get totalPages() { return Math.max(1, Math.ceil(this.dashboard.table.total / this.pageSize)); },
        get pageNumbers() { return Array.from({ length: this.totalPages }, (_, index) => index + 1); },
        get paginatedRows() { return this.dashboard.table.rows.slice((this.page - 1) * this.pageSize, this.page * this.pageSize); },
        get paginationLabel() { const total = this.dashboard.table.total; return total ? `Showing ${(this.page - 1) * this.pageSize + 1}–${Math.min(this.page * this.pageSize, total)} of ${total} matching records` : 'Showing 0 matching records'; },
        query(filters = this.dashboard.filters, { search, sort, direction } = this) {
            return new URLSearchParams({ ...filters, search, sort, direction });
        },
        syncUrl() {
            window.history.replaceState({}, '', `/?${this.query()}${window.location.hash}`);
            lastLocationSearch = window.location.search;
        },

        cancelTableUpdate() {
            window.clearTimeout(searchTimer);
            tableRequestController?.abort();
            tableRequestController = null;
            this.tableLoading = false;
        },
        queueTableSearch() {
            this.cancelTableUpdate();
            this.tableLoading = true;
            this.tableError = '';
            searchTimer = window.setTimeout(() => this.refreshTable(), 170);
        },
        async refreshTable() {
            this.cancelTableUpdate();
            if (this.loading) return;

            const controller = new AbortController();
            tableRequestController = controller;
            this.tableLoading = true;
            this.tableError = '';
            try {
                const response = await fetch(`/dashboard/table?${this.query()}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const table = await response.json();
                if (controller.signal.aborted) return;

                this.dashboard.table = table;
                this.sort = table.sort;
                this.direction = table.direction;
                this.page = 1;
                this.syncUrl();
            } catch {
                if (!controller.signal.aborted) this.tableError = 'Could not update the table. Try again.';
            } finally {
                if (tableRequestController === controller) {
                    tableRequestController = null;
                    this.tableLoading = false;
                }
            }
        },

        async refresh(filters = this.dashboard.filters, updateUrl = true) {
            requestController?.abort();
            this.cancelTableUpdate();
            const controller = new AbortController();
            requestController = controller;
            this.loading = true;
            this.error = '';
            this.tableError = '';
            try {
                const response = await fetch(`/dashboard/data?${this.query(filters)}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Unable to load academic data (HTTP ${response.status}).`);
                const data = await response.json();
                if (controller.signal.aborted) return;
                this.dashboard = data;
                this.draft = { ...data.filters };
                this.sort = data.table.sort;
                this.direction = data.table.direction;
                this.page = Math.min(this.page, this.totalPages);
                this.showAllAlerts = false;
                this.$nextTick(() => this.renderCharts());
                if (updateUrl) this.syncUrl();
            } catch (error) {
                if (error.name !== 'AbortError') this.error = `${error.message} Your previous results remain visible. Please retry.`;
            } finally { if (requestController === controller) this.loading = false; }
        },
        applyFilters() { this.page = 1; this.refresh({ ...this.draft }); },
        resetAll() { this.draft = { ...this.dashboard.defaults }; this.search = ''; this.sort = 'section'; this.direction = 'asc'; this.pageSize = 8; this.applyFilters(); },
        selectYear(year) {
            if (this.loading) return;
            this.draft = { ...this.dashboard.filters, year_level: year };
            this.dependencyChanged();
            this.applyFilters();
        },
        sortBy(column) { this.direction = this.sort === column && this.direction === 'asc' ? 'desc' : 'asc'; this.sort = column; this.refreshTable(); },
        exportCsv() { const link = document.createElement('a'); link.href = `/dashboard/export?${this.query(this.dashboard.filters, this.dashboard.table)}`; link.download = ''; document.body.appendChild(link); link.click(); link.remove(); this.notify('Downloading all matching records in the current sorting order.'); },
        notify(message) { clearTimeout(toastTimer); this.toast = message; toastTimer = setTimeout(() => this.toast = '', 4500); },

        showDetail(detail, preserve = false, trigger = document.activeElement) {
            if (!preserve) { this.history = []; this.breadcrumbs = []; lastFocus = trigger; }
            else if (this.detail) this.history.push(this.detail);
            this.detail = detail;
            this.breadcrumbs = [...this.history.map(item => item.title), detail.title];
            const dialog = document.getElementById('detail-dialog');
            this.$nextTick(() => {
                if (!this.detail) return;
                dialog.querySelector('.detail-content').scrollTop = 0;
                if (!dialog.open) dialog.showModal();
                dialog.querySelector(preserve ? '#detail-title' : '[aria-label="Close details"]').focus({ preventScroll: true });
            });
        },
        openMetric(metric, trigger) {
            const title = this.metricDefinition(metric).title;
            this.showDetail(metric === 'improvement' ? { type: 'improvement', title, metric } : { type: 'groups', title, metric, groups: this.dashboard.drilldown }, false, trigger);
        },
        descend(group) { this.showDetail(group.sections ? { type: 'groups', title: group.label, metric: this.detail.metric, groups: group.sections } : { type: 'records', title: group.label, metric: this.detail.metric, records: group.records }, true); },
        openRecord(record, preserve = false, trigger = document.activeElement) {
            this.showDetail({ type: 'record', title: `${record.section} · ${record.subject}`, record }, preserve, trigger);
        },
        openPeriod(period, trigger) {
            const label = `${period.semester_label} · ${this.prettyYear(period.academic_year)}`;
            this.showDetail({ type: 'period', title: 'Semester comparison', period: label, periodData: period }, false, trigger);
        },
        openAlert(alert, trigger) {
            const popover = trigger.closest('[popover]');
            const returnFocus = popover ? document.getElementById('alerts-toggle') : trigger;
            popover?.hidePopover();
            const records = this.dashboard.records.filter(record => alert.record_ids.includes(record.id));
            this.showDetail(alert.metric === 'improvement'
                ? { type: 'improvement', title: alert.title, alert }
                : { type: 'records', title: alert.title, alert, metric: alert.metric, records }, false, returnFocus);
        },
        back() { this.goBackTo(this.history.length - 1); },
        goBackTo(index) {
            if (index < 0 || index >= this.history.length) return;
            this.detail = this.history[index];
            this.history = this.history.slice(0, index);
            this.breadcrumbs = [...this.history.map(item => item.title), this.detail.title];
            this.$nextTick(() => {
                const dialog = document.getElementById('detail-dialog');
                if (!dialog.open) return;
                dialog.querySelector('.detail-content').scrollTop = 0;
                dialog.querySelector('#detail-title').focus({ preventScroll: true });
            });
        },
        closeDetail() {
            const dialog = document.getElementById('detail-dialog');
            if (!dialog.open) return;
            dialog.close();
            this.detail = null;
            this.history = [];
            this.breadcrumbs = [];
            lastFocus?.focus({ preventScroll: true });
            lastFocus = null;
        },
        summaryValue(summary, metric) { return this.formatMetric(metric === 'rating' ? summary.rating.value : metric === 'grades' ? summary.grade_rate : metric === 'passing' ? summary.passing_rate : summary.sete_rate, metric); },
        groupContext(summary, metric) {
            switch (metric) {
                case 'sete': return `${summary.submitted} of ${summary.required} evaluations submitted`;
                case 'passing': return `${summary.passing} of ${summary.graded} final grades passed`;
                case 'grades': return `${summary.on_time} of ${summary.grades_required} classes submitted on time`;
                case 'rating': return `${summary.rating.responses} reportable responses`;
            }
        },
        recordMetric(record, metric) { return metric === 'grades' ? record.grade_status : this.formatMetric(metric === 'rating' ? record.rating : metric === 'passing' ? record.passing_rate : record.sete_rate, metric); },
        recordDetailGroups(record) {
            return [
                ['Student performance', [
                    ['Total students', record.total_students],
                    ['Records with final grades', record.graded_count],
                    ['Passing rate', this.percent(record.passing_rate)],
                    ['Passing students', record.passing_count],
                    ['Failing students', record.failing_count],
                    ['Missing final grades', record.total_students - record.graded_count],
                ]],
                ['SETE evaluation', [
                    ['Required evaluations', record.sete_required],
                    ['Submitted evaluations', record.sete_submitted],
                    ['Completion rate', this.percent(record.sete_rate)],
                    ['Weighted rating', this.formatMetric(record.rating, 'rating')],
                    ['Valid rating responses', record.rating_responses ?? 'N/A'],
                ]],
                ['Grade submission', [
                    ['Status', record.grade_status],
                    ['Deadline', record.grade_deadline],
                    ['Submitted on', record.grades_submitted_at || 'Not submitted'],
                ]],
            ].map(([title, fields]) => ({ title, fields: fields.map(([label, value]) => ({ label, value })) }));
        },

        renderCharts() {
            Object.values(charts).forEach(chart => chart.destroy());
            charts = {};
            const data = JSON.parse(JSON.stringify(this.dashboard.charts));
            const ratingScale = this.dashboard.metadata.rating_scale;
            const red = '#be1122';
            const grid = { color: '#e9e4ee', drawTicks: false };
            const border = { color: '#e2dce8' };
            const tickFont = ({ chart }) => ({ size: chart.width < 460 ? 10 : 11 });
            const tooltip = { backgroundColor: '#202941', padding: 11, cornerRadius: 8, displayColors: false };
            const common = {
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 250 },
                interaction: { mode: 'nearest', intersect: true },
                plugins: { legend: { display: false }, tooltip },
                onHover(event, elements) {
                    if (event.native?.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
            };
            const categoryAxis = { offset: true, grid: { display: false, drawTicks: false }, border, ticks: { display: false } };
            const alignedLabels = {
                id: 'academicAxisLabels',
                afterLayout(chart) {
                    const plot = chart.canvas.closest('.chart-plot');
                    plot.style.setProperty('--plot-inline-start', `${chart.chartArea.left}px`);
                    plot.style.setProperty('--plot-inline-end', `${chart.width - chart.chartArea.right}px`);
                },
            };

            charts.trend = new Chart(document.getElementById('trend-chart'), {
                type: 'line',
                data: {
                    labels: data.trend.map(period => period.short_label),
                    datasets: [{
                        data: data.trend.map(period => period.rating.value),
                        borderColor: red,
                        backgroundColor: ({ chart }) => chartGradient(chart, '#fbd3dfc7', '#fae5ee8c'),
                        borderWidth: 2.5,
                        fill: 'start',
                        tension: .23,
                        pointRadius: data.trend.map(period => period.selected ? 6 : 4),
                        pointBackgroundColor: data.trend.map(period => period.selected ? red : '#fff'),
                        pointBorderColor: red,
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        spanGaps: false,
                    }],
                },
                options: {
                    ...common,
                    layout: { padding: { top: 20, right: 6 } },
                    scales: {
                        x: categoryAxis,
                        y: {
                            min: ratingScale.min, max: ratingScale.max, grid, border,
                            ticks: { stepSize: 1, callback: value => Number(value).toFixed(1), padding: 8, font: tickFont },
                            title: { display: true, text: `Average rating (/${ratingScale.max.toFixed(1)})`, font: tickFont },
                        },
                    },
                    plugins: {
                        ...common.plugins,
                        tooltip: {
                            ...tooltip,
                            callbacks: {
                                title: items => data.trend[items[0].dataIndex].label,
                                label: item => {
                                    const period = data.trend[item.dataIndex];
                                    return `Weighted rating: ${this.formatMetric(item.parsed.y, 'rating')} · ${period.rating.responses} responses`;
                                },
                            },
                        },
                    },
                    onClick: (_, elements) => {
                        if (elements.length) this.openPeriod(data.trend[elements[0].index]);
                    },
                },
                plugins: [alignedLabels],
            });

            const barLabels = {
                id: 'academicBarLabels',
                afterDatasetsDraw(chart) {
                    const context = chart.ctx;
                    const horizontal = chart.options.indexAxis === 'y';
                    context.save();
                    context.font = `600 ${chart.width < 460 ? 11 : 12}px ${Chart.defaults.font.family}`;
                    context.fillStyle = '#121a30';
                    context.textAlign = horizontal ? 'left' : 'center';
                    context.textBaseline = horizontal ? 'middle' : 'bottom';
                    chart.getDatasetMeta(0).data.forEach((bar, index) => {
                        const value = chart.data.datasets[0].data[index];
                        if (value === null) return;
                        context.fillText(percentNumber.format(value) + '%', horizontal ? bar.x + 7 : bar.x, horizontal ? bar.y : bar.y - 6);
                    });
                    context.restore();
                },
            };
            const percentageAxis = stepSize => ({
                min: 0, max: 100, grid, border,
                ticks: { stepSize, callback: value => `${value}%`, padding: 8, font: tickFont },
            });

            const fills = {
                'Meets Target': ['#b82730', '#98141d'],
                'Below Target': ['#e8b35d', '#b47723'],
                Critical: ['#ff777d', '#eb4855'],
                'N/A': ['#d9dce4', '#cbd0db'],
            };
            for (const { key, metric, label, horizontal } of [
                { key: 'passing', metric: 'passing', label: 'Passing rate', horizontal: false },
                { key: 'completion', metric: 'sete', label: 'SETE completion', horizontal: true },
            ]) {
                charts[key] = new Chart(document.getElementById(`${key}-chart`), {
                    type: 'bar',
                    data: {
                        labels: data.years.map(year => year.label),
                        datasets: [{
                            data: data.years.map(year => year[`${metric}_rate`]),
                            backgroundColor: ({ chart, dataIndex }) => {
                                const status = data.years[dataIndex]?.[`${metric}_status`] ?? 'N/A';
                                return chartGradient(chart, ...fills[status], horizontal);
                            },
                            hoverBackgroundColor: ({ dataIndex }) => fills[data.years[dataIndex]?.[`${metric}_status`] ?? 'N/A'][1],
                            borderRadius: horizontal ? 5 : 8,
                            maxBarThickness: horizontal ? 19 : 82,
                            categoryPercentage: horizontal ? .72 : .66,
                        }],
                    },
                    options: {
                        ...common,
                        indexAxis: horizontal ? 'y' : 'x',
                        layout: { padding: horizontal ? { top: 3, right: 59 } : { top: 20, right: 6 } },
                        scales: horizontal
                            ? { x: percentageAxis(20), y: { grid: { display: false }, border, ticks: { padding: 8, font: tickFont } } }
                            : { x: categoryAxis, y: { ...percentageAxis(25), title: { display: true, text: 'Passing rate (%)', font: tickFont } } },
                        plugins: {
                            ...common.plugins,
                            tooltip: { ...tooltip, callbacks: { label: item => {
                                const year = data.years[item.dataIndex];
                                const counts = horizontal ? `${year.submitted} of ${year.required} submissions` : `${year.passing} of ${year.graded} final grades`;
                                return [`${label}: ${this.percent(item.raw)}`, counts, year[`${metric}_status`]];
                            } } },
                        },
                        onClick: (_, elements) => {
                            if (elements.length) this.selectYear(data.years[elements[0].index].value);
                        },
                    },
                    plugins: horizontal ? [barLabels] : [barLabels, alignedLabels],
                });
            }

        },
    };
}

window.Alpine = Alpine;
Alpine.data('academicDashboard', createDashboard);
Alpine.data('manilaClock', createManilaClock);
Alpine.start();
