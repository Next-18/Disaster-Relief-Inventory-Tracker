<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    beneficiaries: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const filters = reactive({ search: props.filters.search ?? '' });
const activeRequest = ref(null);
const modal = ref(null);
const viewedBeneficiary = ref(null);
let searchTimer;
let lastSearch = filters.search.trim();
const rows = computed(() => props.beneficiaries ?? []);

function visitSearch(replace = false) {
    lastSearch = filters.search.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.qrCodes, { search: lastSearch || undefined }, { preserveState: true, preserveScroll: true, replace });
}

watch(() => filters.search, () => {
    if (filters.search.trim() === lastSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visitSearch(true), 300);
});

watch(() => props.filters, (next) => {
    filters.search = next.search ?? '';
    lastSearch = filters.search.trim();
}, { deep: true });

onBeforeUnmount(() => clearTimeout(searchTimer));

function showQr(beneficiary) {
    viewedBeneficiary.value = beneficiary;
    modal.value?.showModal();
}

function qrUrl(beneficiary) {
    return `${routes.value.qrBase}/${encodeURIComponent(beneficiary.qr_code)}`;
}

function runAction(id, action, url) {
    activeRequest.value = id;
    router.post(url, {}, { preserveScroll: true, onFinish: () => { activeRequest.value = null; } });
}
</script>

<template>
    <section class="module-heading"><div><h2>QR codes</h2><p>Generate and manage beneficiary QR codes.</p></div></section>

    <section class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Beneficiary QR codes</h3><p>{{ rows.length }} active beneficiaries</p></div>
            <form class="module-search-form" role="search" @submit.prevent="visitSearch()">
                <label class="sr-only" for="qr-search">Search beneficiaries</label>
                <input id="qr-search" v-model="filters.search" class="table-search" type="search" placeholder="Search beneficiaries" aria-label="Search beneficiaries">
                <button class="action-btn" type="submit">Search</button>
                <button v-if="filters.search" class="filter-clear" type="button" @click="filters.search = ''; visitSearch()">Clear</button>
            </form>
        </div>
        <div class="table-wrap"><table class="record-table">
            <thead><tr><th>BENEFICIARY</th><th>QR CODE</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
            <tbody>
                <tr v-for="beneficiary in rows" :key="beneficiary.id">
                    <td><b>{{ beneficiary.full_name }}</b><small>{{ beneficiary.beneficiary_no }}</small></td>
                    <td><button v-if="beneficiary.qr_code" class="action-btn" type="button" @click="showQr(beneficiary)">View QR Code</button><span v-else class="text-muted">Not generated</span></td>
                    <td><span class="tag" :class="beneficiary.qr_code ? 'success' : 'warning'">{{ beneficiary.qr_code ? 'Generated' : 'Pending' }}</span></td>
                    <td>
                        <a v-if="beneficiary.qr_code" class="action-btn" :href="routes.qrDownload.replace('__ID__', encodeURIComponent(beneficiary.id))">Download</a>
                        <button v-else class="action-btn" type="button" :disabled="activeRequest === beneficiary.id" @click="runAction(beneficiary.id, 'generate', routes.qrGenerate.replace('__ID__', encodeURIComponent(beneficiary.id)))">{{ activeRequest === beneficiary.id ? 'Generating…' : 'Generate' }}</button>
                    </td>
                </tr>
                <tr v-if="!rows.length"><td colspan="4" class="empty-cell">{{ filters.search ? 'No beneficiaries match this search.' : 'No beneficiaries found.' }}</td></tr>
            </tbody>
        </table></div>
    </section>

    <AdminModal ref="modal" class="form-modal qr-modal" @click.self="modal?.close()">
        <div class="modal-title"><div><h3>{{ viewedBeneficiary?.full_name }} — QR Code</h3><p>{{ viewedBeneficiary?.beneficiary_no }}</p></div><button class="modal-close" type="button" aria-label="Close" @click="modal?.close()">×</button></div>
        <div class="qr-display"><img v-if="viewedBeneficiary?.qr_code" :src="qrUrl(viewedBeneficiary)" :alt="`QR code for ${viewedBeneficiary.full_name}`"></div>
        <div class="modal-actions"><a v-if="viewedBeneficiary?.qr_code" class="action-btn" :href="routes.qrDownload.replace('__ID__', encodeURIComponent(viewedBeneficiary.id))">Download</a><button class="cancel-button" type="button" @click="modal?.close()">Close</button></div>
    </AdminModal>
</template>

<style scoped>
.text-muted { color: #94a3b8; }
.qr-modal { width: min(360px, calc(100vw - 32px)); }
.qr-display { display: flex; justify-content: center; align-items: center; padding: 30px 20px; }
.qr-display img { max-width: 250px; max-height: 250px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: white; }
</style>
