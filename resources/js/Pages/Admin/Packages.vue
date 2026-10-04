<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    packages: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({ search: props.filters.search ?? '' });
const modal = ref(null);
const editingId = ref(null);
const form = useForm({ package_name: '', category: 'General', description: '', status: 'Available' });
let searchTimer;
let lastSearch = filters.search.trim();
const rows = computed(() => props.packages.data ?? []);

function visitSearch(replace = false) {
    lastSearch = filters.search.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.packages, { search: lastSearch || undefined }, { preserveState: true, preserveScroll: true, replace });
}

watch(() => filters.search, () => {
    if (filters.search.trim() === lastSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitSearch(true), 350);
});

watch(() => props.filters, (next) => {
    filters.search = next.search ?? '';
    lastSearch = filters.search.trim();
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
    form.package_name = item.package_name ?? '';
    form.category = item.category ?? 'General';
    form.description = item.description ?? '';
    form.status = item.status ?? 'Available';
    modal.value?.showModal();
}

function savePackage() {
    const options = { preserveScroll: true, onSuccess: () => { modal.value?.close(); form.reset(); editingId.value = null; } };
    if (editingId.value) form.put(routes.value.packageUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.packageStore, options);
}

function deletePackage(item) {
    if (!window.confirm(`Delete “${item.package_name}”? This cannot be undone.`)) return;
    router.delete(routes.value.packageDelete.replace('__ID__', encodeURIComponent(item.id)), { preserveScroll: true });
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Relief packages</h2><p>Create and manage relief packages for distribution.</p></div>
        <button class="add-button" type="button" @click="openAddModal"><span>+</span> Add package</button>
    </section>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Relief packages</h3><p>{{ packages.total }}{{ filters.search ? ' matching' : ' total' }} packages</p></div>
            <form class="module-search-form" role="search" @submit.prevent="visitSearch()">
                <label class="sr-only" for="package-search">Search packages</label>
                <input id="package-search" v-model="filters.search" class="table-search" type="search" placeholder="Search packages" aria-label="Search packages">
                <button class="action-btn" type="submit">Search</button>
                <button v-if="filters.search" class="filter-clear" type="button" @click="filters.search = ''; visitSearch()">Clear</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="record-table">
                <thead><tr><th>PACKAGE NAME</th><th>CATEGORY</th><th>DESCRIPTION</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                    <tr v-for="item in rows" :key="item.id">
                        <td><b>{{ item.package_name }}</b></td>
                        <td><span class="tag neutral">{{ item.category }}</span></td>
                        <td>{{ item.description || '—' }}</td>
                        <td><span class="tag" :class="item.status === 'Available' ? 'success' : 'warning'">{{ item.status }}</span></td>
                        <td><div class="inventory-row-actions"><button class="action-btn" type="button" @click="openEditModal(item)">Edit</button><button class="action-btn delete" type="button" :disabled="form.processing" @click="deletePackage(item)">Delete</button></div></td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="empty-cell">{{ filters.search ? 'No packages match this search.' : 'No relief packages yet.' }}</td></tr>
                </tbody>
            </table>
        </div>
        <InertiaPagination :links="packages.links" :show="packages.last_page > 1" />
    </section>

    <AdminModal ref="modal" class="form-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit package' : 'Add package' }}</h3><p>{{ editingId ? 'Update relief package information.' : 'Create a relief package for distribution.' }}</p></div><button class="modal-close" type="button" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="savePackage">
            <label>Package name<input v-model="form.package_name" maxlength="255" required :class="{ 'input-error': form.errors.package_name }"><small v-if="form.errors.package_name" class="field-error">{{ form.errors.package_name }}</small></label>
            <label>Category<select v-model="form.category" required><option>General</option><option>Food</option><option>Medical</option><option>Hygiene</option><option>Emergency</option></select><small v-if="form.errors.category" class="field-error">{{ form.errors.category }}</small></label>
            <label>Description<textarea v-model="form.description" rows="3"/><small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small></label>
            <label>Status<select v-model="form.status" required><option>Available</option><option>Not Available</option></select><small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small></label>
            <div class="modal-actions"><button class="cancel-button" type="button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : editingId ? 'Update package' : 'Save package' }}</button></div>
        </form>
    </AdminModal>
</template>
