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
        <div class="modal-title qr-modal-title">
            <div class="qr-modal-heading">
                <span class="qr-modal-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v6h-6v-2h4zM14 18h2v2h-2z"/></svg></span>
                <div><span class="qr-modal-eyebrow">Beneficiary QR code</span><h3>{{ viewedBeneficiary?.full_name || 'Beneficiary' }}</h3><p>{{ viewedBeneficiary?.beneficiary_no }}</p></div>
            </div>
            <button class="modal-close" type="button" aria-label="Close" @click="modal?.close()">×</button>
        </div>
        <div class="qr-modal-content">
            <div class="qr-image-frame"><img v-if="viewedBeneficiary?.qr_code" :src="qrUrl(viewedBeneficiary)" :alt="`QR code for ${viewedBeneficiary.full_name}`"></div>
            <p>Scan this code to verify the beneficiary.</p>
        </div>
        <div class="modal-actions qr-modal-actions"><button class="cancel-button" type="button" @click="modal?.close()">Close</button><a v-if="viewedBeneficiary?.qr_code" class="primary-action qr-download" :href="routes.qrDownload.replace('__ID__', encodeURIComponent(viewedBeneficiary.id))">Download QR code</a></div>
    </AdminModal>
</template>

<style scoped>
.text-muted { color: #94a3b8; }
.form-modal.qr-modal { width: min(430px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #e5eaf0; border-radius: 16px; box-shadow: 0 24px 64px rgba(15, 35, 60, .2); }
.qr-modal .qr-modal-title { align-items: flex-start; padding: 20px 23px 17px; }
.qr-modal-heading { display: flex; min-width: 0; align-items: center; gap: 12px; }
.qr-modal-icon { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; border: 1px solid #dbeafe; border-radius: 12px; background: #eff6ff; color: #2563eb; }
.qr-modal-icon svg { width: 20px; height: 20px; fill: currentColor; }
.qr-modal-eyebrow { display: block; margin-bottom: 4px; color: #7b8797; font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.qr-modal-heading h3 { overflow-wrap: anywhere; color: #173b67; font-size: 16px; }
.qr-modal-heading p { margin-top: 4px; }
.qr-modal-content { display: grid; justify-items: center; gap: 13px; padding: 22px 24px 24px; }
.qr-image-frame { display: grid; width: 100%; max-width: 292px; aspect-ratio: 1; place-items: center; padding: 18px; border: 1px solid #edf1f5; border-radius: 14px; background: #f8fafc; }
.qr-image-frame img { display: block; width: 100%; max-width: 250px; aspect-ratio: 1; object-fit: contain; padding: 9px; border: 1px solid #e6ebf1; border-radius: 9px; background: #fff; }
.qr-modal-content > p { margin: 0; color: #7b8797; font-size: 11px; text-align: center; }
.qr-modal .qr-modal-actions { margin: 0; padding: 14px 23px 18px; border-top: 1px solid #edf1f5; }
.qr-modal .qr-modal-actions .qr-download { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; padding: 10px 15px; border: 1px solid #2563eb; border-radius: 8px; background: #2563eb; color: #fff; font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
.qr-modal .qr-modal-actions .qr-download:hover { border-color: #1d4ed8; background: #1d4ed8; color: #fff; }
.qr-modal .qr-modal-actions .qr-download:focus-visible { outline: 3px solid #bfdbfe; outline-offset: 2px; }
@media (max-width: 480px) { .qr-modal .qr-modal-title { padding: 18px 18px 15px; }.qr-modal-content { padding: 18px; }.qr-image-frame { max-width: 260px; padding: 14px; }.qr-modal .qr-modal-actions { padding: 12px 18px 16px; } }
</style>
