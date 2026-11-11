<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import QrScannerModal from '../../Components/QrScannerModal.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { appConfirm } from '../../composables/useAppDialog';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    distributions: { type: Object, required: true },
    beneficiaries: { type: Array, required: true },
    packages: { type: Array, required: true },
    search: { type: String, default: '' },
    status: { type: String, default: 'all' },
    today: { type: String, required: true },
    canManageDistributions: { type: Boolean, default: false },
    canAssignDistributions: { type: Boolean, default: false },
    canReleasePackages: { type: Boolean, default: false },
    canViewDistributionHistory: { type: Boolean, default: false },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const searchTerm = ref(props.search);
const statusFilter = ref(props.status);
const modal = ref(null);
const recordModalOpen = ref(false);
const scannerModal = ref(null);
const scanError = ref('');
const alreadyClaimedMessage = ref('');
const scannedBeneficiary = ref(null);
const pendingBeneficiary = ref(null);
const pendingReleaseOptions = ref([]);
const pendingReleaseError = ref('');
const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const editingId = ref(null);
const form = useForm({ recipient_mode: 'individual', beneficiary_id: '', priority_type: '', package_id: '', start_date: '', deadline: '', status: 'Pending', notes: '' });
const rows = computed(() => props.distributions.data ?? []);
const recipientCount = computed(() => {
    if (form.recipient_mode === 'individual') return form.beneficiary_id ? 1 : 0;
    if (form.recipient_mode === 'all') return props.beneficiaries.length;
    return props.beneficiaries.filter((beneficiary) => beneficiary.priority_type === form.priority_type).length;
});
const selectedPackage = computed(() => packageOptions.value.find((item) => String(item.id) === String(form.package_id)));
const insufficientPackageStock = computed(() => form.recipient_mode !== 'individual'
    && selectedPackage.value
    && recipientCount.value > Number(selectedPackage.value.available_quantity ?? 0));
// Received packages are hidden from the available list, but an edit form
// must still show the package already assigned to that record.
const packageOptions = computed(() => {
    const options = [...props.packages];
    const assigned = rows.value.find((d) => d.id === editingId.value)?.relief_package;
    if (form.package_id && !options.some((item) => item.id === form.package_id)) {
        options.push({ id: form.package_id, package_name: `${assigned?.package_name ?? 'Assigned package'} (not available)` });
    }
    return options;
});
let searchTimer;
let lastSubmittedSearch = searchTerm.value.trim();

watch(() => props.search, (value) => {
    lastSubmittedSearch = (value ?? '').trim();
    searchTerm.value = value ?? '';
});
watch(() => props.status, (value) => {
    statusFilter.value = value ?? 'all';
});
watch(searchTerm, () => {
    if (searchTerm.value.trim() === lastSubmittedSearch) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchDistributions(true), 350);
});

onMounted(() => {
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    if (props.canAssignDistributions && new URLSearchParams(window.location.search).get('action') === 'add') {
        openAddModal();
        const url = new URL(window.location.href);
        url.searchParams.delete('action');
        window.history.replaceState(window.history.state, '', url);
    }
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});

function updateOnlineStatus() {
    isOnline.value = navigator.onLine;
}

function searchDistributions(replace = false) {
    lastSubmittedSearch = searchTerm.value.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.distribution, {
        search: lastSubmittedSearch || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    }, { preserveState: true, preserveScroll: true, replace });
}

function openAddModal(beneficiaryId = '') {
    if (!props.canAssignDistributions) return;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.start_date = props.today;
    form.deadline = props.today;
    form.status = 'Pending';
    form.recipient_mode = 'individual';
    form.priority_type = '';
    if (beneficiaryId) form.beneficiary_id = beneficiaryId;
    recordModalOpen.value = true;
    modal.value?.showModal();
}

function openEditModal(distribution) {
    recordModalOpen.value = true;
    editingId.value = distribution.id;
    form.clearErrors();
    form.beneficiary_id = distribution.beneficiary_id;
    form.recipient_mode = 'individual';
    form.priority_type = '';
    form.package_id = distribution.package_id ?? '';
    form.start_date = distribution.start_date ?? String(distribution.date_released).slice(0, 10);
    form.deadline = distribution.deadline ?? String(distribution.date_released).slice(0, 10);
    form.status = distribution.status;
    form.notes = distribution.notes ?? '';
    modal.value?.showModal();
}

