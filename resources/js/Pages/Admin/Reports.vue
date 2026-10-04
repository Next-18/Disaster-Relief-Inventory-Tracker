<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    distributions: { type: Object, required: true },
    startDate: { type: String, default: null },
    endDate: { type: String, default: null },
    status: { type: String, required: true },
    periodLabel: { type: String, required: true },
    totalDistributions: { type: Number, required: true },
    releasedCount: { type: Number, required: true },
    pendingCount: { type: Number, required: true },
    releaseRate: { type: Number, required: true },
    monthlyTrend: { type: Object, required: true },
    chartMaxCount: { type: Number, required: true },
    chartHasActivity: { type: Boolean, required: true },
    chartPeriodLabel: { type: String, required: true },
    inventoryItems: { type: Object, required: true },
    inventoryRecordCount: { type: Number, required: true },
    totalUnits: { type: Number, required: true },
    lowStockItems: { type: Number, required: true },
    healthyStockItems: { type: Number, required: true },
    lowStockShare: { type: Number, required: true },
    totalBeneficiaries: { type: Number, required: true },
    activeBeneficiaries: { type: Number, required: true },
    inactiveBeneficiaries: { type: Number, required: true },
    qrGeneratedCount: { type: Number, required: true },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const reportErrors = computed(() => page.props.errors ?? {});
const filters = reactive({ start_date: props.startDate ?? '', end_date: props.endDate ?? '', status: props.status ?? 'all' });
watch(() => [props.startDate, props.endDate, props.status], ([startDate, endDate, status]) => {
    Object.assign(filters, { start_date: startDate ?? '', end_date: endDate ?? '', status: status ?? 'all' });
});
const filterDisclosure = ref(null);
const months = computed(() => Object.values(props.monthlyTrend ?? {}));
const hasOther = computed(() => months.value.some((month) => month.other > 0));

function queryString(status = filters.status) {
    const params = new URLSearchParams();
    if (filters.start_date) params.set('start_date', filters.start_date);
    if (filters.end_date) params.set('end_date', filters.end_date);
    if (status) params.set('status', status);
    return params.toString();
}

const exportUrl = computed(() => `${routes.value.reportExport}${queryString() ? `?${queryString()}` : ''}`);

