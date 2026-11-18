# ProSpective verification report

**Scope** — ProSpective for the BS Information Technology Program Chair, College of Informatics and Computing Sciences, Batangas State University – ARASOF-Nasugbu. This review uses Parts I–V of the supplied `BAT 405 Case Study.docx` and all 24 statements from the revised BAT 405 Interactive Analytics Dashboard Evaluation Questionnaire.

**Data basis** — The local prototype uses 144 deterministic aggregate class records across six semesters, with 24 classes per semester. The repository contains 12 subject codes, eight sections per semester, and eight assigned faculty in its catalog. Grade submission status uses the dataset snapshot dated June 25, 2026. Enrollment, grades, and SETE counts refer to student-subject records. A student can appear in multiple classes.

## Implementation review

| Requirement | Implementation and verification |
|---|---|
| 1. Grade submission calculation | The Grade Submission Rate uses on-time classes divided by required classes. All demo classes require submission. Monitoring and KPI details also show completed submissions, which include on-time and late classes. Pending represents an outstanding submission with time remaining. Overdue represents an outstanding submission past its deadline. |
| 2. Five formulas and thresholds | Shared definitions specify the exact Part II targets and critical thresholds in the reconciliation table. Calculations use underlying counts and unrounded results. Displays use one decimal for percentages and two for ratings. |
| 3. Consistent classification | KPI badges, chart legends and tooltips, table rates, the notification bell, and Decision Support use the same metric status rules. The 90% grade rate threshold applies once the scope's deadlines have passed. The overdue-class alert uses the status of each affected class. It can be Critical while the overall rate is Below Target. The late-only and pending cases have independent tests. |
| 4. KPI information | The existing card hover text and KPI details show the calculation and thresholds. The details show the supporting counts, and the dashboard shows the applied period. The additional info controls were removed at the user's request. |
| 5–7. Insights and source integrity | The additional Teaching and Academic Insights section was removed at the user's request. Existing charts use the centralized filtered records. The supplied sources establish a SETE instrument but provide neither verified dimension names nor dimension-level scores. |
| 8. SETE privacy | By default, a class needs five valid rating responses. Set `ACALYTICS_SETE_MIN_RESPONSES` to configure the cutoff. The prototype enforces a minimum setting of two to suppress one-response cells. Below-cutoff groups are excluded from score rollups and faculty comparisons. Masked scores and group rating response counts are withheld from JSON, the UI, and CSV. Participation totals still count submitted evaluations. |
| 9. Provenance and interpretation | Grade status and class counts are recorded or derived directly from dated submissions. KPI and comparison headings show calculated measures. Decision Support separates recommended actions from evidence and links to supporting classes. The SETE interpretation note explains its use alongside other evidence. |
| 10–11. Filters and interactions | Apply Filters updates all applicable sections and the historical comparison retains the selected cohort, subject, and faculty. Reset All restores the defaults. Search, sorting, and pagination affect the table. CSV exports all matching rows across pages. The browser checks below exercise each named control. |
| 12. Analytics model | The dashboard uses descriptive and diagnostic analytics. Part IV is documented below as a proposed model with an evaluation plan. |
| 13. Design | The ProSpective branding, Poppins typography, palette, five KPIs, Decision Support, three original charts, monitoring table, and compact dialogs are in place. |
| 14–15. Reconciliation and evaluation | All six periods and 1,607 valid filter combinations were tested against the centralized data. The full questionnaire matrix, results, and limitations follow. |

## Default-period reconciliation

**First Semester, A.Y. 2025–2026** | CICS BSIT, all year levels, 24 classes

| KPI | Calculation from the underlying records | Display | Target / critical threshold | Reconciled with |
|---|---|---:|---|---|
| SETE Completion Rate | 758 submitted ÷ 965 required × 100 = 78.5492228…% | **78.5%** | ≥90% / below 80% | Snapshot, sum of all four year groups, class SETE counts, evidence, CSV |
| Student Passing Rate | 850 passing ÷ 959 with final grades × 100 = 88.6339937…% | **88.6%** | ≥85% / below 75% | Year-level numerators and denominators, table, detail and CSV. The 6 missing grade records are outside the denominator. |
| Grade Submission Rate | 22 on time ÷ 24 required × 100 | **91.7%** | 100% / below 90% by the submission deadline | Grade status rows, drill-down, overdue class evidence, and CSV |
| Faculty Teaching Improvement | 6 faculty with strictly higher reportable weighted ratings ÷ 8 comparable faculty × 100 | **75.0%** | ≥70% / below 50% | Comparison with Second Semester, A.Y. 2024–2025, with the same filters and eligible faculty |
| Average Faculty Evaluation | 3,135.775 aggregate score points ÷ 758 valid responses in reportable classes = 4.1369063… | **4.14 / 5.0** | ≥4.0 / below 3.5 | Selected trend point, semester detail, weighted classes, and row-level rating detail where reportable |

