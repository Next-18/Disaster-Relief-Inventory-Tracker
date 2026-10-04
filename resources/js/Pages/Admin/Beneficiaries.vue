<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    beneficiaries: { type: Object, required: true },
    totalBeneficiaries: { type: Number, required: true },
    activeBeneficiaries: { type: Number, required: true },
    inactiveBeneficiaries: { type: Number, required: true },
    priorityHouseholds: { type: Number, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    priority_type: props.filters.priority_type ?? 'all',
    priority_only: props.filters.priority_only === true || props.filters.priority_only === '1' || props.filters.priority_only === 1,
    per_page: Number(props.filters.per_page ?? 10),
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
});
const selectedIds = ref([]);
const rowMenuId = ref(null);
const bulkMenuOpen = ref(false);
const beneficiaryModal = ref(null);
const viewModal = ref(null);
const qrModal = ref(null);
const viewedBeneficiary = ref(null);
const qrBeneficiary = ref(null);
const editingId = ref(null);
const form = useForm({ full_name: '', contact_number: '', address: '', household_size: '', priority_type: 'Regular', status: 'Active' });
const bulkForm = useForm({ ids: [], status: 'Active' });
let searchTimer;
let lastSubmittedSearch = filters.search.trim();

const rows = computed(() => props.beneficiaries.data ?? []);
const allSelected = computed(() => rows.value.length > 0 && rows.value.every((row) => selectedIds.value.includes(row.id)));
const activeFilters = computed(() => Boolean(filters.search || filters.status !== 'all' || filters.priority_type !== 'all' || filters.priority_only));
const exportHref = computed(() => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(buildQuery())) {
        if (value !== '' && value !== false && value != null) params.set(key, String(value));
    }
    return `${routes.value.beneficiariesExport}${params.size ? `?${params}` : ''}`;
});

function buildQuery() {
    return {
        search: filters.search.trim(),
        status: filters.status,
        priority_type: filters.priority_type,
        priority_only: filters.priority_only ? 1 : undefined,
        per_page: filters.per_page,
        sort: filters.sort,
        direction: filters.direction,
    };
}

function visitFilters({ replace = false } = {}) {
    lastSubmittedSearch = filters.search.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.beneficiaries, { ...buildQuery(), page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        replace,
    });
}

watch(() => filters.search, () => {
    if (filters.search.trim() === lastSubmittedSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitFilters({ replace: true }), 350);
});

watch(() => props.filters, (next) => {
    lastSubmittedSearch = (next.search ?? '').trim();
    Object.assign(filters, {
        search: next.search ?? '',
        status: next.status ?? 'all',
        priority_type: next.priority_type ?? 'all',
        priority_only: next.priority_only === true || next.priority_only === '1' || next.priority_only === 1,
        per_page: Number(next.per_page ?? 10),
        sort: next.sort ?? 'created_at',
        direction: next.direction ?? 'desc',
    });
}, { deep: true });

onMounted(() => {
    document.addEventListener('click', closeActionMenus);
    document.addEventListener('keydown', closeActionMenusOnEscape);
    if (new URLSearchParams(window.location.search).get('action') === 'add') {
        openAddModal();
        const url = new URL(window.location.href);
        url.searchParams.delete('action');
        window.history.replaceState(window.history.state, '', url);
    }
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    document.removeEventListener('click', closeActionMenus);
    document.removeEventListener('keydown', closeActionMenusOnEscape);
});

function closeActionMenus(event) {
    if (!event.target.closest?.('.beneficiary-bulk-actions')) bulkMenuOpen.value = false;
    if (!event.target.closest?.('.row-actions-dropdown')) rowMenuId.value = null;
}

function closeActionMenusOnEscape(event) {
    if (event.key === 'Escape') {
        bulkMenuOpen.value = false;
        rowMenuId.value = null;
    }
}

function openAddModal() {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    beneficiaryModal.value?.showModal();
}

