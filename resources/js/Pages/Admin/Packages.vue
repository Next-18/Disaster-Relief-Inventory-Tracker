<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { appConfirm } from '../../composables/useAppDialog';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    packages: { type: Object, required: true },
    inventoryItems: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({ search: props.filters.search ?? '' });
const modal = ref(null);
const editingId = ref(null);
const form = useForm({ package_name: '', category: 'General', description: '', status: 'Available', items: [] });
let searchTimer;
let lastSearch = filters.search.trim();
const rows = computed(() => props.packages.data ?? []);
const categoryOptions = computed(() => [...new Set(['General', 'Food', 'Medical', 'Hygiene', 'Emergency', ...props.categories])].sort());

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
    form.items = [{ inventory_item_id: '', quantity_per_package: 1 }];
    modal.value?.showModal();
}

function openEditModal(item) {
    editingId.value = item.id;
    form.clearErrors();
    form.package_name = item.package_name ?? '';
    form.category = item.category ?? 'General';
    form.description = item.description ?? '';
    form.status = item.status ?? 'Available';
    form.items = (item.inventory_items ?? []).map((ingredient) => ({
        inventory_item_id: ingredient.id,
        quantity_per_package: ingredient.pivot.quantity_per_package,
    }));
    if (!form.items.length) form.items = [{ inventory_item_id: '', quantity_per_package: 1 }];
    modal.value?.showModal();
}

function addIngredient() {
    form.items.push({ inventory_item_id: '', quantity_per_package: 1 });
}

function removeIngredient(index) {
    form.items.splice(index, 1);
}

function availableItemsFor(index) {
    const selectedElsewhere = form.items
        .filter((_, itemIndex) => itemIndex !== index)
        .map((ingredient) => Number(ingredient.inventory_item_id));
    return props.inventoryItems.filter((item) => !selectedElsewhere.includes(Number(item.id))
        || Number(item.id) === Number(form.items[index]?.inventory_item_id));
}

function savePackage() {
    const options = { preserveScroll: true, onSuccess: () => { modal.value?.close(); form.reset(); editingId.value = null; } };
    if (editingId.value) form.put(routes.value.packageUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.packageStore, options);
}