**Rounding correction.** The request's example of 758 out of 965 is 78.5492228…%. Direct rounding to one decimal gives 78.5%. Rounding to 78.55 first and then to one decimal yields the conflicting 78.6%.

| Year level | SETE submitted / required | SETE completion | Passed / final grade records | Passing rate | Grade submissions on time / completed / required |
|---|---:|---:|---:|---:|---:|
| First | 225 / 250 | 90.0% | 233 / 248 | 94.0% | 6 / 6 / 6 |
| Second | 190 / 230 | 82.6% | 202 / 228 | 88.6% | 6 / 6 / 6 |
| Third | 166 / 244 | 68.0% | 196 / 244 | 80.3% | 6 / 6 / 6 |
| Fourth | 177 / 241 | 73.4% | 219 / 239 | 91.6% | 4 / 5 / 6 |
| Total | 758 / 965 | **78.5%** | 850 / 959 | **88.6%** | 22 / 23 / 24 |

The default grade snapshot has 22 on time, 1 late, 0 pending, and 1 overdue. Overall completion is 23 ÷ 24, or **95.8%**. The on-time KPI is **Below Target** at 91.7%. The overdue alert is **Critical** and links to BSIT 4B, IT 413. The faculty comparison has **6 improved, 0 unchanged, and 2 declined**. In the First Year drill-down for BSIT 1A, IT 111, 39 of 42 graded records passed (92.9%) and 38 of 44 required evaluations were submitted (86.4%).

### All available periods

| Academic year and semester | SETE submitted / required | Passed / graded | Grades on time / completed / required | Improved / unchanged / declined | Reportable rating responses | Selected trend rating |
|---|---:|---:|---:|---:|---:|---:|
| 2023–2024 First | 704 / 949 | 813 / 943 | 22 / 23 / 24 | N/A | 704 | 4.01 |
| 2023–2024 Second | 721 / 953 | 819 / 947 | 22 / 23 / 24 | 6 / 0 / 2 | 721 | 4.04 |
| 2024–2025 First | 733 / 957 | 828 / 951 | 22 / 23 / 24 | 6 / 0 / 2 | 733 | 4.07 |
| 2024–2025 Second | 745 / 961 | 840 / 955 | 22 / 23 / 24 | 6 / 0 / 2 | 745 | 4.10 |
| 2025–2026 First | 758 / 965 | 850 / 959 | 22 / 23 / 24 | 6 / 0 / 2 | 758 | 4.14 |
| 2025–2026 Second | 706 / 969 | 825 / 925 | 22 / 23 / 24 | 6 / 0 / 2 | 702 | 4.17 |

The earliest period has no prior comparison. Second Semester 2025–2026 includes a masked low-response test case in BSIT 4B, IT 412. Its class view and CSV show N/A for the rating and rating response count. Participation includes its submitted evaluations.

## BAT 405 criteria matrix

The matrix records engineering verification and the features available for evaluation. The questionnaire's Likert scores are for the evaluator to assign.