function openEditModal(beneficiary) {
    form.clearErrors();
    form.full_name = beneficiary.full_name ?? '';
    form.contact_number = beneficiary.contact_number ?? '';
    form.address = beneficiary.address ?? '';
    form.household_size = beneficiary.household_size ?? '';
    form.priority_type = beneficiary.priority_type ?? 'Regular';
    form.status = beneficiary.status ?? 'Active';
    editingId.value = beneficiary.id;
    beneficiaryModal.value?.showModal();
}

function saveBeneficiary() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            beneficiaryModal.value?.close();
            form.reset();
            editingId.value = null;
        },
    };

    if (editingId.value) form.put(routes.value.beneficiaryUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.beneficiaryStore, options);
}

function deleteBeneficiary(beneficiary) {
    if (!window.confirm(`Delete ${beneficiary.full_name}? This cannot be undone.`)) return;
    router.delete(routes.value.beneficiaryDelete.replace('__ID__', encodeURIComponent(beneficiary.id)), { preserveScroll: true });
}

function toggleSelected(id) {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((selected) => selected !== id)
        : [...selectedIds.value, id];
}

function toggleAllOnPage() {
    const pageIds = rows.value.map((row) => row.id);
    selectedIds.value = allSelected.value
        ? selectedIds.value.filter((id) => !pageIds.includes(id))
        : [...new Set([...selectedIds.value, ...pageIds])];
    bulkMenuOpen.value = false;
}

function clearSelection() {
    selectedIds.value = [];
}

function setBulkStatus(status) {
    if (!selectedIds.value.length) return;
    bulkForm.ids = [...selectedIds.value];
    bulkForm.status = status;
    bulkForm.post(routes.value.beneficiariesBulkStatus, {
        preserveScroll: true,
        onSuccess: clearSelection,
    });
}

function bulkDelete() {
    if (!selectedIds.value.length || !window.confirm(`Delete ${selectedIds.value.length} selected beneficiaries? This cannot be undone.`)) return;
    bulkForm.ids = [...selectedIds.value];
    bulkForm.post(routes.value.beneficiariesBulkDelete, {
        preserveScroll: true,
        onSuccess: clearSelection,
    });
}

function showDetails(beneficiary) {
    viewedBeneficiary.value = beneficiary;
    rowMenuId.value = null;
    viewModal.value?.showModal();
}

function showQr(beneficiary) {
    qrBeneficiary.value = beneficiary;
    rowMenuId.value = null;
    qrModal.value?.showModal();
}

function generateQr(beneficiary) {
    rowMenuId.value = null;
    if (!window.confirm(`Generate a QR code for ${beneficiary.full_name}?`)) return;
    router.post(routes.value.qrGenerate.replace('__ID__', encodeURIComponent(beneficiary.id)), {}, { preserveScroll: true });
}

function sortBy(field) {
    filters.direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
    filters.sort = field;
    visitFilters();
}

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}