function openScanner() {
    if (!isOnline.value) {
        scanError.value = 'An internet connection is required to verify a beneficiary.';
        return;
    }
    scanError.value = '';
    scannerModal.value?.open();
}

function closeBeneficiaryCheck() {
    document.getElementById('beneficiary-check-modal')?.close();
    scannedBeneficiary.value = null;
}

async function handleScannedCode(text) {
    if (!isOnline.value) {
        scanError.value = 'An internet connection is required to verify a beneficiary.';
        return;
    }

    const code = (text ?? '').trim();
    let match = null;
    let response;
    try {
        response = await fetch(`/admin/distribution/lookup?code=${encodeURIComponent(code)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
    } catch {
        scanError.value = 'Could not reach the server. Check your connection and scan again.';
        return;
    }

    if (response.ok) {
        try {
            const result = await response.json();
            match = result.data ?? null;
        } catch {
            match = null;
        }
    }
    if (!match) {
        scanError.value = response.status === 404
            ? `No active beneficiary found for "${code}".`
            : 'Could not verify this beneficiary with the server. Check your connection and try again.';
        return;
    }
    scanError.value = '';
    const releasedToday = match.released_today ?? [];
    if (props.canReleasePackages && releasedToday.length) {
        scannerModal.value?.close();
        alreadyClaimedMessage.value = `${match.full_name} has already claimed ${releasedToday.join(', ')} today. Each beneficiary can receive one package per day.`;
        document.getElementById('already-claimed-modal')?.showModal();
        return;
    }
    if (props.canReleasePackages && match.pending_distributions?.length) {
        scannerModal.value?.close();
        pendingBeneficiary.value = { ...match, qr_payload: code };
        pendingReleaseOptions.value = match.pending_distributions;
        pendingReleaseError.value = '';
        if (pendingReleaseOptions.value.length === 1) releasePendingDistribution(pendingReleaseOptions.value[0]);
        else document.getElementById('pending-release-modal')?.showModal();
    } else if (props.canReleasePackages && recordModalOpen.value) {
        scannerModal.value?.close();
        form.beneficiary_id = match.id;
    } else if (props.canReleasePackages) {
        scanError.value = `No pending package is assigned to ${match.full_name}. Assign a package as Pending first.`;
    } else {
        scannerModal.value?.close();
        scannedBeneficiary.value = match;
        document.getElementById('beneficiary-check-modal')?.showModal();
    }
}

function releasePendingDistribution(assignment) {
    if (!pendingBeneficiary.value || !assignment) return;
    if (!isOnline.value) {
        pendingReleaseError.value = 'An internet connection is required to release this package.';
        return;
    }
    pendingReleaseError.value = '';
    if (!pendingBeneficiary.value.offlineClaimId) {
        const random = new Uint8Array(16);
        crypto.getRandomValues(random);
        random[6] = (random[6] & 0x0f) | 0x40;
        random[8] = (random[8] & 0x3f) | 0x80;
        const hex = [...random].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        pendingBeneficiary.value.offlineClaimId = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    router.post(routes.value.distributionReleasePending, {
        beneficiary_no: pendingBeneficiary.value.qr_payload,
        distribution_id: assignment.id,
        offline_claim_id: pendingBeneficiary.value.offlineClaimId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            document.getElementById('pending-release-modal')?.close();
            if (recordModalOpen.value) modal.value?.close();
            pendingReleaseOptions.value = [];
            pendingBeneficiary.value = null;
        },
        onError: (errors) => {
            pendingReleaseError.value = Object.values(errors).flat()[0] ?? 'Could not release this assignment. Scan again and retry.';
        },
    });
}

function saveDistribution() {
    const options = { preserveScroll: true, onSuccess: () => { modal.value?.close(); form.reset(); editingId.value = null; } };
    if (editingId.value) form.put(routes.value.distributionUpdate.replace('__ID__', encodeURIComponent(editingId.value)), options);
    else form.post(routes.value.distributionStore, options);
}

async function deleteDistribution(distribution) {
    const isPending = distribution.status === 'Pending';
    const confirmed = await appConfirm(
        isPending
            ? `Cancel this package assignment for ${distribution.beneficiary?.full_name ?? 'this beneficiary'}? The history record will remain, reserved stock will be restored, and the beneficiary will be notified.`
            : `Void this released distribution record for ${distribution.beneficiary?.full_name ?? 'this beneficiary'}? The history record will remain and stock will not be restored.`,
        {
        title: isPending ? 'Cancel assignment?' : 'Void distribution record?',
        confirmLabel: isPending ? 'Cancel assignment' : 'Void record',
        danger: true,
    });
    if (confirmed) router.delete(routes.value.distributionDelete.replace('__ID__', encodeURIComponent(distribution.id)), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}
</script>

<template>
    <section class="module-heading">
        <div><h2>{{ canAssignDistributions ? 'Distribution records' : canReleasePackages ? 'Beneficiary check-in' : canViewDistributionHistory ? 'Distribution history' : 'Beneficiary verification' }}</h2><p>{{ canAssignDistributions ? 'Assign packages to beneficiaries or record a direct release.' : canReleasePackages ? 'Scan a beneficiary QR code to verify their identity and release an assigned package. New assignments are handled by an administrator.' : canViewDistributionHistory ? 'Review package assignments and releases. Staff handle package check-in and release.' : 'Scan a beneficiary QR code to verify their identity before directing them to the distribution desk.' }}</p></div>
        <div class="module-heading-actions">
            <button v-if="canReleasePackages" class="add-button" type="button" :disabled="!isOnline" @click="openScanner">{{ !isOnline ? 'Connection required' : 'Scan QR' }}</button>
            <button v-if="canAssignDistributions" class="add-button" type="button" :disabled="!isOnline" @click="openAddModal()"><span>+</span> Record Distribution</button>
        </div>
    </section>

    <section class="connection-status" :class="isOnline ? 'is-connected' : 'is-disconnected'" role="status">
        <span class="connection-indicator"></span>
        <div><strong>{{ isOnline ? 'Online' : 'Connection unavailable' }}</strong><span>{{ isOnline ? 'Check-ins and releases are confirmed by the server.' : 'Connect to the internet to verify beneficiaries and record releases.' }}</span></div>
    </section>

    <section v-if="!canReleasePackages" class="panel record-panel">
        <div class="checkin-station">
            <div class="checkin-station-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h5v5H4zM15 4h5v5h-5zM4 15h5v5H4zM15 15h2v2h-2zM19 15v5M15 19h2"/><path d="M11 4v3M11 11h3M4 11v2M11 15v5"/></svg></div>
            <div class="checkin-station-copy"><p class="checkin-eyebrow">Beneficiary check-in</p><h3>Verify a beneficiary</h3><p>Scan their QR code, confirm the name and beneficiary number, then direct them to the release desk.</p>
                <ol><li>Ask to see the beneficiary QR code.</li><li>Scan and confirm their details.</li><li>Send them to staff to receive their assigned package.</li></ol>
                <button class="primary-action checkin-scan-button" type="button" :disabled="!isOnline" @click="openScanner">{{ isOnline ? 'Scan QR code' : 'Connection required' }}</button>
            </div>
        </div>
    </section>

    <section v-if="canViewDistributionHistory" class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Distribution history</h3><p>{{ distributions.total }}{{ search || statusFilter !== 'all' ? ' matching' : ' total' }} distribution records</p></div>
            <form class="module-search-form" role="search" @submit.prevent="searchDistributions()">
                <label class="sr-only" for="distribution-search">Search distributions</label>
                <select v-model="statusFilter" class="filter-select distribution-status-filter" aria-label="Filter distributions by status" @change="searchDistributions(true)"><option value="all">All statuses</option><option value="Pending">Pending</option><option value="Released">Released</option><option value="Cancelled">Cancelled</option><option value="Voided">Voided</option></select>
                <input id="distribution-search" v-model="searchTerm" class="table-search distribution-search" type="search" placeholder="Search beneficiary, package..." aria-label="Search distributions">
                <button class="action-btn" type="submit">Search</button>
                <button v-if="searchTerm || statusFilter !== 'all'" class="filter-clear" type="button" @click="searchTerm = ''; statusFilter = 'all'; searchDistributions()">Clear</button>
            </form>
        </div>
        <div class="table-wrap"><table class="record-table field-distribution-table">
            <thead><tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DISTRIBUTION WINDOW</th><th>DATE RELEASED</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
            <tbody>
                <tr v-for="distribution in rows" :key="distribution.id">
                    <td data-label="Beneficiary"><b>{{ distribution.beneficiary?.full_name ?? 'Unknown Beneficiary' }}</b><small>{{ distribution.beneficiary?.beneficiary_no ?? 'N/A' }}</small></td>
                    <td data-label="Package">{{ distribution.relief_package?.package_name ?? 'Unknown Package' }}</td>
                    <td data-label="Distribution window"><span>{{ formatDate(distribution.start_date) }} – {{ formatDate(distribution.deadline) }}</span></td>
                    <td data-label="Date">{{ formatDate(distribution.date_released) }}</td>
                    <td data-label="Status"><span class="tag" :class="{ success: distribution.status === 'Released', warning: distribution.status === 'Pending', neutral: ['Cancelled', 'Voided'].includes(distribution.status) }">{{ distribution.status }}</span></td>
                    <td data-label="Actions"><div v-if="canManageDistributions && ['Pending', 'Released'].includes(distribution.status)" class="inventory-row-actions"><button type="button" class="action-btn" @click="openEditModal(distribution)">Edit</button><button type="button" class="action-btn delete" @click="deleteDistribution(distribution)">{{ distribution.status === 'Pending' ? 'Cancel assignment' : 'Void record' }}</button></div><span v-else>{{ ['Cancelled', 'Voided'].includes(distribution.status) ? 'History kept' : 'Recorded' }}</span></td>
                </tr>
                <tr v-if="!rows.length"><td colspan="6" class="empty-cell">{{ search ? 'No distributions match this search.' : 'No distribution records yet.' }}</td></tr>
            </tbody>
        </table></div>
        <InertiaPagination :links="distributions.links" :show="distributions.last_page > 1" />
    </section>

    <QrScannerModal ref="scannerModal" :error="scanError" @decoded="handleScannedCode" />

    <dialog class="form-modal already-claimed-modal" id="already-claimed-modal" @click.self="$event.currentTarget.close()">
        <div class="modal-title"><div><h3>Already claimed</h3><p>{{ alreadyClaimedMessage }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="$event.currentTarget.closest('dialog')?.close()">×</button></div>
        <div class="modal-actions"><button class="primary-action" type="button" @click="$event.currentTarget.closest('dialog')?.close()">Close</button></div>
    </dialog>

    <dialog class="form-modal" id="beneficiary-check-modal">
        <div class="modal-title"><h3>Beneficiary verified</h3><p>Confirm the person’s details and direct them to the staff release desk.</p></div>
        <div v-if="scannedBeneficiary" class="beneficiary-check-details">
            <p><strong>Name</strong><span>{{ scannedBeneficiary.full_name }}</span></p>
            <p><strong>Beneficiary number</strong><span>{{ scannedBeneficiary.beneficiary_no }}</span></p>
            <p v-if="scannedBeneficiary.purok"><strong>Sitio/Purok</strong><span>{{ scannedBeneficiary.purok }}</span></p>
            <p><strong>Household size</strong><span>{{ scannedBeneficiary.household_size ?? 'Not recorded' }}</span></p>
            <p><strong>Priority</strong><span>{{ scannedBeneficiary.priority_type ?? 'Regular' }}</span></p>
            <p><strong>Status</strong><span>{{ scannedBeneficiary.status }}</span></p>
            <p v-if="scannedBeneficiary.pending_distributions?.length"><strong>Pending package</strong><span>{{ scannedBeneficiary.pending_distributions.map((item) => item.package_name).join(', ') }}</span></p>
        </div>
        <div class="modal-actions"><button class="primary-action" type="button" @click="closeBeneficiaryCheck">Done</button></div>
    </dialog>

    <dialog class="form-modal pending-release-modal" id="pending-release-modal" @click.self="$event.currentTarget.close()">
        <div class="modal-title"><div><h3>Choose package to release</h3><p>{{ pendingBeneficiary?.full_name }} · Release will be confirmed by the server.</p></div><button type="button" class="modal-close" aria-label="Close" @click="$event.currentTarget.closest('dialog')?.close()">×</button></div>
        <div class="pending-release-options">
            <button v-for="assignment in pendingReleaseOptions" :key="assignment.id" type="button" class="pending-release-option" @click="releasePendingDistribution(assignment)">
                <strong>{{ assignment.package_name }}</strong><small>Assigned {{ assignment.assigned_date || 'date unavailable' }}</small><small v-if="assignment.start_date || assignment.deadline">Collection {{ assignment.start_date || 'any time' }} to {{ assignment.deadline || 'no deadline' }}</small>
            </button>
            <small v-if="pendingReleaseError" class="field-error">{{ pendingReleaseError }}</small>
        </div>
    </dialog>

    <AdminModal v-if="canAssignDistributions" ref="modal" class="form-modal" @click.self="modal?.close()" @close="recordModalOpen = false">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit Distribution' : 'Record Distribution' }}</h3><p>{{ editingId ? 'Update distribution information.' : 'Record a relief package distribution to a beneficiary.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="saveDistribution">
            <label>Recipients<select v-model="form.recipient_mode" :disabled="!!editingId" required><option value="individual">One beneficiary</option><option value="all">All active beneficiaries</option><option value="priority">Priority group</option></select><small v-if="form.errors.recipient_mode" class="field-error">{{ form.errors.recipient_mode }}</small></label>
            <label v-if="form.recipient_mode === 'individual'">Beneficiary<div class="beneficiary-scan-row"><select v-model="form.beneficiary_id" required><option value="">Select beneficiary</option><option v-for="beneficiary in beneficiaries" :key="beneficiary.id" :value="beneficiary.id">{{ beneficiary.full_name }} ({{ beneficiary.beneficiary_no }})</option></select><button type="button" class="action-btn" :disabled="!isOnline" @click="openScanner">Scan QR</button></div><small v-if="form.errors.beneficiary_id" class="field-error">{{ form.errors.beneficiary_id }}</small></label>
            <label v-if="form.recipient_mode === 'priority'">Priority group<select v-model="form.priority_type" required><option value="">Choose group</option><option>PWD</option><option>Solo Parent</option><option>Senior Citizen</option></select><small v-if="form.errors.priority_type" class="field-error">{{ form.errors.priority_type }}</small></label>
            <p v-if="form.recipient_mode !== 'individual'" class="recipient-count">This will create {{ recipientCount }} distribution record(s), one per active beneficiary.</p>
            <label>Relief Package<select v-model="form.package_id" required><option value="">Select package</option><option v-for="item in packageOptions" :key="item.id" :value="item.id">{{ item.package_name }} — {{ item.available_quantity ?? '—' }} packs ready</option></select><small v-if="form.errors.package_id" class="field-error">{{ form.errors.package_id }}</small></label>
            <p v-if="insufficientPackageStock" class="recipient-warning">This package has {{ selectedPackage.available_quantity }} pack(s) ready, but {{ recipientCount }} active beneficiaries are selected. Add stock or choose a smaller group.</p>
            <div class="distribution-window-fields">
                <label>Start of distribution<input v-model="form.start_date" type="date" :max="form.deadline || undefined" required><small v-if="form.errors.start_date" class="field-error">{{ form.errors.start_date }}</small></label>
                <label>Deadline<input v-model="form.deadline" type="date" :min="form.start_date || undefined" required><small v-if="form.errors.deadline" class="field-error">{{ form.errors.deadline }}</small></label>
            </div>
            <div class="distribution-pending-status"><strong>{{ form.status === 'Released' ? 'Released — scan confirmed' : 'Pending — QR scan required' }}</strong><span>{{ form.status === 'Released' ? 'This record was already marked released after a QR scan. Its status cannot be changed here.' : 'Recording an assignment does not confirm pickup. It will be marked released only after staff scan the beneficiary’s QR code.' }}</span></div>
            <label>Notes<textarea v-model="form.notes" rows="3"/><small v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing || !isOnline || recipientCount === 0 || insufficientPackageStock">{{ form.processing ? 'Saving…' : editingId ? 'Update Distribution' : 'Record Distribution' }}</button></div>
        </form>
    </AdminModal>
</template>

<style scoped>
.recipient-count { margin: -4px 0 4px; color: #52657f; font-size: 12px; }
.distribution-window-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 480px) { .distribution-window-fields { grid-template-columns: 1fr; gap: 0; } }
.recipient-warning { margin: -5px 0 4px; color: #a53d00; font-size: 12px; }
.distribution-pending-status { display: grid; gap: 5px; padding: 11px 12px; border: 1px solid #f0ddb9; border-radius: 9px; background: #fffaf0; color: #77551b; }
.distribution-pending-status strong { font-size: 12px; }
.distribution-pending-status span { color: #7b6a4d; font-size: 11px; font-weight: 400; line-height: 1.5; }
.connection-status { display: flex; align-items: center; gap: 11px; margin: 0 0 16px; padding: 12px 15px; border: 1px solid #cfe7d9; border-radius: 10px; background: #f2faf5; color: #2e6043; }
.connection-status.is-disconnected { border-color: #f1d4a7; background: #fff9ed; color: #805b1b; }
.connection-indicator { width: 9px; height: 9px; flex: 0 0 9px; border-radius: 50%; background: #2f9d62; }
.is-disconnected .connection-indicator { background: #d18b21; }
.connection-status div { display: grid; gap: 2px; }
.connection-status strong { font-size: 12px; }
.connection-status span:not(.connection-indicator) { font-size: 11px; }
.module-heading-actions button:disabled { cursor: not-allowed; opacity: .55; }
.already-claimed-modal { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #e5eaf0; border-radius: 14px; box-shadow: 0 24px 64px rgba(15, 35, 60, .2); }
.pending-release-modal { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #e5eaf0; border-radius: 14px; box-shadow: 0 24px 64px rgba(15, 35, 60, .2); }
.pending-release-options { display: grid; gap: 8px; padding: 16px 20px 20px; }
.pending-release-option { display: grid; gap: 4px; padding: 12px; border: 1px solid #e1e8f0; border-radius: 9px; background: #f8fafc; color: #29496f; text-align: left; cursor: pointer; }
.pending-release-option:hover { border-color: #8fb5f8; background: #f1f6ff; }
.pending-release-option small { color: #718096; }
.checkin-station { display: grid; grid-template-columns: 64px minmax(0, 1fr); gap: 20px; align-items: start; max-width: 760px; margin: 28px auto; padding: 28px; }
.checkin-station-icon { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 18px; background: #eaf2ff; color: #2864c8; }
.checkin-station-icon svg { width: 30px; height: 30px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.checkin-eyebrow { margin: 0 0 7px; color: #2864c8; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.checkin-station-copy h3 { margin: 0 0 7px; color: #173b67; font-size: 21px; }
.checkin-station-copy > p:not(.checkin-eyebrow) { max-width: 560px; margin: 0; color: #667991; font-size: 13px; line-height: 1.6; }
.checkin-station-copy ol { display: grid; gap: 10px; margin: 20px 0; padding-left: 22px; color: #536780; font-size: 12px; line-height: 1.5; }
.checkin-scan-button { min-width: 160px; min-height: 44px; background: #245fba; border-color: #245fba; color: #fff; }
.checkin-scan-button:hover:not(:disabled) { background: #1c4f9e; border-color: #1c4f9e; }
.checkin-scan-button:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 640px) {
    .checkin-station { grid-template-columns: 1fr; gap: 14px; margin: 14px auto; padding: 21px; }
    .checkin-station-icon { width: 52px; height: 52px; border-radius: 14px; }
    .checkin-station-copy h3 { font-size: 19px; }
    .checkin-scan-button { width: 100%; min-height: 48px; }
    .field-distribution-table { min-width: 0; width: 100%; }
    .field-distribution-table thead { display: none; }
    .field-distribution-table, .field-distribution-table tbody { display: block; }
    .field-distribution-table tbody { padding: 10px; }
    .field-distribution-table tbody tr { display: grid; gap: 0; margin-bottom: 10px; padding: 8px 13px; border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; }
    .field-distribution-table tbody tr:last-child { margin-bottom: 0; }
    .field-distribution-table td { display: grid; grid-template-columns: 88px minmax(0, 1fr); align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #edf2f7; white-space: normal; overflow-wrap: anywhere; }
    .field-distribution-table td:first-child { border-top: 0; }
    .field-distribution-table td::before { content: attr(data-label); color: #8290a5; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .field-distribution-table td.empty-cell { display: block; padding: 22px 12px; }
    .field-distribution-table td.empty-cell::before { content: none; }
}
</style>