| Criterion ID | Evaluation Statement | Corresponding Feature | Component Location | Implementation Status | Testing Evidence |
|---|---|---|---|---|---|
| A1 | The dashboard clearly communicates its purpose and intended users. | Academic Analytics Overview and Program Chair identity | Header and banner | Implemented | Dashboard render and Program Chair control |
| A2 | Important academic and teaching-effectiveness information is easy to locate. | Five KPI cards and prioritized insights above trends and class records | Filters, KPIs, Decision Support, charts, table | Implemented | All five KPI cards and three original charts rendered. Other Alerts exposes secondary findings. |
| A3 | Information is arranged logically and is easy to understand. | Reporting scope, KPIs, priorities, comparisons, and class evidence | Dashboard section order | Implemented | Dashboard renders without page-width overflow at 1440, 820, and 390 px. |
| A4 | The dashboard clearly distinguishes recorded information from analytical insights. | Recorded class activity, calculated rates, and labelled recommended actions | Monitoring table, View Details, Decision Support | Implemented | Class details and evidence reconcile to the scope. Recorded completion and on-time KPI values differ as expected. |
| B1 | The five KPI cards are relevant to academic operations and teaching effectiveness. | Completion, passing, submission timeliness, faculty comparison, and weighted SETE | KPI grid | Implemented | All five formulas reconciled across 1,607 filter combinations and six periods. |
| B2 | Each KPI is clearly labeled and understandable. | Titles, formulas, and supporting counts | Five KPI cards and their details | Implemented | Verified all five drill-down dialogs. |
| B3 | KPI targets, reporting periods, or thresholds are shown when needed. | Applied period, targets, warning cutoffs, and prior comparison period | Filter bar, KPI tooltips, chart legends | Implemented | Exact threshold boundary tests for every metric. Combined filters update the report period. |
| B4 | The KPIs help identify areas that may require attention or improvement. | Meets Target, Below Target, Critical, or N/A status | KPI badges, supporting alerts | Implemented | Default grade KPI is Below Target at 91.7%. A late-only scope evaluates as Critical even without an overdue class. |
| C1 | The dashboard includes at least three appropriate data visualizations. | Historical line, passing bars, and completion bars | Explore the Bigger Picture | Implemented | Three charts render in the default view. |
| C2 | Charts have clear titles, labels, units, and legends when needed. | Rating scale, percent axes, and shared status labels | Three chart cards | Implemented | Verified actual canvas hover for historical, passing, and completion charts. |
| C3 | Visualizations make trends and comparisons easy to understand. | Chronological periods, selected point, year-level comparisons | SETE Rating Trends and year-level charts | Implemented | Selected point reconciles to KPI, all six labels are chronological |
| C4 | The dashboard is readable and avoids unnecessary clutter or misleading displays. | Poppins, responsive chart cards, contextual alerts, N/A handling | Dashboard and empty states | Implemented | Layout checks at desktop, tablet, and phone sizes, unavailable-value tests |
| D1 | At least two interactive features (such as filters, drill-down, or tooltips) are clearly presented. | Filters, KPI drill-down, chart hover, monitoring search and details | Filter card, plots, and table | Implemented | Browser interaction checks and endpoint tests |
| D2 | The purpose of each interactive feature is easy to understand. | Labelled inputs, Apply Filters, Reset All, Export CSV, and labelled eye buttons | Filter, chart and monitoring controls | Implemented | Control activation and accessible labels checked |
| D3 | The interactive features are appropriate for the intended users’ tasks. | Section and faculty filters, class evidence, grade status, and export | Scope controls, insights, monitoring | Implemented | Combined scope, matching rows, and CSV results |
| D4 | When demonstrated, interactive controls update the relevant information correctly. | Academic filters update scoped panels. Search updates the table. Year selection applies a filter. | Dashboard and monitoring state | Implemented | Combined filters, dependent options, reset, search, and chart selection exercised. |
| E1 | The dashboard includes at least one understandable warning or alert. | Scoped, data-driven alerts | Notification bell and Decision Support | Implemented | The default period has seven alerts, accessible from the bell and Decision Support. |
| E2 | The alert clearly indicates the condition requiring attention. | Rate thresholds and operational submission conditions are identified by scope and severity | Alert card and evidence dialog | Implemented | Checked the Below Target grade KPI and opened the evidence for the separate one-class overdue alert. |
| E3 | The dashboard helps users identify possible academic or teaching-improvement actions. | Context-specific follow-up for participation, grades, and academic support | Recommended action on alert cards and dialogs | Implemented | Actions render and evidence opens for affected classes |
| E4 | Results provide sufficient context to avoid unsupported conclusions. | Counts, current and prior reporting periods, participation, interpretation note | KPI details, trend details, and Snapshot | Implemented | Formula and scope context, weighted denominators and comparability assertions |
| F1 | The dashboard avoids unnecessarily exposing confidential student or faculty information. | Aggregate demo records and configurable small-group masking | JSON, View Details, charts, summaries, tooltips, alerts, CSV | Implemented for prototype | Default and 39-response cutoffs verified. Small-group scores and valid response counts are absent from published outputs. |
| F2 | SETE summaries include useful context, such as response rates or evaluation periods. | Valid response counts, submitted/required totals, 1–5 rating scale, and period labels | Trends, semester details, Snapshot | Implemented | Selected point, 758-response tooltip, and participation calculations checked |
| F3 | The dashboard does not present SETE scores as the sole measure of teaching effectiveness. | Exact interpretation note and qualified historical comparisons | SETE completion card and faculty comparison | Implemented | Required note appears in the rendered view |
| F4 | The information is presented in a fair and responsible manner. | Same-scope comparisons, response weighting, privacy, and explicit unavailable results | SETE details and prototype footer | Implemented for prototype | Underlying precision, low-response, and no-prior-period cases verified. Institutional validation remains pending. |

## Part IV model selection and application

The live dashboard uses **descriptive analytics** for measured rates and trends and **diagnostic drill-down** to identify the records behind alerts. Its recommended actions are rule-based. Historical data across six demo semesters supports testing the calculations.