function applyFilters() {
    router.get(routes.value.reports, {
        start_date: filters.start_date || undefined,
        end_date: filters.end_date || undefined,
        status: filters.status,
    }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    Object.assign(filters, { start_date: '', end_date: '', status: 'all' });
    router.get(routes.value.reports, { status: 'all' }, { preserveState: true, preserveScroll: true });
}

function barHeight(value) {
    return value > 0 ? Math.max(1, Math.round((value / Math.max(1, props.chartMaxCount)) * 132)) : 0;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' }) : '—';
}

function printReport() {
    window.print();
}

function outsideClick(event) {
    if (filterDisclosure.value?.open && !filterDisclosure.value.contains(event.target)) filterDisclosure.value.open = false;
}

function onKeydown(event) {
    if (event.key === 'Escape' && filterDisclosure.value?.open) {
        filterDisclosure.value.open = false;
        filterDisclosure.value.querySelector('summary')?.focus();
    }
}

onMounted(() => {
    document.addEventListener('click', outsideClick);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', outsideClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <section class="reports-heading">
        <div><h2>Reports</h2><p>Review distributions for a selected period and check current stock and registry totals.</p></div>
        <div class="report-actions no-print">
            <button class="action-btn" type="button" @click="printReport">Print report</button>
            <a class="action-btn report-export" :href="exportUrl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/></svg>Export CSV</a>
            <details ref="filterDisclosure" class="report-filter-disclosure" :open="Object.keys(reportErrors).length > 0">
                <summary class="report-filter-trigger" aria-label="Report filters" title="Report filters"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg></summary>
                <section class="panel report-filter-panel report-filter-popover" aria-labelledby="report-filter-title">
                    <div class="report-filter-heading"><div><h2 id="report-filter-title">Distribution report</h2><p>Choose a date range and optional status. Summary totals include both statuses.</p></div><span class="report-period-chip">{{ periodLabel }}</span></div>
                    <form class="report-filter-form" @submit.prevent="applyFilters">
                        <div class="report-filter-field"><label for="report-start-date">Start date</label><input id="report-start-date" v-model="filters.start_date" type="date" @change="filters.end_date && filters.end_date < filters.start_date ? filters.end_date = filters.start_date : null"></div>
                        <div class="report-filter-field"><label for="report-end-date">End date</label><input id="report-end-date" v-model="filters.end_date" type="date" :min="filters.start_date"></div>
                        <div class="report-filter-field report-status-field"><label for="report-status">Table status</label><select id="report-status" v-model="filters.status"><option value="all">All statuses</option><option value="Released">Released</option><option value="Pending">Pending</option></select></div>
                        <div class="report-filter-actions"><button class="primary-action" type="submit">Apply filters</button><button v-if="startDate || endDate || status !== 'all'" class="filter-clear" type="button" @click="clearFilters">Clear filters</button></div>
                    </form>
                </section>
            </details>
        </div>
    </section>

    <div v-if="Object.keys(reportErrors).length" class="report-validation-errors" role="alert"><strong>Check the report filters:</strong><ul><li v-for="(message, key) in reportErrors" :key="key">{{ message }}</li></ul></div>

    <section class="report-section" aria-labelledby="distribution-summary-title">
        <div class="report-section-heading"><div><h2 id="distribution-summary-title">Distribution summary</h2><p>Distribution activity for {{ periodLabel }}.</p></div><span class="report-period-chip">{{ Number(totalDistributions).toLocaleString() }} total</span></div>
        <div class="metrics report-metrics">
            <div class="metric-card"><div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div><div><small>All distributions</small><b>{{ Number(totalDistributions).toLocaleString() }}</b></div></div>
            <div class="metric-card green"><div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg></div><div><small>Released</small><b>{{ Number(releasedCount).toLocaleString() }}</b></div></div>
            <div class="metric-card orange"><div class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><div><small>Pending</small><b>{{ Number(pendingCount).toLocaleString() }}</b></div></div>
            <div class="metric-card"><div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 15l3-4 3 2 5-7"/></svg></div><div><small>Release rate</small><b>{{ Number(releaseRate).toFixed(1) }}%</b></div></div>
        </div>
    </section>

    <section class="report-chart-grid" aria-label="Distribution and inventory charts">
        <article class="panel report-chart-card" aria-labelledby="monthly-trend-title">
            <div class="report-chart-heading"><div><h2 id="monthly-trend-title">Monthly distribution trend</h2><p>{{ chartPeriodLabel }}. Bars show distribution counts by status.</p></div><div class="report-chart-legend"><span><i class="legend-released"/>Released</span><span><i class="legend-pending"/>Pending</span><span v-if="hasOther"><i class="legend-other"/>Other</span></div></div>
            <div v-if="chartHasActivity" class="report-trend-chart" role="group" aria-label="Monthly distribution totals">
                <div v-for="month in months" :key="month.label" class="report-trend-column" role="img" :aria-label="`${month.label}: ${month.total} total, ${month.released} released, ${month.pending} pending, ${month.other} other`">
                    <span class="report-chart-total">{{ month.total }}</span><div class="report-chart-plot"><div class="report-chart-stack" :title="`${month.total} distributions`"><span class="chart-segment chart-released" :style="{ height: `${barHeight(month.released)}px` }"/><span class="chart-segment chart-pending" :style="{ height: `${barHeight(month.pending)}px` }"/><span class="chart-segment chart-other" :style="{ height: `${barHeight(month.other)}px` }"/></div></div><span class="report-chart-month">{{ month.label }}</span>
                </div>
            </div>
            <div v-else class="report-chart-empty">No distribution activity in the months shown.</div>
        </article>
        <article class="panel report-chart-card report-stock-health" aria-labelledby="stock-health-title">
            <div class="report-chart-heading"><div><h2 id="stock-health-title">Current stock health</h2><p>Items at or below their minimum stock level.</p></div></div>
            <div class="stock-health-content"><div class="stock-health-donut" :class="{ 'is-empty': inventoryRecordCount === 0 }" :style="{ '--low-stock-share': `${lowStockShare}%` }" role="img" :aria-label="inventoryRecordCount === 0 ? 'No stock records' : `${lowStockItems} low stock records and ${healthyStockItems} above minimum`"><div class="stock-health-hole"><strong>{{ Number(lowStockItems).toLocaleString() }}</strong><span>low stock</span></div></div><div class="stock-health-legend"><div><i class="legend-healthy"/><span>Above minimum</span><strong>{{ Number(healthyStockItems).toLocaleString() }}</strong></div><div><i class="legend-low-stock"/><span>Low stock</span><strong>{{ Number(lowStockItems).toLocaleString() }}</strong></div><p>{{ Number(inventoryRecordCount).toLocaleString() }} total stock records</p></div></div>
        </article>
    </section>

    <section class="panel record-panel report-distributions" aria-labelledby="distribution-records-title">
        <div class="panel-heading"><div><h2 id="distribution-records-title">Distribution records</h2><p>{{ distributions.total ? `Showing ${distributions.from}–${distributions.to} of ${Number(distributions.total).toLocaleString()} matching records` : 'No records match the selected filters' }}</p></div><span class="report-period-chip">Table: {{ status === 'all' ? 'All statuses' : status }}</span></div>
        <div class="table-wrap"><table class="record-table report-table"><thead><tr><th>RECORD</th><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>STATUS</th><th>RECORDED BY</th></tr></thead><tbody>
            <tr v-for="distribution in distributions.data" :key="distribution.id"><td><b>#{{ distribution.id }}</b></td><td><b>{{ distribution.beneficiary?.full_name || 'Unknown beneficiary' }}</b><small>{{ distribution.beneficiary?.beneficiary_no || 'No beneficiary number' }}</small></td><td>{{ distribution.relief_package?.package_name || distribution.reliefPackage?.package_name || 'Unknown package' }}</td><td>{{ formatDate(distribution.date_released) }}</td><td><span class="tag" :class="distribution.status === 'Released' ? 'success' : 'warning'">{{ distribution.status }}</span></td><td>{{ distribution.distributor?.name || '—' }}</td></tr>
            <tr v-if="!distributions.data.length"><td colspan="6" class="empty-cell">No distributions found. Adjust the filters or clear them to see more records.</td></tr>
        </tbody></table></div>
        <InertiaPagination v-if="distributions.last_page > 1" class="no-print" :links="distributions.links" :show="true" />
    </section>

    <section class="report-section report-inventory-section">
        <div class="report-section-heading"><div><h2 id="inventory-snapshot-title">Current inventory snapshot</h2><p>Live stock levels; inventory totals are not limited by the distribution date filter.</p></div><div class="report-stock-actions no-print"><a class="action-btn report-export" :href="routes.inventoryReportExport">Export stock CSV</a><Link class="action-btn" :href="routes.inventory + '?stock_status=low'">Review low stock</Link></div></div>
        <div class="report-mini-metrics"><div><small>Stock records</small><b>{{ Number(inventoryRecordCount).toLocaleString() }}</b></div><div><small>Total units on hand</small><b>{{ Number(totalUnits).toLocaleString() }}</b></div><div :class="lowStockItems > 0 ? 'is-alert' : 'is-healthy'"><small>Low stock records</small><b>{{ Number(lowStockItems).toLocaleString() }}</b></div></div>
        <section class="panel record-panel report-stock-table"><div class="panel-heading"><div><h3>Stock by item</h3><p>{{ inventoryItems.total ? `Showing ${inventoryItems.from}–${inventoryItems.to} of ${Number(inventoryItems.total).toLocaleString()} stock records` : 'No inventory records yet' }}</p></div></div><div class="table-wrap"><table class="record-table report-table"><thead><tr><th>ITEM</th><th>CATEGORY</th><th>ON HAND</th><th>MINIMUM</th><th>STATUS</th></tr></thead><tbody>
            <tr v-for="item in inventoryItems.data" :key="item.id"><td><b>{{ item.item_name }}</b></td><td>{{ item.category }}</td><td>{{ Number(item.quantity).toLocaleString() }} {{ item.unit }}</td><td>{{ Number(item.minimum_stock).toLocaleString() }} {{ item.unit }}</td><td><span class="tag" :class="item.status === 'Low Stock' ? 'warning' : 'success'">{{ item.status }}</span></td></tr><tr v-if="!inventoryItems.data.length"><td colspan="5" class="empty-cell">No inventory records to report.</td></tr>
        </tbody></table></div><InertiaPagination v-if="inventoryItems.last_page > 1" class="no-print" :links="inventoryItems.links" :show="true" /></section>
    </section>

    <section class="panel report-registry"><div class="report-section-heading"><div><h2 id="registry-summary-title">Current beneficiary registry</h2><p>Current totals, independent of the selected distribution period.</p></div></div><div class="report-mini-metrics report-registry-metrics"><div><small>Registered beneficiaries</small><b>{{ Number(totalBeneficiaries).toLocaleString() }}</b></div><div class="is-healthy"><small>Active beneficiaries</small><b>{{ Number(activeBeneficiaries).toLocaleString() }}</b></div><div><small>Inactive beneficiaries</small><b>{{ Number(inactiveBeneficiaries).toLocaleString() }}</b></div><div><small>QR codes generated</small><b>{{ Number(qrGeneratedCount).toLocaleString() }}</b></div></div></section>
</template>