function beneficiaryInitials(name) {
    return (name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase();
}

function priorityClass(priority) {
    return { 'Senior Citizen': 'warning', PWD: 'success', 'Solo Parent': 'info' }[priority] ?? 'neutral';
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Beneficiary records</h2><p>Manage registered households and relief eligibility.</p></div>
        <button class="add-button" type="button" @click="openAddModal"><span>+</span> Add beneficiary</button>
    </section>

    <section class="metrics metrics-clickable">
        <button type="button" class="metric-card" :class="{ 'metric-active': !activeFilters }" @click="Object.assign(filters, { search: '', status: 'all', priority_type: 'all', priority_only: false }); visitFilters()">
            <div class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></div><div><small>Total</small><b>{{ totalBeneficiaries }}</b></div>
        </button>
        <button type="button" class="metric-card green" :class="{ 'metric-active': filters.status === 'Active' }" @click="filters.status = 'Active'; visitFilters()">
            <div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div><small>Active</small><b>{{ activeBeneficiaries }}</b></div>
        </button>
        <button type="button" class="metric-card orange" :class="{ 'metric-active': filters.status === 'Inactive' }" @click="filters.status = 'Inactive'; visitFilters()">
            <div class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div><div><small>Inactive</small><b>{{ inactiveBeneficiaries }}</b></div>
        </button>
        <button type="button" class="metric-card" :class="{ 'metric-active': filters.priority_only }" @click="filters.priority_only = !filters.priority_only; visitFilters()">
            <div class="metric-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div><div><small>Priority</small><b>{{ priorityHouseholds }}</b></div>
        </button>
    </section>

    <div v-if="activeFilters" class="active-filters">
        <span class="active-filters-label">Filters:</span>
        <button v-if="filters.search" type="button" class="filter-chip" @click="filters.search = ''">Search: {{ filters.search }} ×</button>
        <button v-if="filters.status !== 'all'" type="button" class="filter-chip" @click="filters.status = 'all'; visitFilters()">Status: {{ filters.status }} ×</button>
        <button v-if="filters.priority_type !== 'all'" type="button" class="filter-chip" @click="filters.priority_type = 'all'; visitFilters()">Priority: {{ filters.priority_type }} ×</button>
        <button v-if="filters.priority_only" type="button" class="filter-chip" @click="filters.priority_only = false; visitFilters()">Priority households ×</button>
        <button type="button" class="filter-clear-all" @click="Object.assign(filters, { search: '', status: 'all', priority_type: 'all', priority_only: false }); visitFilters()">Clear all</button>
    </div>

    <section class="panel record-panel">
        <div class="panel-heading beneficiaries-panel-heading">
            <div><h3>Registered beneficiaries</h3><p>{{ beneficiaries.total ? `Showing ${beneficiaries.from}–${beneficiaries.to} of ${beneficiaries.total}` : 'No records to display' }}</p></div>
            <div class="beneficiaries-toolbar">
                <form class="beneficiaries-filter-form" @submit.prevent="visitFilters()">
                    <div class="search-field">
                        <svg class="search-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input v-model="filters.search" type="search" inputmode="search" class="table-search beneficiaries-search" placeholder="Search name, ID, contact..." aria-label="Search records" autocomplete="off">
                    </div>
                    <button type="submit" class="action-btn search-submit-btn">Search</button>
                    <select v-model="filters.status" class="filter-select" aria-label="Filter by status" @change="visitFilters()"><option value="all">All status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select>
                    <select v-model="filters.priority_type" class="filter-select" aria-label="Filter by priority" @change="visitFilters()"><option value="all">All priorities</option><option>Regular</option><option>Senior Citizen</option><option>PWD</option><option>Solo Parent</option></select>
                    <select v-model.number="filters.per_page" class="filter-select per-page-select" aria-label="Rows per page" @change="visitFilters()"><option :value="10">10 / page</option><option :value="25">25 / page</option><option :value="50">50 / page</option></select>
                </form>
                <div class="row-actions-dropdown beneficiary-bulk-actions">
                    <button type="button" class="row-actions-btn" title="Bulk actions" aria-label="Bulk actions" :aria-expanded="bulkMenuOpen" @click.stop="bulkMenuOpen = !bulkMenuOpen; rowMenuId = null">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                    <div v-if="bulkMenuOpen" class="row-actions-menu show bulk-actions-menu">
                        <button type="button" class="row-action-item" @click="toggleAllOnPage">{{ allSelected ? 'Unmark this page' : 'Mark all on this page' }}</button>
                        <button type="button" class="row-action-item" :disabled="!selectedIds.length" @click="clearSelection(); bulkMenuOpen = false">Clear all marks</button>
                        <div class="bulk-action-divider"/>
                        <button type="button" class="row-action-item" :disabled="!selectedIds.length || bulkForm.processing" @click="setBulkStatus('Active'); bulkMenuOpen = false">Mark selected active</button>
                        <button type="button" class="row-action-item" :disabled="!selectedIds.length || bulkForm.processing" @click="setBulkStatus('Inactive'); bulkMenuOpen = false">Mark selected inactive</button>
                        <button type="button" class="row-action-item row-action-danger" :disabled="!selectedIds.length || bulkForm.processing" @click="bulkDelete(); bulkMenuOpen = false">Delete selected</button>
                    </div>
                </div>
                <a :href="exportHref" class="action-btn export-btn" title="Export filtered results"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export</a>
            </div>
        </div>

        <div v-if="selectedIds.length" class="selection-bar">
            <span><strong>{{ selectedIds.length }}</strong> records marked</span>
            <button type="button" class="filter-clear" @click="clearSelection">Clear marks</button>
        </div>

        <div class="table-wrap">
            <table class="record-table beneficiaries-table">
                <thead><tr>
                    <th><button type="button" class="sort-link" :class="{ 'sort-active': filters.sort === 'full_name' }" @click="sortBy('full_name')">Beneficiary <span class="sort-indicator">{{ filters.sort === 'full_name' ? (filters.direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                    <th>Contact</th>
                    <th><button type="button" class="sort-link" :class="{ 'sort-active': filters.sort === 'household_size' }" @click="sortBy('household_size')">Household <span class="sort-indicator">{{ filters.sort === 'household_size' ? (filters.direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                    <th><button type="button" class="sort-link" :class="{ 'sort-active': filters.sort === 'priority_type' }" @click="sortBy('priority_type')">Priority <span class="sort-indicator">{{ filters.sort === 'priority_type' ? (filters.direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                    <th><button type="button" class="sort-link" :class="{ 'sort-active': filters.sort === 'status' }" @click="sortBy('status')">Status <span class="sort-indicator">{{ filters.sort === 'status' ? (filters.direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                    <th class="actions-col">Actions</th>
                </tr></thead>
                <tbody>
                    <tr v-for="beneficiary in rows" :key="beneficiary.id" class="beneficiary-row" :class="{ selected: selectedIds.includes(beneficiary.id) }">
                        <td><button type="button" class="beneficiary-name-btn view-beneficiary-btn" @click="showDetails(beneficiary)"><div class="beneficiary-cell"><div class="avatar" :class="`a${(beneficiary.id % 4) + 1}`">{{ beneficiary.full_name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase() }}</div><div><b>{{ beneficiary.full_name }}</b><small>{{ beneficiary.beneficiary_no }} — {{ beneficiary.address || 'No address listed' }}</small></div></div></button></td>
                        <td>{{ beneficiary.contact_number || '—' }}</td>
                        <td>{{ beneficiary.household_size || '—' }} members</td>
                        <td><span class="tag" :class="priorityClass(beneficiary.priority_type)">{{ beneficiary.priority_type }}</span></td>
                        <td><span class="tag" :class="beneficiary.status === 'Active' ? 'success' : 'warning'">{{ beneficiary.status }}</span></td>
                        <td class="actions-cell"><div class="row-actions-dropdown">
                            <button type="button" class="row-actions-btn" :aria-expanded="rowMenuId === beneficiary.id" :aria-label="`Actions for ${beneficiary.full_name}`" @click="rowMenuId = rowMenuId === beneficiary.id ? null : beneficiary.id"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg></button>
                            <div v-if="rowMenuId === beneficiary.id" class="row-actions-menu show">
                                <button type="button" class="row-action-item" @click="toggleSelected(beneficiary.id); rowMenuId = null">{{ selectedIds.includes(beneficiary.id) ? 'Unmark record' : 'Mark record' }}</button>
                                <div class="bulk-action-divider"/>
                                <button type="button" class="row-action-item" @click="showDetails(beneficiary)">View details</button>
                                <button type="button" class="row-action-item" @click="rowMenuId = null; openEditModal(beneficiary)">Edit</button>
                                <button v-if="beneficiary.qr_code" type="button" class="row-action-item" @click="showQr(beneficiary)">View QR code</button>
                                <button v-else type="button" class="row-action-item" @click="generateQr(beneficiary)">Generate QR</button>
                                <div class="bulk-action-divider"/><button type="button" class="row-action-item row-action-danger" @click="rowMenuId = null; deleteBeneficiary(beneficiary)">Delete</button>
                            </div>
                        </div></td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="6"><div class="empty-state"><div class="empty-state-icon">♟</div><h4>{{ activeFilters ? 'No matches found' : 'No beneficiaries yet' }}</h4><p>{{ activeFilters ? 'Try adjusting your search or filters.' : 'Add your first household record to get started.' }}</p><button v-if="activeFilters" type="button" class="primary-action" @click="Object.assign(filters, { search: '', status: 'all', priority_type: 'all', priority_only: false }); visitFilters()">Clear filters</button><button v-else type="button" class="primary-action" @click="openAddModal">Add beneficiary</button></div></td></tr>
                </tbody>
            </table>
        </div>
        <InertiaPagination :links="beneficiaries.links" :show="beneficiaries.last_page > 1" />
    </section>

    <AdminModal ref="beneficiaryModal" class="form-modal" @click.self="beneficiaryModal?.close()">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit beneficiary' : 'Add beneficiary' }}</h3><p>{{ editingId ? 'Update beneficiary information.' : 'Create a barangay beneficiary record.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="beneficiaryModal?.close()">×</button></div>
        <form @submit.prevent="saveBeneficiary">
            <label>Full name<input v-model="form.full_name" maxlength="255" placeholder="Enter full name" required :class="{ 'input-error': form.errors.full_name }"><small v-if="form.errors.full_name" class="field-error">{{ form.errors.full_name }}</small></label>
            <label>Contact number<input v-model="form.contact_number" type="tel" inputmode="tel" placeholder="09xx-xxx-xxxx or +63..." :class="{ 'input-error': form.errors.contact_number }"><small v-if="form.errors.contact_number" class="field-error">{{ form.errors.contact_number }}</small></label>
            <label>Address<input v-model="form.address" maxlength="255" placeholder="Enter complete address"><small v-if="form.errors.address" class="field-error">{{ form.errors.address }}</small></label>
            <div class="form-row"><label>Household size<input v-model="form.household_size" type="number" min="1" max="99" placeholder="Members"><small v-if="form.errors.household_size" class="field-error">{{ form.errors.household_size }}</small></label><label>Priority<select v-model="form.priority_type" required><option>Regular</option><option>Senior Citizen</option><option>PWD</option><option>Solo Parent</option></select><small v-if="form.errors.priority_type" class="field-error">{{ form.errors.priority_type }}</small></label></div>
            <label>Status<select v-model="form.status" required><option>Active</option><option>Inactive</option></select><small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="beneficiaryModal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : editingId ? 'Update beneficiary' : 'Save beneficiary' }}</button></div>
        </form>
    </AdminModal>

    <AdminModal ref="viewModal" class="form-modal detail-modal" @click.self="viewModal?.close()">
        <div class="modal-title detail-modal-title">
            <div class="detail-identity">
                <div class="detail-avatar" :class="`a${((viewedBeneficiary?.id || 0) % 4) + 1}`">{{ beneficiaryInitials(viewedBeneficiary?.full_name) }}</div>
                <div class="detail-heading-copy">
                    <h3>{{ viewedBeneficiary?.full_name || 'Beneficiary' }}</h3>
                    <div class="detail-meta"><span>{{ viewedBeneficiary?.beneficiary_no || 'Record details' }}</span><span v-if="viewedBeneficiary" class="tag" :class="viewedBeneficiary.status === 'Active' ? 'success' : 'warning'">{{ viewedBeneficiary.status }}</span></div>
                </div>
            </div>
            <button type="button" class="modal-close" aria-label="Close details" @click="viewModal?.close()">×</button>
        </div>
        <div v-if="viewedBeneficiary" class="detail-modal-body">
            <div class="detail-row"><span>Contact number</span><strong>{{ viewedBeneficiary.contact_number || 'Not provided' }}</strong></div>
            <div class="detail-row detail-row-wide"><span>Address</span><strong>{{ viewedBeneficiary.address || 'No address listed' }}</strong></div>
            <div class="detail-row"><span>Household size</span><strong>{{ viewedBeneficiary.household_size ? `${viewedBeneficiary.household_size} members` : 'Not provided' }}</strong></div>
            <div class="detail-row"><span>Priority</span><strong><span class="tag" :class="priorityClass(viewedBeneficiary.priority_type)">{{ viewedBeneficiary.priority_type || 'Regular' }}</span></strong></div>
            <div class="detail-row"><span>Registered</span><strong>{{ formatDate(viewedBeneficiary.created_at) }}</strong></div>
        </div>
        <div class="modal-actions detail-modal-actions"><button type="button" class="cancel-button" @click="viewModal?.close()">Close</button><button type="button" class="primary-action" @click="viewModal?.close(); openEditModal(viewedBeneficiary)">Edit record</button></div>
    </AdminModal>

    <AdminModal ref="qrModal" class="form-modal qr-modal" @click.self="qrModal?.close()">
        <div class="modal-title"><div><h3>QR Code</h3><p>Scan to verify beneficiary identity</p></div><button type="button" class="modal-close" aria-label="Close" @click="qrModal?.close()">×</button></div>
        <div v-if="qrBeneficiary" class="qr-display"><img :src="`${routes.qrBase}/${qrBeneficiary.qr_code}`" :alt="`QR code for ${qrBeneficiary.full_name}`"></div>
        <div class="modal-actions"><button type="button" class="cancel-button" @click="qrModal?.close()">Close</button></div>
    </AdminModal>
</template>

<style scoped>
.beneficiary-bulk-actions { flex: 0 0 auto; }
.bulk-actions-menu { min-width: 195px; }
.bulk-actions-menu .row-action-item:disabled { color: #a0aec0; cursor: not-allowed; background: transparent; }
.form-modal.detail-modal {
    width: min(520px, calc(100vw - 32px));
    max-height: calc(100dvh - 32px);
    overflow: auto;
    border: 1px solid #e5eaf0;
    border-radius: 16px;
    box-shadow: 0 24px 64px rgba(15, 35, 60, .2);
}

.detail-modal .detail-modal-title {
    align-items: flex-start;
    padding: 21px 24px 18px;
}

.detail-identity { display: flex; min-width: 0; align-items: center; gap: 13px; }
.detail-avatar { display: grid; width: 44px; height: 44px; flex: 0 0 44px; place-items: center; border-radius: 13px; background: #eff6ff; color: #2563eb; font-size: 14px; font-weight: 700; }
.detail-heading-copy { min-width: 0; }
.detail-heading-copy h3 { overflow-wrap: anywhere; color: #173b67; font-size: 17px; line-height: 1.3; }
.detail-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 6px; color: #71819a; font-size: 11px; }
.detail-meta .tag { padding: 4px 8px; font-size: 10px; }
.detail-modal .detail-modal-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 19px 24px 22px; }
.detail-modal .detail-row { display: flex; min-width: 0; flex-direction: column; justify-content: flex-start; gap: 7px; padding: 12px 13px; border: 1px solid #edf1f5; border-radius: 10px; background: #fafbfd; font-size: 12px; }
.detail-modal .detail-row span:first-child { color: #7b8797; font-size: 10px; font-weight: 700; letter-spacing: .045em; text-transform: uppercase; }
.detail-modal .detail-row strong { overflow-wrap: anywhere; color: #263b53; font-size: 12px; font-weight: 600; text-align: left; }
.detail-modal .detail-row-wide { grid-column: 1 / -1; }
.detail-modal .detail-modal-actions { margin: 0; padding: 14px 24px 18px; border-top: 1px solid #edf1f5; }
.detail-modal .detail-modal-actions .primary-action { min-height: 38px; border-radius: 8px; }
.detail-modal .detail-modal-actions .cancel-button { padding: 9px 12px; }
.metrics-clickable .metric-card {
    min-height: 78px;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(24, 49, 83, .035);
}
.metrics-clickable .metric-card.metric-active { box-shadow: 0 0 0 3px #e8f1ff; }
.record-panel { border: 1px solid #e4e9f0; border-radius: 15px; box-shadow: 0 5px 18px rgba(26, 49, 78, .035); }
.beneficiaries-panel-heading { align-items: center; padding: 17px 22px; }
.beneficiaries-toolbar, .beneficiaries-filter-form { gap: 8px; }
.beneficiaries-search {
    height: 38px;
    border-color: #e2e8f0;
    border-radius: 9px;
    background: #fcfdff;
    transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.beneficiaries-search:focus { border-color: #93b4ed; background: #fff; box-shadow: 0 0 0 3px #edf4ff; }
.filter-select { height: 38px; border-color: #e2e8f0; border-radius: 9px; color: #425a77; }
.search-submit-btn, .export-btn { min-height: 38px; border-radius: 9px; font-weight: 650; }
.beneficiaries-table { min-width: 700px; }
.beneficiaries-table th { padding: 12px 16px; background: #f9fafc; }
.beneficiaries-table td { padding: 14px 16px; }
.beneficiaries-table .sort-link {
    appearance: none;
    padding: 3px 0;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #77869b;
    font: inherit;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .065em;
    text-transform: uppercase;
    cursor: pointer;
}
.beneficiaries-table .sort-link:hover, .beneficiaries-table .sort-link.sort-active { color: #2563eb; }
.beneficiaries-table .sort-indicator { color: inherit; font-size: 11px; }
.beneficiaries-table .beneficiary-row { transition: background .15s ease; }
.beneficiaries-table .beneficiary-row:hover { background: #fafcff; }
.beneficiaries-table .beneficiary-row.selected { background: #f5f8ff; }
.beneficiaries-table .beneficiary-row.selected td:first-child { box-shadow: inset 3px 0 #6b9cf0; }
.beneficiary-cell b { color: #233d5b; font-size: 12px; font-weight: 650; }
.beneficiary-cell small { color: #8290a3; }
.row-actions-btn { width: 34px; height: 34px; border-color: #e3e8ef; border-radius: 9px; transition: background .15s ease, border-color .15s ease, color .15s ease; }
.row-actions-btn:hover, .row-actions-btn[aria-expanded="true"] { border-color: #c9d9f1; background: #f3f7ff; }
.beneficiary-bulk-actions .row-actions-btn { width: 38px; height: 38px; }
.bulk-actions-menu { min-width: 205px; padding: 4px; border-color: #e6ebf2; border-radius: 12px; box-shadow: 0 14px 34px rgba(22, 42, 68, .14); }
.bulk-actions-menu .row-action-item { border-radius: 7px; padding: 9px 10px; font-size: 11px; }
.bulk-actions-menu .row-action-item:disabled { color: #a0aec0; cursor: not-allowed; }
.selection-bar { margin: 10px 14px; padding: 10px 13px; border: 1px solid #e0eaff; border-radius: 10px; background: #f7faff; color: #385f98; font-size: 12px; }
.selection-bar strong { color: #1d4ed8; }
.selection-bar .filter-clear { border-radius: 7px; padding: 7px 9px; }
@media (max-width: 480px) {
    .detail-modal .detail-modal-title { padding: 18px 18px 15px; }
    .detail-modal .detail-modal-body { grid-template-columns: 1fr; padding: 16px 18px 18px; }
    .detail-modal .detail-row-wide { grid-column: auto; }
    .detail-modal .detail-modal-actions { padding: 12px 18px 16px; }
}
@media (max-width: 760px) {
    .beneficiaries-panel-heading { padding: 16px; }
    .beneficiaries-toolbar { width: 100%; }
    .beneficiaries-filter-form { width: 100%; }
    .beneficiary-bulk-actions { margin-left: auto; }
    .selection-bar { flex-direction: row; align-items: center; margin: 8px 10px; padding: 9px 11px; }
}
</style>