**Proposed model.** Evaluate whether a regularized logistic regression classifier could help the Program Chair prioritize reminders for classes likely to miss their grade submission deadline. The decision point is seven days before each deadline. The proposed output is a calibrated probability of missing that deadline and a reminder-priority flag, to be evaluated using authorized historical snapshots. Candidate inputs at the decision point include prior on-time rate for comparable classes, assigned class workload, section enrollment, program year level, and the presence of a draft submission if the portal records it. Use only information available at the decision point. This proposal uses operational grade submission data.

1. Define the reminder decision, eligible classes, prediction time, and missed-deadline outcome.
2. Collect institution-authorized submission history, due dates, and operational inputs with original timestamps.
3. Reconcile status, missing dates, and changes to requirements. Create point-in-time features and preserve missingness.
4. Split semesters chronologically. Keep a later semester untouched for final testing and check whether a few faculty or sections dominate any split.
5. Fit a simple rules baseline and regularized logistic regression on earlier semesters.
6. Select the reminder threshold on validation semesters and check calibration and differences across relevant groups.
7. Evaluate the final holdout and review errors with the Program Chair. Assess whether reminders improve follow-up in a controlled pilot before live use.

**Evaluation metrics.** Precision measures how many flagged classes actually missed the deadline and helps gauge unnecessary reminders. Recall measures how many actual missed deadlines the model caught and helps assess missed follow-up. Both must be evaluated at the proposed reminder threshold.

There are no trained or validated model outputs in ProSpective. Any later use of SETE in a model would require analysis of participation bias, small groups, class context, and instrument changes. SETE feedback and model output should support human review alongside other evidence for personnel decisions.

## Parts I, III, and V scope

The case study describes existing portal functions such as enrollment, grades, and schedules, and a SETE process that collects evaluations. ProSpective demonstrates the proposed Program Chair view from aggregate class records. It includes five KPIs, three charts, multiple interactive features, and actionable alerts. Live data integration, verified SETE mapping, and institutional access control remain production work.

Before institutional use, repeat functional and usability testing with Program Chairs, reconcile authoritative portal and SETE aggregates, and test privacy and access controls with authorized role and disclosure scenarios. Two supported workflows are prioritizing reminders for late or overdue grade submissions and using SETE participation and academic evidence to choose areas for teaching support. Decisions about teaching effectiveness require interpretation in academic context.

## Reproduce the validation

```powershell
npm.cmd run build
php artisan test
node tests/Browser/academic-dashboard.mjs
```

The browser test uses Node's WebSocket support and a fresh headless Chrome profile. The default URL is `http://127.0.0.1:8000`. Set `ACALYTICS_BASE_URL` for another local port and `ACALYTICS_CHROME` if Chrome is installed elsewhere.

## Actual validation results

| Check | Result |
|---|---|
| Vite production build | Passed. Live Laravel served the rebuilt styling and JavaScript. |
| Laravel test suite | **18 tests passed, 20,135 assertions.** All 144 class records across six semesters and all 1,607 valid filter combinations reconciled to counts, rates, weighted formulas, and faculty comparisons. Additional checks covered exact thresholds, same-day grade deadlines, late-only status, configurable suppression, CSV, and missing data. |
| Headless Chrome on Windows | **Seven browser test groups passed.** Verified chart hover, KPI dialogs, semester and class details, Explore Participation, notification bell, Other Alerts, View Full Analysis, Apply Filters, Reset All, year-level selection, search, sorting, pagination, page sizes, CSV download, and the eye dialog's scroll stability. |
| Responsive rendering | Browser checks passed at desktop 1440 px, tablet 820 px, and phone 390 px. On narrow viewports the table scrolls within its card and the dialog fits the viewport. |
| Browser error check | No uncaught runtime exceptions or JavaScript console errors in the test run. |

Isolated Chrome required permission to run outside the shell sandbox after Windows denied access to its GPU subprocess. The successful browser run used a temporary local profile.

## Remaining limits

- The dashboard uses demonstration aggregates. Connecting institutional data and enforcing Program Chair authorization require production integration and verification.
- Official SETE evaluation dimension labels and scores were absent from the supplied materials and repository.
- The thresholds are case-study rules. Institutional suitability and production disclosure policies require program review.
- SETE ratings provide one participation-dependent source of evidence. The faculty comparison indicates a change in the rating, while a causal finding about teaching effectiveness requires additional evidence.
- The current grade-state calculation uses a date-level snapshot. The demo data does not provide time-of-day or timezone-specific submission deadlines.
- Browser verification covers Chromium at the listed viewport sizes. Firefox, Safari, and formal assistive-technology evaluation are outside this test run.
