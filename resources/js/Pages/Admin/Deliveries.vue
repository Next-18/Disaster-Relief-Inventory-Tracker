<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    deliveries: { type: Object, required: true },
    inventoryItems: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const search = ref(props.filters.search ?? '');
const modal = ref(null);
const form = useForm({
    source: '',
    reference_number: '',
    delivery_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [{ inventory_item_id: '', quantity_received: 1, quantity_damaged: 0, condition_note: '' }],
});

watch(() => props.filters.search, (value) => { search.value = value ?? ''; });
const rows = computed(() => props.deliveries.data ?? []);
const selectedIds = computed(() => form.items.map((item) => Number(item.inventory_item_id)).filter(Boolean));
const acceptedTotal = (line) => Math.max(0, Number(line.quantity_received || 0) - Number(line.quantity_damaged || 0));

function visitSearch() {
    router.get(routes.value.deliveries, { search: search.value.trim() || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

function openModal() {
    form.reset();
    form.clearErrors();
    form.source = '';
    form.delivery_date = new Date().toISOString().slice(0, 10);
    form.items = [{ inventory_item_id: '', quantity_received: 1, quantity_damaged: 0, condition_note: '' }];
    modal.value?.showModal();
}

function addLine() {
    form.items.push({ inventory_item_id: '', quantity_received: 1, quantity_damaged: 0, condition_note: '' });
}

function removeLine(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

function saveDelivery() {
    form.post(routes.value.deliveryStore, {
        preserveScroll: true,
        onSuccess: () => modal.value?.close(),
    });
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <section class="module-heading">
        <div><h2>Deliveries</h2><p>Record supplies received from DSWD or another source, including damaged goods.</p></div>
        <button class="add-button" type="button" :disabled="!inventoryItems.length" @click="openModal"><span>+</span> Record delivery</button>
    </section>

    <div v-if="!inventoryItems.length" class="delivery-notice">Add inventory items first. You can then select them when recording a delivery.</div>

    <section class="panel delivery-panel">
        <div class="delivery-toolbar">
            <div><h3>Delivery history</h3><p>{{ deliveries.total ? `${deliveries.total} recorded deliveries` : 'No deliveries recorded yet' }}</p></div>
            <form class="delivery-search" role="search" @submit.prevent="visitSearch">
                <input v-model="search" type="search" placeholder="Search source, reference, or item" aria-label="Search deliveries">
                <button class="action-btn" type="submit">Search</button>
            </form>
        </div>

        <div class="delivery-list">
            <article v-for="delivery in rows" :key="delivery.id" class="delivery-card">
                <header class="delivery-card-header">
                    <div><h4>{{ delivery.source }}</h4><p>Received {{ formatDate(delivery.delivery_date) }}<span v-if="delivery.reference_number"> · Ref {{ delivery.reference_number }}</span></p></div>
                    <span class="delivery-receiver">Recorded by {{ delivery.receiver?.name ?? 'Former user' }}</span>
                </header>
                <div class="delivery-items">
                    <div v-for="line in delivery.items" :key="line.id" class="delivery-line">
                        <strong>{{ line.inventory_item?.item_name ?? 'Removed inventory item' }}</strong>
                        <span>{{ line.quantity_received }} {{ line.inventory_item?.unit ?? '' }} received</span>
                        <span v-if="line.quantity_damaged" class="delivery-damaged">{{ line.quantity_damaged }} damaged</span>
                        <span class="delivery-accepted">{{ line.quantity_received - line.quantity_damaged }} usable</span>
                        <small v-if="line.condition_note">{{ line.condition_note }}</small>
                    </div>
                </div>
                <p v-if="delivery.notes" class="delivery-notes">{{ delivery.notes }}</p>
            </article>
            <div v-if="!rows.length" class="delivery-empty">{{ search ? 'No deliveries match your search.' : 'No delivery records yet. Record the next incoming supply delivery here.' }}</div>
        </div>
        <InertiaPagination :links="deliveries.links" :show="deliveries.last_page > 1" />
    </section>

    <AdminModal ref="modal" class="form-modal delivery-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>Record incoming delivery</h3><p>Usable quantities are added to inventory when you save.</p></div><button type="button" class="modal-close" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="saveDelivery">
            <div class="form-row">
                <label>Supplied by<input v-model="form.source" maxlength="255" placeholder="e.g. DSWD, CSWDO, donor" required><small v-if="form.errors.source" class="field-error">{{ form.errors.source }}</small></label>
                <label>Delivery date<input v-model="form.delivery_date" type="date" :max="new Date().toISOString().slice(0, 10)" required><small v-if="form.errors.delivery_date" class="field-error">{{ form.errors.delivery_date }}</small></label>
            </div>
            <label>Delivery / reference number <span class="optional-label">Optional</span><input v-model="form.reference_number" maxlength="100" placeholder="DR number or receipt number"><small v-if="form.errors.reference_number" class="field-error">{{ form.errors.reference_number }}</small></label>

            <div class="delivery-form-heading"><div><h4>Items received</h4><p>Damaged goods are recorded and excluded from usable stock.</p></div><button type="button" class="action-btn" @click="addLine">+ Add item</button></div>
            <small v-if="form.errors.items" class="field-error">{{ form.errors.items }}</small>
            <div v-for="(line, index) in form.items" :key="index" class="delivery-form-line">
                <label>Inventory item<select v-model.number="line.inventory_item_id" required><option value="" disabled>Select an item</option><option v-for="item in inventoryItems" :key="item.id" :value="item.id" :disabled="selectedIds.includes(item.id) && Number(line.inventory_item_id) !== item.id">{{ item.item_name }} ({{ item.unit }})</option></select><small v-if="form.errors[`items.${index}.inventory_item_id`]" class="field-error">{{ form.errors[`items.${index}.inventory_item_id`] }}</small></label>
                <div class="form-row"><label>Qty received<input v-model.number="line.quantity_received" type="number" min="1" step="1" required><small v-if="form.errors[`items.${index}.quantity_received`]" class="field-error">{{ form.errors[`items.${index}.quantity_received`] }}</small></label><label>Damaged<input v-model.number="line.quantity_damaged" type="number" min="0" :max="line.quantity_received" step="1" required><small v-if="form.errors[`items.${index}.quantity_damaged`]" class="field-error">{{ form.errors[`items.${index}.quantity_damaged`] }}</small></label></div>
                <p class="delivery-usable-preview">{{ acceptedTotal(line) }} usable {{ inventoryItems.find((item) => Number(item.id) === Number(line.inventory_item_id))?.unit ?? 'units' }} will be added</p>
                <label>Condition / discrepancy <span class="optional-label">Optional</span><input v-model="line.condition_note" maxlength="500" placeholder="e.g. 2 sacks torn; 1 box wet"></label>
                <button v-if="form.items.length > 1" type="button" class="delivery-remove" @click="removeLine(index)">Remove item</button>
            </div>

            <label>Delivery notes <span class="optional-label">Optional</span><textarea v-model="form.notes" rows="3" maxlength="2000" placeholder="Add overall delivery details or discrepancy notes"/><small v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Recording…' : 'Save delivery' }}</button></div>
        </form>
    </AdminModal>
</template>

<style scoped>
.delivery-notice{margin:0 0 18px;padding:13px 16px;border:1px solid #f0d48b;border-radius:10px;background:#fff9e8;color:#76530a;font-size:14px}
.delivery-panel{padding:22px}
.delivery-toolbar,.delivery-card-header,.delivery-form-heading{display:flex;align-items:center;justify-content:space-between;gap:16px}
.delivery-toolbar{margin-bottom:18px}.delivery-toolbar h3,.delivery-form-heading h4{margin:0;color:#182b25}.delivery-toolbar p,.delivery-form-heading p{margin:5px 0 0;color:#75817b;font-size:13px}
.delivery-search{display:flex;gap:8px}.delivery-search input{width:min(360px,45vw);padding:10px 12px;border:1px solid #d7dfda;border-radius:9px;font:inherit}
.delivery-list{display:grid;gap:12px}.delivery-card{padding:16px;border:1px solid #e4eae6;border-radius:12px;background:#fff}.delivery-card-header{align-items:flex-start}.delivery-card-header h4{margin:0;font-size:16px;color:#20362d}.delivery-card-header p{margin:5px 0 0;color:#748079;font-size:13px}.delivery-receiver{color:#718078;font-size:12px;text-align:right}
.delivery-items{display:grid;gap:7px;margin-top:14px}.delivery-line{display:grid;grid-template-columns:minmax(130px,1.5fr) 1fr 1fr 1fr;gap:10px;align-items:center;padding:10px 12px;border-radius:8px;background:#f7f9f7;font-size:13px}.delivery-line small{grid-column:1/-1;color:#65736b}.delivery-damaged{color:#a24131}.delivery-accepted{color:#28714f;font-weight:600}.delivery-notes{margin:12px 0 0;color:#5f6c65;font-size:13px}.delivery-empty{padding:38px 16px;text-align:center;color:#78847e}
.delivery-modal{max-height:90vh;overflow-y:auto}.optional-label{color:#85918a;font-size:12px;font-weight:400}.delivery-form-heading{margin:18px 0 10px}.delivery-form-line{position:relative;margin:0 0 12px;padding:14px;border:1px solid #e1e8e3;border-radius:10px}.delivery-usable-preview{margin:4px 0 12px;color:#28714f;font-size:13px;font-weight:600}.delivery-remove{margin:-4px 0 10px;padding:0;border:0;background:none;color:#a24131;font:inherit;font-size:13px;cursor:pointer}
@media(max-width:700px){.delivery-panel{padding:14px}.delivery-toolbar,.delivery-card-header{align-items:stretch;flex-direction:column}.delivery-search{width:100%}.delivery-search input{width:100%;min-width:0}.delivery-receiver{text-align:left}.delivery-line{grid-template-columns:1fr 1fr}.delivery-line strong{grid-column:1/-1}}
</style>
