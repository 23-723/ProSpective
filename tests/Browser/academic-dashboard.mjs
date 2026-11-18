import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const base = new URL(process.env.ACALYTICS_BASE_URL ?? 'http://127.0.0.1:8000');
assert.ok(['127.0.0.1', 'localhost'].includes(base.hostname), 'The browser audit runs against an isolated local server.');

const chromePath = process.env.ACALYTICS_CHROME ?? (process.platform === 'win32'
    ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    : 'chromium');
const scratchRoot = path.resolve(tmpdir());
const scratch = await mkdtemp(path.join(scratchRoot, 'acalytics-browser-'));
const chrome = spawn(chromePath, [
    '--headless=new', '--disable-gpu', '--disable-background-networking',
    '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1',
    '--remote-debugging-port=0', '--user-data-dir=' + path.join(scratch, 'profile'), 'about:blank',
], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let launchError;
let stderr = '';
chrome.on('error', error => { launchError = error; });
chrome.stderr.on('data', data => { stderr = (stderr + data).slice(-1500); });
let socket;

async function until(check, label) {
    const deadline = Date.now() + 12000;
    while (Date.now() < deadline) {
        const value = await check();
        if (value) return value;
        await delay(45);
    }
    assert.fail(label);
}

try {
    const debugPort = await until(async () => {
        if (launchError) throw launchError;
        if (chrome.exitCode !== null) throw new Error(stderr);
        return (await readFile(path.join(scratch, 'profile', 'DevToolsActivePort'), 'utf8').catch(() => '')).split('\n')[0];
    }, 'Chrome did not open a local DevTools endpoint.');
    const tabs = await (await fetch('http://127.0.0.1:' + debugPort + '/json/list')).json();
    const target = tabs.find(tab => tab.type === 'page');
    socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });

    const pending = new Map();
    const browserErrors = [];
    let messageId = 0;
    socket.addEventListener('close', event => {
        const error = new Error(`DevTools disconnected (${event.code} ${event.reason}). ${stderr.trim()}`);
        for (const call of pending.values()) call.reject(error);
        pending.clear();
    });
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id) {
            const call = pending.get(message.id);
            pending.delete(message.id);
            if (message.error) call.reject(new Error(message.error.message));
            else call.resolve(message.result);
        } else if (message.method === 'Runtime.exceptionThrown') {
            const error = message.params.exceptionDetails;
            browserErrors.push(error.exception?.description ?? error.text);
        } else if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') {
            browserErrors.push(message.params.args.map(arg => arg.value ?? arg.description).join(' '));
        }
    });

    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            if (socket.readyState !== WebSocket.OPEN) {
                reject(new Error(`DevTools connection closed before ${method}. ${stderr.trim()}`));
                return;
            }
            const id = ++messageId;
            pending.set(id, { resolve, reject });
            socket.send(JSON.stringify({ id, method, params }));
        });
    }

    async function onPage(fn, ...args) {
        const result = await send('Runtime.evaluate', {
            expression: '(' + fn.toString() + ')(...' + JSON.stringify(args) + ')',
            awaitPromise: true,
            returnByValue: true,
        });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
        return result.result.value;
    }

    const click = selector => onPage(selector => document.querySelector(selector).click(), selector);
    const text = selector => onPage(selector => document.querySelector(selector)?.textContent.trim(), selector);
    const state = () => onPage(() => {
        const app = Alpine.$data(document.body);
        return { data: JSON.parse(JSON.stringify(app.dashboard)), page: app.page, pageSize: app.pageSize, tableBusy: app.tableBusy, loading: app.loading };
    });
    const choose = (model, value) => onPage((model, value) => {
        const select = document.querySelector(`select[x-model="${model}"], select[x-model\\.number="${model}"]`);
        if (![...select.options].some(option => option.value === value)) throw new Error('Unavailable option ' + model + '=' + value);
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }, model, value);
    const dialogOpen = () => onPage(() => document.querySelector('#detail-dialog').open);
    const waitDialog = () => until(dialogOpen, 'The detail dialog did not open.');
    const closeDialog = () => click('#detail-dialog [aria-label="Close details"]');

    async function navigate(relative = '/') {
        const url = new URL(relative, base).href;
        await send('Page.navigate', { url });
        await until(() => onPage(url => location.href === url && !!window.Alpine && document.querySelector('.chart-plot')?.style.getPropertyValue('--plot-inline-start'), url),
            'Alpine did not start or Chart.js did not render.');
    }

    await send('Page.enable');
    await send('Runtime.enable');
    await send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: scratch });
    await send('Page.addScriptToEvaluateOnNewDocument', {
        source: `
            window.auditTrendPoints = new Map();
            window.auditTooltips = [];
            const arc = CanvasRenderingContext2D.prototype.arc;
            CanvasRenderingContext2D.prototype.arc = function (x, y, radius, ...args) {
                if (this.canvas.id === 'trend-chart' && radius >= 3 && radius <= 8) window.auditTrendPoints.set(Math.round(x), { x, y });
                return arc.call(this, x, y, radius, ...args);
            };
            const fillText = CanvasRenderingContext2D.prototype.fillText;
            CanvasRenderingContext2D.prototype.fillText = function (text, x, y, ...args) {
                if (/^(Weighted rating|Passing rate|SETE completion): /.test(String(text))) window.auditTooltips.push(text);
                if (this.canvas.id === 'completion-chart' && text === '90.0%') window.auditCompletionPoint = { x: x - 50, y };
                return fillText.call(this, text, x, y, ...args);
            };
        `,
    });
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 960, deviceScaleFactor: 1, mobile: false });
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await navigate();

    const verifyFooter = async () => assert.ok(await onPage(() => {
        const footer = document.querySelector('.dashboard-footer');
        const [copyright, version] = footer.children;
        const bounds = footer.getBoundingClientRect();
        return footer.children.length === 2
            && copyright.textContent === '© 2026 ProSpective. All rights reserved.'
            && version.textContent === 'Version 1.0.0'
            && getComputedStyle(footer).borderTopWidth === '1px'
            && bounds.top > document.querySelector('#performance-monitoring').getBoundingClientRect().bottom
            && Math.abs(bounds.bottom + scrollY - document.documentElement.scrollHeight) <= 1
            && Math.abs(copyright.getBoundingClientRect().left - bounds.left) <= 1
            && Math.abs(version.getBoundingClientRect().right - bounds.right) <= 1
            && copyright.scrollWidth <= copyright.clientWidth;
    }), 'The footer should fit below the table with copyright on the left and version on the right.');
    await verifyFooter();

    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
    const applyBackgroundAlpha = () => onPage(() => new Promise(resolve => requestAnimationFrame(() => {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        context.fillStyle = getComputedStyle(document.querySelector('.filter-apply')).backgroundColor;
        context.fillRect(0, 0, 1, 1);
        resolve(context.getImageData(0, 0, 1, 1).data[3]);
    })));
    const applyPoint = await onPage(() => {
        const button = document.querySelector('.filter-apply');
        button.scrollIntoView({ block: 'nearest' });
        const bounds = button.getBoundingClientRect();
        return { x: bounds.left + bounds.width / 2, y: bounds.top + bounds.height / 2 };
    });
    assert.equal(await applyBackgroundAlpha(), 255);
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...applyPoint });
    assert.ok(await onPage(() => document.querySelector('.filter-apply').matches(':hover')));
    assert.equal(await applyBackgroundAlpha(), 255);
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 2, y: 2 });
    assert.equal(await applyBackgroundAlpha(), 255);
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });

    const initial = (await state()).data;
    assert.equal(await text('.brand strong'), 'ProSpective');
    assert.equal(await text('.hero-heading p'), 'An Integrated Academic Performance and Teaching Effectiveness Analytics and Decision Support System for Program Chairs');
    await until(() => onPage(() => {
        const logo = document.querySelector('.brand-mark');
        const favicon = document.querySelector('link[rel="icon"]');
        return logo.complete && logo.naturalWidth > 0 && logo.src === favicon.href;
    }), 'The provided logo did not load in the dashboard header.');
    assert.deepEqual(initial.kpis.map(metric => metric.display), ['78.5', '88.6', '91.7', '75.0', '4.14']);
    assert.equal(await onPage(() => document.querySelectorAll('.kpi-card').length), 5);
    assert.equal(await onPage(() => document.querySelectorAll('.charts-grid canvas').length), 3);
    assert.ok(await onPage(() => !document.querySelector('.teaching-insights, .kpi-info')));
    assert.ok(await onPage(() => innerWidth === 1440 && document.documentElement.scrollWidth <= innerWidth));
    assert.match(await text('.target-note'), /78\.5%.*90%/);
    assert.match(await text('.insight-copy'), /Student-subject evaluations submitted/);
    assert.match(await text('.header-clock-time'), /\d\d:\d\d:\d\d [AP]M/);
    assert.equal(await text('.decision-section .section-heading p'), initial.alerts.length + ' active alerts · First Semester · 2025–2026');
    console.log('PASS dashboard render, KPI values, snapshot, clock, and alert count');

    const moveOnCanvas = async (selector, fractionX, fractionY) => {
        const point = await onPage((selector, fractionX, fractionY) => {
            const canvas = document.querySelector(selector);
            canvas.scrollIntoView({ block: 'center' });
            const bounds = canvas.getBoundingClientRect();
            return { x: bounds.left + bounds.width * fractionX, y: bounds.top + bounds.height * fractionY };
        }, selector, fractionX, fractionY);
        await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
    };
    await moveOnCanvas('#passing-chart', .19, .55);
    await until(() => onPage(() => window.auditTooltips.includes('Passing rate: 94.0%')), 'Passing chart tooltip did not reconcile.');
    const completionPoint = await onPage(() => {
        const canvas = document.querySelector('#completion-chart');
        canvas.scrollIntoView({ block: 'center' });
        const bounds = canvas.getBoundingClientRect();
        return { x: bounds.left + window.auditCompletionPoint.x, y: bounds.top + window.auditCompletionPoint.y };
    });
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...completionPoint });
    await until(() => onPage(() => window.auditTooltips.includes('SETE completion: 90.0%')), 'Completion chart tooltip did not reconcile.');

    await until(() => onPage(() => window.auditTrendPoints.size >= 6), 'Trend points were not drawn.');
    const point = await onPage(() => {
        const canvas = document.querySelector('#trend-chart');
        canvas.scrollIntoView({ block: 'center' });
        const bounds = canvas.getBoundingClientRect();
        const point = [...window.auditTrendPoints.values()].sort((a, b) => a.x - b.x)[4];
        return { x: bounds.left + point.x, y: bounds.top + point.y };
    });
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
    await until(() => onPage(() => window.auditTooltips.some(line => line.includes('4.14 / 5.0') && line.includes('758 responses'))),
        'The selected trend tooltip did not show its reconciled rating and response count.');
    await send('Input.dispatchMouseEvent', { ...point, type: 'mousePressed', button: 'left', clickCount: 1 });
    await send('Input.dispatchMouseEvent', { ...point, type: 'mouseReleased', button: 'left', clickCount: 1 });
    await waitDialog();
    assert.equal(await text('.detail-subtitle'), 'First Semester · 2025–2026');
    assert.match(await text('.period-summary'), /4\.14 \/ 5\.0/);
    assert.ok(await onPage(() => document.querySelector('.period-summary .status-badge').getClientRects().length > 0));
    await closeDialog();

    const historicalPeriod = '.trend-period-buttons button[aria-label^="View Second Semester 2024-2025,"]';
    await click(historicalPeriod);
    await waitDialog();
    assert.equal(await text('.detail-subtitle'), 'Second Semester · 2024–2025');
    assert.match(await text('.period-summary'), /4\.10 \/ 5\.0/);
    assert.match(await text('.period-summary'), /745 reportable responses/);
    assert.match(await text('.period-fields'), /77\.5%\s+745 of 961 submitted/);
    assert.ok(await onPage(() => document.querySelector('#detail-dialog').getBoundingClientRect().width <= 600));
    assert.ok(await onPage(() => !document.querySelector('.period-method').open));
    await click('.period-method summary');
    assert.ok(await onPage(() => document.querySelector('.period-method').open));
    await closeDialog();
    assert.ok(await onPage(selector => document.activeElement.matches(selector), historicalPeriod));

    await click('.insight-progress button');
    await waitDialog();
    assert.match(await text('.detail-kpi-summary'), /758 of 965/);
    assert.equal(await text('#kpi-group-heading'), 'By year level');
    assert.match(await text('.kpi-groups .drill-group small'), /225 of 250 evaluations submitted/);
    assert.ok(await onPage(() => !document.querySelector('.detail-kpi-method').open));
    await click('.detail-kpi-method summary');
    assert.ok(await onPage(() => document.querySelector('.detail-kpi-method').open));
    await click('.drill-groups .drill-group');
    await until(() => text('#detail-title').then(title => title === 'First Year'), 'Participation drill-down did not open the first year.');
    assert.equal(await text('#kpi-group-heading'), 'By section');
    await click('.kpi-groups .drill-group');
    await until(() => text('#detail-title').then(title => title === 'BSIT 1A'), 'KPI section drill-down did not open.');
    await click('#detail-dialog .drill-groups .drill-group');
    await until(() => onPage(() => document.querySelector('#detail-dialog').classList.contains('record-dialog')), 'KPI class drill-down did not open.');
    await click('#detail-dialog .detail-footer button');
    await until(() => text('#detail-title').then(title => title === 'BSIT 1A'), 'KPI Back button did not restore the section.');
    await click('#detail-dialog .detail-breadcrumbs span:first-of-type button');
    await until(() => text('#detail-title').then(title => title === 'SETE Completion Rate'), 'KPI breadcrumb did not restore the metric overview.');
    await closeDialog();
    await click('.kpi-grades .kpi-open');
    await waitDialog();
    assert.deepEqual(await onPage(() => [...document.querySelectorAll('.detail-kpi-counts li')].map(item => item.textContent)),
        ['22 on time', '1 late', '0 pending', '1 overdue']);
    await closeDialog();
    for (const key of ['passing', 'improvement', 'rating']) {
        await click('.kpi-' + key + ' .kpi-open');
        await waitDialog();
        assert.equal(await text('#detail-title'), initial.kpis.find(metric => metric.key === key).title);
        assert.ok(await onPage(() => document.querySelector('#detail-dialog').getBoundingClientRect().width <= 600));
        await closeDialog();
    }
    console.log('PASS Chart.js tooltips, semester details, all five compact KPI dialogs, formulas, and drill-down navigation');

    assert.ok(await onPage(() => {
        const section = document.querySelector('.decision-section');
        const grid = section.querySelector('.decision-alerts');
        const cards = [...grid.querySelectorAll('.alert-card')];
        return cards.length === 3
            && getComputedStyle(section).fontFamily.includes('Poppins')
            && getComputedStyle(grid).gridTemplateColumns.split(' ').length === 3
            && cards.every(card => getComputedStyle(card).backgroundImage === 'none'
                && [...card.querySelectorAll('.decision-icon .icon')].filter(icon => icon.getClientRects().length).length === 1);
    }));
    assert.deepEqual(await onPage(() => [...document.querySelectorAll('.decision-section > .decision-alerts > .decision-card')].map(card => [
        card.querySelector('h3').textContent,
        card.querySelector('.alert-value').textContent,
        card.querySelector('.decision-gap strong').textContent,
    ])), [
        ['SETE Completion Rate', '68.0%', '22.0 pp'],
        ['Student Passing Rate', '68.6%', '16.4 pp'],
        ['Overdue grade submissions', '1 class', ''],
    ]);
    await click('.decision-more summary');
    assert.ok(await onPage(() => document.querySelector('.decision-more').open
        && document.querySelectorAll('.decision-more .alert-card').length === Alpine.$data(document.body).dashboard.alerts.length - 3));
    assert.ok(await onPage(() => !document.querySelector('.decision-more button[aria-label^="View full analysis for On-time grade submission below target"]')));
    await click('.decision-more summary');
    await click('.decision-section .decision-alerts .alert-card .text-button');
    await waitDialog();
    assert.match(await text('.detail-alert'), /Recommended action/);
    await closeDialog();
    await until(() => onPage(() => document.querySelector('#detail-dialog .detail-breadcrumbs').style.display !== 'none'),
        'The alert dialog did not finish resetting after close.');
    const overdueAlert = '.decision-alerts button[aria-label^="View full analysis for Overdue grade submissions"]';
    await click(overdueAlert);
    await waitDialog();
    assert.equal(await text('#detail-title'), 'Overdue grade submissions');
    assert.equal(await text('.alert-detail-scope'), 'BSIT 4B');
    assert.equal(await text('.alert-detail-summary .detail-kpi-result strong'), '1 class');
    assert.equal(await text('.alert-detail-summary .status-badge'), 'Critical');
    assert.equal(await text('#detail-dialog .detail-section-heading'), 'Affected classes');
    assert.match(await text('#detail-dialog .drill-group'), /IT 413/);
    assert.equal(await text('#detail-dialog .drill-value.is-danger strong'), 'Overdue');
    assert.ok(await onPage(() => {
        const dialog = document.querySelector('#detail-dialog');
        return dialog.getBoundingClientRect().width <= 600 && dialog.querySelector('.detail-breadcrumbs').getClientRects().length === 0;
    }));
    await click('#detail-dialog .drill-group');
    await until(() => text('#detail-title').then(title => title === 'BSIT 4B · IT 413'), 'Alert evidence did not open the affected class.');
    await click('#detail-dialog .detail-footer button');
    await until(() => text('#detail-title').then(title => title === 'Overdue grade submissions'), 'Back did not restore the alert analysis.');
    await closeDialog();
    assert.ok(await onPage(selector => document.activeElement.matches(selector), overdueAlert));
    console.log('PASS Decision Support card styling, Other alerts toggle, and evidence');

    await click('#alerts-toggle');
    assert.ok(await onPage(() => document.querySelector('#academic-alerts').matches(':popover-open')));
    await click('#academic-alerts .more-alerts');
    await until(() => onPage(() => document.querySelectorAll('#academic-alerts .alert-card').length === Alpine.$data(document.body).dashboard.alerts.length), 'Bell did not expose all active alerts.');
    await click('#academic-alerts .alert-card .text-button');
    await waitDialog();
    assert.match(await text('.detail-alert'), /Recommended action/);
    assert.ok(await onPage(() => document.querySelectorAll('#detail-dialog .drill-group').length > 0));
    await closeDialog();
    await click('.profile-button');
    assert.ok(await onPage(() => document.querySelector('#program-profile').matches(':popover-open')));
    await click('#program-profile a');
    assert.equal(await onPage(() => location.hash), '#performance-monitoring');
    assert.equal((await state()).loading, false);
    console.log('PASS bell, alert evidence and recommendations, and Program Chair navigation');

    await click('[aria-label="Next page"]');
    await until(async () => (await state()).page === 2, 'Next page did not activate.');
    assert.match(await text('.pagination [role="status"]'), /9–16 of 24/);
    assert.equal(await text('th[aria-sort="ascending"] .table-sort-label'), 'Section');
    const passingSort = '[title="Sort by Passing Rate"]';
    for (const [order, direction] of [['asc', 'Ascending'], ['desc', 'Descending']]) {
        await click(passingSort);
        await until(async () => {
            const { data, tableBusy } = await state();
            return !tableBusy && data.table.sort === 'passing_rate' && data.table.direction === order;
        }, 'Passing Rate sorting did not switch to ' + direction + '.');
        const { data, page } = await state();
        const rates = data.table.rows.map(row => row.passing_rate);
        assert.deepEqual(rates, rates.toSorted((a, b) => order === 'asc' ? a - b : b - a));
        assert.equal(page, 1);
        assert.equal(await text('th[aria-sort="' + direction.toLowerCase() + '"] .table-sort-label'), 'Passing Rate');
        assert.equal(await onPage(() => document.querySelectorAll('.monitoring-table th[aria-sort]').length), 1);
        const arrow = await onPage(() => {
            const header = document.querySelector('th[aria-sort]');
            const icon = header.querySelector('.table-sort-arrow');
            return {
                besideLabel: icon.getBoundingClientRect().left >= header.querySelector('.table-sort-label').getBoundingClientRect().right,
                pointsUp: new DOMMatrix(getComputedStyle(icon).transform).b < 0,
                visibleCount: [...document.querySelectorAll('.table-sort-arrow')].filter(icon => icon.getClientRects().length).length,
            };
        });
        assert.deepEqual(arrow, { besideLabel: true, pointsUp: order === 'asc', visibleCount: 1 });
    }
    await click('[aria-label="Next page"]');
    assert.match(await text('.pagination [role="status"]'), /9–16 of 24/);
    await click('.monitoring-export');
    const csv = await until(() => readFile(path.join(scratch, 'prospective-academic-report-2025-2026.csv'), 'utf8').catch(() => ''), 'The Export CSV button did not download a file.');
    assert.equal(csv.trim().split(/\r?\n/).length, 25);
    await choose('pageSize', '16');
    assert.equal((await state()).pageSize, 16);
    assert.match(await text('.pagination [role="status"]'), /1–16 of 24/);
    await onPage(() => {
        const input = document.querySelector('.search-field input');
        for (const value of ['I', 'IT', 'IT 31']) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    await until(async () => !(await state()).tableBusy, 'Table search did not settle.');
    assert.equal(await text('.monitoring-record-total'), '6 records');
    assert.equal(await text('th[aria-sort="descending"] .table-sort-label'), 'Passing Rate');
    assert.deepEqual((await state()).data.kpis, initial.kpis);
    const position = await onPage(() => {
        document.querySelector('.row-action').scrollIntoView({ block: 'center' });
        return [scrollY, document.querySelector('.table-scroll').scrollLeft];
    });
    await click('.row-action');
    await waitDialog();
    const firstResult = (await state()).data.table.rows[0];
    assert.equal(await text('#detail-title'), firstResult.section + ' · ' + firstResult.subject);
    assert.match(await text('.record-context'), new RegExp(firstResult.faculty_name));
    await closeDialog();
    assert.deepEqual(await onPage(() => [scrollY, document.querySelector('.table-scroll').scrollLeft]), position);
    console.log('PASS search, pagination, page size, sorting, complete CSV download, and stable eye-dialog scrolling');

    await choose('draft.academic_year', '2024-2025');
    await choose('draft.semester', '2');
    await choose('draft.year_level', '3');
    await choose('draft.section', 'BSIT 3A');
    await choose('draft.subject', 'IT 312');
    await choose('draft.faculty', 'F06');
    await click('.filter-apply');
    await until(async () => (await state()).data.filters.faculty === 'F06', 'Combined academic filters did not apply.');
    const combined = (await state()).data;
    assert.equal(combined.summary.classes, 1);
    assert.equal(combined.filters.academic_year, '2024-2025');
    assert.equal(combined.filters.semester, '2');
    assert.equal(combined.charts.trend.find(period => period.selected).rating.value, combined.kpis[4].value);
    await click('.filter-reset');
    await until(async () => (await state()).data.table.total === 24, 'Reset All did not restore the default record set.');
    assert.equal((await state()).pageSize, 8);
    await click('.chart-filter-buttons button[aria-label^="Third Year"]');
    await until(async () => (await state()).data.filters.year_level === '3', 'Chart year-level selection did not apply.');
    assert.equal((await state()).data.table.total, 6);
    console.log('PASS dependent dropdowns, combined filters, Reset All, and chart filtering');

    await navigate('/?semester=2&section=BSIT%204B&subject=IT%20412');
    assert.equal((await state()).data.kpis[4].display, 'N/A');
    await click('.row-action');
    await waitDialog();
    assert.match(await text('.record-details .privacy-note'), /Insufficient responses/);
    assert.match(await text('.record-details'), /Valid rating responses\s*N\/A/);
    await closeDialog();
    await navigate('/?program=UNKNOWN');
    assert.equal((await state()).data.alerts.length, 0);
    assert.ok((await state()).data.kpis.every(metric => metric.display === 'N/A'));
    assert.match(await text('.table-empty'), /No academic records/);
    await verifyFooter();
    await navigate('/');

    for (const width of [820, 390]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 600 });
        const layout = () => onPage(() => ({
            viewport: innerWidth,
            document: document.documentElement.scrollWidth,
            tableScrolls: document.querySelector('.table-scroll').scrollWidth > document.querySelector('.table-scroll').clientWidth,
            chartColumns: getComputedStyle(document.querySelector('.charts-grid')).gridTemplateColumns.split(' ').length,
            alertColumns: getComputedStyle(document.querySelector('.decision-alerts')).gridTemplateColumns.split(' ').length,
        }));
        await until(async () => (await layout()).viewport === width, 'Responsive viewport width did not settle.');
        const actual = await layout();
        assert.ok(actual.document <= width, JSON.stringify(actual));
        assert.ok(actual.tableScrolls);
        assert.equal(actual.chartColumns, 1);
        assert.equal(actual.alertColumns, width <= 560 ? 1 : 2);
        await verifyFooter();
    }
    await click('.row-action');
    await waitDialog();
    assert.ok(await onPage(() => document.querySelector('#detail-dialog').getBoundingClientRect().width < innerWidth));
    await closeDialog();
    await click('.kpi-sete .kpi-open');
    await waitDialog();
    assert.ok(await onPage(() => {
        const dialog = document.querySelector('#detail-dialog');
        const content = dialog.querySelector('.detail-content');
        return dialog.getBoundingClientRect().width < innerWidth && content.scrollWidth <= content.clientWidth;
    }));
    await closeDialog();
    await click(overdueAlert);
    await waitDialog();
    assert.ok(await onPage(() => {
        const dialog = document.querySelector('#detail-dialog');
        const content = dialog.querySelector('.detail-content');
        return dialog.getBoundingClientRect().width < innerWidth && content.scrollWidth <= content.clientWidth;
    }));
    await closeDialog();
    assert.deepEqual(browserErrors, []);
    console.log('PASS privacy masking, empty states, 820px and 390px layouts, mobile dialog, and no browser console errors');
} finally {
    socket?.close();
    if (chrome.pid && chrome.exitCode === null) {
        const exited = new Promise(resolve => chrome.once('exit', resolve));
        chrome.kill();
        await exited;
    }
    if (path.dirname(scratch) === scratchRoot && path.basename(scratch).startsWith('acalytics-browser-')) {
        await rm(scratch, { recursive: true, force: true, maxRetries: 4, retryDelay: 150 }).catch(() => {});
    }
}
