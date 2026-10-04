<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    items: { type: Object, required: true },
    totalInventoryItems: { type: Number, required: true },
    lowStockCount: { type: Number, required: true },
    categoryCount: { type: Number, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({
    search: props.filters.search ?? '',
    stock_status: props.filters.stock_status ?? 'all',
    per_page: Number(props.filters.per_page ?? 10),
});
const modal = ref(null);
const editingId = ref(null);
const form = useForm({ item_name: '', category: '', quantity: 0, unit: 'pcs', minimum_stock: 0 });
let searchTimer;
let lastSubmittedSearch = filters.search.trim();

const rows = computed(() => props.items.data ?? []);

function visitFilters({ replace = false } = {}) {
    lastSubmittedSearch = filters.search.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.inventory, {
        search: filters.search.trim() || undefined,
        stock_status: filters.stock_status === 'all' ? undefined : filters.stock_status,
        per_page: filters.per_page,
        page: 1,
    }, { preserveState: true, preserveScroll: true, replace });
}

watch(() => filters.search, () => {
    if (filters.search.trim() === lastSubmittedSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitFilters({ replace: true }), 350);
});

watch(() => props.filters, (next) => {
    lastSubmittedSearch = (next.search ?? '').trim();
    filters.search = next.search ?? '';
    filters.stock_status = next.stock_status ?? 'all';
    filters.per_page = Number(next.per_page ?? 10);
}, { deep: true });

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('action') === 'add') {
        openAddModal();
        const url = new URL(window.location.href);
        url.searchParams.delete('action');
        window.history.replaceState(window.history.state, '', url);
    }
});
onBeforeUnmount(() => clearTimeout(searchTimer));

function openAddModal() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    modal.value?.showModal();
}

function openEditModal(item) {
    editingId.value = item.id;
    form.clearErrors();
    form.item_name = item.item_name;
    form.category = item.category;
    form.quantity = item.quantity;
    form.unit = item.unit;
    form.minimum_stock = item.minimum_stock;
    modal.value?.showModal();
}

function saveItem() {
    const options = { preserveScroll: true, onSuccess: () => { modal.value?.close(); form.reset(); editingId.value = null; } };
    if (editingId.value) form.put(routes.value.inventoryUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.inventoryStore, options);
}

function deleteItem(item) {
    if (!window.confirm(`Delete “${item.item_name}”? This cannot be undone.`)) return;
    router.delete(routes.value.inventoryDelete.replace('__ID__', encodeURIComponent(item.id)), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Relief inventory</h2><p>Monitor available supplies and minimum stock levels.</p></div>
        <button class="add-button" type="button" @click="openAddModal"><span>+</span> Add item</button>
    </section>

    <div class="inventory-summary"><div><small>Stock records</small><b>{{ Number(totalInventoryItems).toLocaleString() }}</b></div><div><small>Low stock records</small><b>{{ Number(lowStockCount).toLocaleString() }}</b></div><div><small>Categories</small><b>{{ Number(categoryCount).toLocaleString() }}</b></div></div>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Stock list</h3><p>{{ items.total ? `Showing ${items.from}–${items.to} of ${items.total} matching records` : 'No records to display' }}</p></div>
            <form class="inventory-filter-form" role="search" @submit.prevent="visitFilters()">
                <input v-model="filters.search" type="search" class="table-search inventory-search" placeholder="Search name, category, unit" aria-label="Search inventory">
                <select v-model="filters.stock_status" class="filter-select inventory-select" aria-label="Filter by stock status" @change="visitFilters()"><option value="all">All stock</option><option value="low">Low stock</option><option value="available">Above minimum</option></select>
                <select v-model.number="filters.per_page" class="filter-select inventory-select" aria-label="Rows per page" @change="visitFilters()"><option :value="10">10 / page</option><option :value="25">25 / page</option><option :value="50">50 / page</option></select>
                <button type="submit" class="action-btn">Search</button>
                <button v-if="filters.search || filters.stock_status !== 'all'" type="button" class="filter-clear" @click="filters.search = ''; filters.stock_status = 'all'; visitFilters()">Clear</button>
            </form>
        </div>
        <div class="table-wrap"><table class="record-table">
            <thead><tr><th>ITEM</th><th>CATEGORY</th><th>AVAILABLE STOCK</th><th>MINIMUM</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
            <tbody>
                <tr v-for="item in rows" :key="item.id" :class="{ 'inventory-row-low': item.status === 'Low Stock' }">
                    <td><b>{{ item.item_name }}</b><small>Last updated {{ formatDate(item.updated_at) }}</small></td>
                    <td>{{ item.category }}</td>
                    <td><b>{{ item.quantity }}</b> {{ item.unit }}</td>
                    <td>{{ item.minimum_stock }} {{ item.unit }}</td>
                    <td><span class="tag" :class="item.status === 'Low Stock' ? 'warning' : 'success'">{{ item.status }}</span></td>
                    <td><div class="inventory-row-actions"><button type="button" class="action-btn" @click="openEditModal(item)">Edit</button><button type="button" class="action-btn delete" @click="deleteItem(item)">Delete</button></div></td>
                </tr>
                <tr v-if="!rows.length"><td colspan="6" class="empty-cell">{{ filters.search || filters.stock_status !== 'all' ? 'No inventory items match these filters.' : 'No inventory items yet.' }} <button v-if="filters.search || filters.stock_status !== 'all'" type="button" class="filter-clear" @click="filters.search = ''; filters.stock_status = 'all'; visitFilters()">Clear filters</button></td></tr>
            </tbody>
        </table></div>
        <InertiaPagination :links="items.links" :show="items.last_page > 1" />
    </section>

    <AdminModal ref="modal" class="form-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit inventory item' : 'Add inventory item' }}</h3><p>{{ editingId ? 'Update inventory item information.' : 'Enter a supply item and its stock level.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="saveItem">
            <label>Item name<input v-model="form.item_name" maxlength="255" placeholder="e.g. Rice" required :class="{ 'input-error': form.errors.item_name }"><small v-if="form.errors.item_name" class="field-error">{{ form.errors.item_name }}</small></label>
            <label>Category<input v-model="form.category" maxlength="60" placeholder="e.g. Food supplies" required><small v-if="form.errors.category" class="field-error">{{ form.errors.category }}</small></label>
            <div class="form-row"><label>Quantity<input v-model.number="form.quantity" type="number" min="0" max="4294967295" step="1" required><small v-if="form.errors.quantity" class="field-error">{{ form.errors.quantity }}</small></label><label>Unit<input v-model="form.unit" maxlength="30" placeholder="e.g. bags" required><small v-if="form.errors.unit" class="field-error">{{ form.errors.unit }}</small></label></div>
            <label>Minimum stock level<input v-model.number="form.minimum_stock" type="number" min="0" max="4294967295" step="1" required><small v-if="form.errors.minimum_stock" class="field-error">{{ form.errors.minimum_stock }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : editingId ? 'Update item' : 'Save item' }}</button></div>
        </form>
    </AdminModal>
</template>