async function deletePackage(item) {
    const confirmed = await appConfirm(`Delete “${item.package_name}”? This cannot be undone.`, {
        title: 'Delete package?',
        confirmLabel: 'Delete package',
        danger: true,
    });
    if (confirmed) router.delete(routes.value.packageDelete.replace('__ID__', encodeURIComponent(item.id)), { preserveScroll: true });
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Relief packages</h2><p>Build reusable package templates from inventory supplies. Stock is deducted each time a package is released.</p></div>
        <div class="package-create-action"><small v-if="!inventoryItems.length">Add inventory supplies before creating a package template.</small><button class="add-button" type="button" :disabled="!inventoryItems.length" @click="openAddModal"><span>+</span> Add package</button></div>
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
                <thead><tr><th>PACKAGE TEMPLATE</th><th>CONTENTS</th><th>DESCRIPTION</th><th>PACKS READY</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                <tbody>
                    <tr v-for="item in rows" :key="item.id">
                        <td><b>{{ item.package_name }}</b><small><span class="tag neutral">{{ item.category }}</span></small></td>
                        <td><span v-if="item.inventory_items?.length">{{ item.inventory_items.map((part) => `${part.pivot.quantity_per_package} ${part.unit} ${part.item_name}`).join(', ') }}</span><span v-else class="tag warning">Recipe needed</span></td>
                        <td>{{ item.description || '—' }}</td>
                        <td><b>{{ item.available_quantity ?? 0 }}</b> packs</td>
                        <td><span class="tag" :class="item.status === 'Available' ? 'success' : 'warning'">{{ item.status === 'Available' ? 'Active template' : item.status === 'Unavailable' ? 'Unavailable' : 'Paused' }}</span></td>
                        <td><div class="inventory-row-actions"><button class="action-btn" type="button" @click="openEditModal(item)">Edit</button><button class="action-btn delete" type="button" :disabled="form.processing" @click="deletePackage(item)">Delete</button></div></td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="6" class="empty-cell">{{ filters.search ? 'No packages match this search.' : 'No relief packages yet.' }}</td></tr>
                </tbody>
            </table>
        </div>
        <InertiaPagination :links="packages.links" :show="packages.last_page > 1" />
    </section>

    <AdminModal ref="modal" class="form-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit package' : 'Add package' }}</h3><p>{{ editingId ? 'Update relief package information.' : 'Create a relief package for distribution.' }}</p></div><button class="modal-close" type="button" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="savePackage">
            <label>Package name<input v-model="form.package_name" maxlength="255" required :class="{ 'input-error': form.errors.package_name }"><small v-if="form.errors.package_name" class="field-error">{{ form.errors.package_name }}</small></label>
            <label>Category<select v-model="form.category" required><option v-for="category in categoryOptions" :key="category" :value="category">{{ category }}</option></select><small class="recipe-help">Choose a category already used by inventory or packages.</small><small v-if="form.errors.category" class="field-error">{{ form.errors.category }}</small></label>
            <label>Description<textarea v-model="form.description" rows="3"/><small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small></label>
            <fieldset class="package-recipe"><legend>Items in one package</legend>
                <p class="recipe-help">Choose the supplies and amount needed for one pack. Stock is deducted automatically when staff records a release.</p>
                <div v-for="(ingredient, index) in form.items" :key="index" class="recipe-row">
                    <select v-model="ingredient.inventory_item_id" required aria-label="Inventory item">
                        <option value="">Select supply</option>
                        <option v-for="stock in availableItemsFor(index)" :key="stock.id" :value="stock.id">{{ stock.item_name }} ({{ stock.quantity }} {{ stock.unit }} in stock)</option>
                    </select>
                    <input v-model.number="ingredient.quantity_per_package" type="number" min="1" step="1" required aria-label="Quantity per package">
                    <span>{{ inventoryItems.find((stock) => Number(stock.id) === Number(ingredient.inventory_item_id))?.unit || 'units' }} / pack</span>
                    <button type="button" class="action-btn delete" aria-label="Remove supply" :disabled="form.items.length === 1" @click="removeIngredient(index)">Remove</button>
                </div>
                <small v-if="form.errors.items" class="field-error">{{ form.errors.items }}</small>
                <button type="button" class="action-btn recipe-add" @click="addIngredient">+ Add supply</button>
            </fieldset>
            <label>Template status<select v-model="form.status" required><option value="Available">Active</option><option value="Not Available">Paused</option><option value="Unavailable">Unavailable</option></select><small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small></label>
            <div class="modal-actions"><button class="cancel-button" type="button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : editingId ? 'Update package' : 'Save package' }}</button></div>
        </form>
    </AdminModal>
</template>

<style scoped>
.package-recipe { min-width: 0; margin: 4px 0 12px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px; }
.package-create-action { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.package-create-action small { color: #718096; font-size: 11px; }
.package-recipe legend { padding: 0 5px; color: #334e6e; font-size: 12px; font-weight: 700; }
.recipe-help { margin: 0 0 11px; color: #718096; font-size: 11px; line-height: 1.5; }
.recipe-row { display: grid; grid-template-columns: minmax(0, 1fr) 84px auto auto; align-items: center; gap: 8px; margin-bottom: 8px; }
.recipe-row span { color: #718096; font-size: 11px; white-space: nowrap; }
.recipe-row input, .recipe-row select { min-width: 0; width: 100%; min-height: 38px; }
.recipe-add { margin-top: 3px; }
@media (max-width: 540px) { .recipe-row { grid-template-columns: minmax(0, 1fr) 75px; }.recipe-row span { grid-column: 1; }.recipe-row button { grid-column: 2; grid-row: 2; } }
</style>
