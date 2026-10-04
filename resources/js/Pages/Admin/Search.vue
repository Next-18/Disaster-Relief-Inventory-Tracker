<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    term: { type: String, required: true },
    beneficiaries: { type: Array, required: true },
    inventoryItems: { type: Array, required: true },
    packages: { type: Array, required: true },
    distributions: { type: Array, required: true },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const groups = computed(() => [
    { title: 'Beneficiaries', key: 'beneficiaries', items: props.beneficiaries, url: routes.value.beneficiaries, param: 'search' },
    { title: 'Inventory', key: 'inventory', items: props.inventoryItems, url: routes.value.inventory, param: 'search' },
    { title: 'Relief packages', key: 'packages', items: props.packages, url: routes.value.packages, param: 'search' },
    { title: 'Distributions', key: 'distributions', items: props.distributions, url: routes.value.distribution, param: 'search' },
].map((group) => ({ ...group, url: `${group.url}?${group.param}=${encodeURIComponent(props.term)}` })));
const resultCount = computed(() => groups.value.reduce((total, group) => total + group.items.length, 0));

function description(group, item) {
    if (group.key === 'beneficiaries') return [item.beneficiary_no, item.status, item.priority_type, item.address || 'No address listed'].filter(Boolean).join(' · ');
    if (group.key === 'inventory') return [item.category, Number(item.quantity).toLocaleString() + ' ' + item.unit + ' in stock', item.status].filter(Boolean).join(' · ');
    if (group.key === 'packages') return `${item.category} · ${item.status}`;
    return `${formatDate(item.date_released)} · ${item.status}`;
}

function title(group, item) {
    if (group.key === 'beneficiaries') return item.full_name;
    if (group.key === 'inventory') return item.item_name;
    if (group.key === 'packages') return item.package_name;
    return `${item.beneficiary?.full_name || 'Unknown beneficiary'} · ${item.relief_package?.package_name || item.reliefPackage?.package_name || 'Unknown package'}`;
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}
</script>

<template>
    <section class="global-search-summary">
        <div><h2>Results for <span>“{{ term }}”</span></h2><p>{{ resultCount }} {{ resultCount === 1 ? 'match' : 'matches' }} found across your records. Up to 8 results are shown per section.</p></div>
        <Link class="action-btn" :href="routes.dashboard">Back to dashboard</Link>
    </section>

    <section v-if="!resultCount" class="panel global-search-empty">
        <span aria-hidden="true">⌕</span><h2>No matching records</h2><p>Check the spelling or try a beneficiary name, item, package, or distribution status.</p>
    </section>
    <div v-else class="global-search-groups">
        <section v-for="group in groups.filter((entry) => entry.items.length)" :key="group.key" class="panel global-search-group">
            <div class="global-search-group-heading"><h2>{{ group.title }}</h2><span>{{ group.items.length }}{{ group.items.length === 8 ? '+' : '' }}</span></div>
            <div class="global-search-results">
                <Link v-for="item in group.items" :key="item.id" class="global-search-result" :href="group.url">
                    <span class="global-search-result-main"><strong>{{ title(group, item) }}</strong><small>{{ description(group, item) }}</small></span>
                    <span class="global-search-result-arrow" aria-hidden="true">→</span>
                </Link>
            </div>
            <Link class="global-search-view-all" :href="group.url">View matching {{ group.title.toLowerCase() }} <span aria-hidden="true">→</span></Link>
        </section>
    </div>
</template>
