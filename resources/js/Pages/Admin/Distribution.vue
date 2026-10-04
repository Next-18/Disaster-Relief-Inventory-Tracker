<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    distributions: { type: Object, required: true },
    beneficiaries: { type: Array, required: true },
    packages: { type: Array, required: true },
    search: { type: String, default: '' },
    today: { type: String, required: true },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const searchTerm = ref(props.search);
const modal = ref(null);
const editingId = ref(null);
const form = useForm({ beneficiary_id: '', package_id: '', date_released: '', status: 'Released', notes: '' });
const rows = computed(() => props.distributions.data ?? []);
let searchTimer;
let lastSubmittedSearch = searchTerm.value.trim();

watch(() => props.search, (value) => {
    lastSubmittedSearch = (value ?? '').trim();
    searchTerm.value = value ?? '';
});
watch(searchTerm, () => {
    if (searchTerm.value.trim() === lastSubmittedSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchDistributions(true), 350);
});

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('action') === 'add') {
        openAddModal();
        const url = new URL(window.location.href);
        url.searchParams.delete('action');
        window.history.replaceState(window.history.state, '', url);
    }
});
onBeforeUnmount(() => clearTimeout(searchTimer));

function searchDistributions(replace = false) {
    lastSubmittedSearch = searchTerm.value.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.distribution, { search: lastSubmittedSearch || undefined }, { preserveState: true, preserveScroll: true, replace });
}

function openAddModal() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.date_released = props.today;
    form.status = 'Released';
    modal.value?.showModal();
}

function openEditModal(distribution) {
    editingId.value = distribution.id;
    form.clearErrors();
    form.beneficiary_id = distribution.beneficiary_id;
    form.package_id = distribution.package_id ?? '';
    form.date_released = String(distribution.date_released).slice(0, 10);
    form.status = distribution.status;
    form.notes = distribution.notes ?? '';
    modal.value?.showModal();
}

function saveDistribution() {
    const options = { preserveScroll: true, onSuccess: () => { modal.value?.close(); form.reset(); editingId.value = null; } };
    if (editingId.value) form.put(routes.value.distributionUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.distributionStore, options);
}

function deleteDistribution(distribution) {
    if (!window.confirm(`Delete this distribution record for ${distribution.beneficiary?.full_name ?? 'this beneficiary'}? This cannot be undone.`)) return;
    router.delete(routes.value.distributionDelete.replace('__ID__', encodeURIComponent(distribution.id)), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Distribution records</h2><p>Record and monitor relief package distributions.</p></div>
        <button class="add-button" type="button" @click="openAddModal"><span>+</span> Record Distribution</button>
    </section>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Distribution history</h3><p>{{ distributions.total }}{{ search ? ' matching' : ' total' }} distribution records</p></div>
            <form class="module-search-form" role="search" @submit.prevent="searchDistributions()">
                <label class="sr-only" for="distribution-search">Search distributions</label>
                <input id="distribution-search" v-model="searchTerm" class="table-search" type="search" placeholder="Search beneficiary, package..." aria-label="Search distributions">
                <button class="action-btn" type="submit">Search</button>
                <button v-if="searchTerm" class="filter-clear" type="button" @click="searchTerm = ''; searchDistributions()">Clear</button>
            </form>
        </div>
        <div class="table-wrap"><table class="record-table">
            <thead><tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
            <tbody>
                <tr v-for="distribution in rows" :key="distribution.id">
                    <td><b>{{ distribution.beneficiary?.full_name ?? 'Unknown Beneficiary' }}</b><small>{{ distribution.beneficiary?.beneficiary_no ?? 'N/A' }}</small></td>
                    <td>{{ distribution.relief_package?.package_name ?? 'Unknown Package' }}</td>
                    <td>{{ formatDate(distribution.date_released) }}</td>
                    <td><span class="tag" :class="distribution.status === 'Released' ? 'success' : 'warning'">{{ distribution.status }}</span></td>
                    <td><div class="inventory-row-actions"><button type="button" class="action-btn" @click="openEditModal(distribution)">Edit</button><button type="button" class="action-btn delete" @click="deleteDistribution(distribution)">Delete</button></div></td>
                </tr>
                <tr v-if="!rows.length"><td colspan="5" class="empty-cell">{{ search ? 'No distributions match this search.' : 'No distribution records yet.' }}</td></tr>
            </tbody>
        </table></div>
        <InertiaPagination :links="distributions.links" :show="distributions.last_page > 1" />
    </section>

    <AdminModal ref="modal" class="form-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit Distribution' : 'Record Distribution' }}</h3><p>{{ editingId ? 'Update distribution information.' : 'Record a relief package distribution to a beneficiary.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="saveDistribution">
            <label>Beneficiary<select v-model="form.beneficiary_id" required><option value="">Select beneficiary</option><option v-for="beneficiary in beneficiaries" :key="beneficiary.id" :value="beneficiary.id">{{ beneficiary.full_name }} ({{ beneficiary.beneficiary_no }})</option></select><small v-if="form.errors.beneficiary_id" class="field-error">{{ form.errors.beneficiary_id }}</small></label>
            <label>Relief Package<select v-model="form.package_id" required><option value="">Select package</option><option v-for="item in packages" :key="item.id" :value="item.id">{{ item.package_name }}</option></select><small v-if="form.errors.package_id" class="field-error">{{ form.errors.package_id }}</small></label>
            <label>Date Released<input v-model="form.date_released" type="date" required><small v-if="form.errors.date_released" class="field-error">{{ form.errors.date_released }}</small></label>
            <label>Status<select v-model="form.status" required><option>Released</option><option>Pending</option></select><small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small></label>
            <label>Notes<textarea v-model="form.notes" rows="3"/><small v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : editingId ? 'Update Distribution' : 'Record Distribution' }}</button></div>
        </form>
    </AdminModal>
</template>
