<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminModal from '../../Components/AdminModal.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import QrScannerModal from '../../Components/QrScannerModal.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    distributions: { type: Object, required: true },
    beneficiaries: { type: Array, required: true },
    packages: { type: Array, required: true },
    search: { type: String, default: '' },
    today: { type: String, required: true },
    canManageDistributions: { type: Boolean, default: false },
    canAssignDistributions: { type: Boolean, default: false },
    canReleasePackages: { type: Boolean, default: false },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const searchTerm = ref(props.search);
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
const offlineAssignments = ref([]);
const offlinePreparedAt = ref('');
const offlineClaims = ref([]);
const confirmedDeviceClaims = ref([]);
const offlineMessage = ref('');
const offlineBusy = ref(false);
// Confirmed claims on this page remain blocked if the connection drops.
// The server remains authoritative for claims made on any other device.
const confirmedClaimsThisSession = new Set();
const editingId = ref(null);
const form = useForm({ recipient_mode: 'individual', beneficiary_id: '', priority_type: '', package_id: '', date_released: '', status: 'Released', notes: '' });
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
const offlineKey = `relieftrack-offline-${page.props.auth?.user?.id ?? 'staff'}`;

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
    try {
        const saved = JSON.parse(localStorage.getItem(offlineKey) ?? '{}');
        offlineAssignments.value = Array.isArray(saved.assignments) ? saved.assignments : [];
        offlineClaims.value = Array.isArray(saved.claims) ? saved.claims : [];
        confirmedDeviceClaims.value = Array.isArray(saved.confirmed) ? saved.confirmed : [];
        offlinePreparedAt.value = saved.preparedAt ?? '';
    } catch {
        offlineAssignments.value = [];
        offlineClaims.value = [];
        confirmedDeviceClaims.value = [];
    }
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

function saveOfflineState() {
    localStorage.setItem(offlineKey, JSON.stringify({
        assignments: offlineAssignments.value,
        claims: offlineClaims.value,
        confirmed: confirmedDeviceClaims.value,
        preparedAt: offlinePreparedAt.value,
    }));
}

async function prepareOfflineDistribution() {
    if (!isOnline.value) return;
    offlineBusy.value = true;
    offlineMessage.value = '';
    try {
        const response = await fetch(routes.value.distributionOfflineAssignments, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Could not download assigned households.');
        const result = await response.json();
        offlineAssignments.value = Array.isArray(result.data) ? result.data : [];
        offlinePreparedAt.value = result.prepared_at ?? new Date().toISOString();
        saveOfflineState();
        offlineMessage.value = `Saved ${offlineAssignments.value.length} pending assignment(s) on this device.`;
    } catch (error) {
        offlineMessage.value = error.message || 'Could not prepare offline data.';
    } finally {
        offlineBusy.value = false;
    }
}

async function syncOfflineClaims() {
    if (!isOnline.value || !offlineClaims.value.length || offlineBusy.value) return;
    offlineBusy.value = true;
    offlineMessage.value = '';
    try {
        const response = await fetch(routes.value.distributionOfflineSync, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: JSON.stringify({ claims: offlineClaims.value.slice(0, 200).map(({ beneficiary_no, distribution_id, offline_claim_id }) => ({ beneficiary_no, distribution_id, offline_claim_id })) }),
        });
        if (!response.ok) throw new Error(response.status === 419 ? 'Your staff session expired. Sign in again, then sync.' : 'Could not sync offline claims.');
        const result = await response.json();
        const byId = new Map((result.results ?? []).map((item) => [Number(item.distribution_id), item]));
        offlineClaims.value.forEach((claim) => {
            if (byId.get(Number(claim.distribution_id))?.success) rememberConfirmedClaim(claim.beneficiary_no);
        });
        offlineClaims.value = offlineClaims.value
            .map((claim) => ({ ...claim, syncMessage: byId.get(Number(claim.distribution_id))?.message ?? 'Awaiting server response.' }))
            .filter((claim) => !byId.get(Number(claim.distribution_id))?.success);
        saveOfflineState();
        const confirmed = (result.results ?? []).filter((item) => item.success).length;
        offlineMessage.value = `${confirmed} claim(s) confirmed. ${offlineClaims.value.length} need staff review or another sync.`;
    } catch (error) {
        offlineMessage.value = error.message || 'Could not sync offline claims.';
    } finally {
        offlineBusy.value = false;
    }
}

function rememberConfirmedClaim(beneficiaryNo) {
    const normalized = String(beneficiaryNo ?? '').trim().toUpperCase();
    if (!normalized) return;
    confirmedClaimsThisSession.add(normalized);
    const claimDate = localDateKey();
    if (!confirmedDeviceClaims.value.some((claim) => claim.beneficiary_no === normalized && claim.date === claimDate)) {
        confirmedDeviceClaims.value.push({ beneficiary_no: normalized, date: claimDate });
    }
    saveOfflineState();
}

function localDateKey() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function clearOfflineData() {
    if (offlineClaims.value.length) return;
    offlineAssignments.value = [];
    confirmedDeviceClaims.value = [];
    offlinePreparedAt.value = '';
    offlineMessage.value = 'Prepared offline data cleared from this device.';
    saveOfflineState();
}

function searchDistributions(replace = false) {
    lastSubmittedSearch = searchTerm.value.trim();
    clearTimeout(searchTimer);
    router.get(routes.value.distribution, { search: lastSubmittedSearch || undefined }, { preserveState: true, preserveScroll: true, replace });
}

function openAddModal(beneficiaryId = '') {
    if (!props.canAssignDistributions) return;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.date_released = props.today;
    form.status = 'Released';
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
    form.date_released = String(distribution.date_released).slice(0, 10);
    form.status = distribution.status;
    form.notes = distribution.notes ?? '';
    modal.value?.showModal();
}

function openScanner() {
    scanError.value = '';
    scannerModal.value?.open();
}

function closeBeneficiaryCheck() {
    document.getElementById('beneficiary-check-modal')?.close();
    scannedBeneficiary.value = null;
}

async function handleScannedCode(text) {
    const code = (text ?? '').trim();
    const normalizedCode = code.toUpperCase();
    const todayKey = localDateKey();
    if (confirmedClaimsThisSession.has(normalizedCode) || confirmedDeviceClaims.value.some((claim) => claim.beneficiary_no === normalizedCode && claim.date === todayKey)) {
        alreadyClaimedMessage.value = 'This beneficiary was already confirmed as claimed on this device today.';
        document.getElementById('already-claimed-modal')?.showModal();
        return;
    }
    const queued = offlineClaims.value.find((claim) => claim.beneficiary_no.toUpperCase() === normalizedCode);
    if (queued) {
        alreadyClaimedMessage.value = 'A claim for this beneficiary is already saved on this device and is waiting to sync.';
        document.getElementById('already-claimed-modal')?.showModal();
        return;
    }
    if (!navigator.onLine) {
        if (!openOfflineAssignments(normalizedCode)) scanError.value = 'This QR is not in the offline list on this device. Reconnect or refer the person to staff for manual review.';
        return;
    }
    let match = null;
    let lookupUnavailable = false;
    let response;
    try {
        response = await fetch(`/admin/distribution/lookup?code=${encodeURIComponent(code)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
    } catch {
        lookupUnavailable = true;
    }
    if (lookupUnavailable && openOfflineAssignments(normalizedCode)) {
        return;
    }
    if (response?.ok) {
        try {
            const result = await response.json();
            match = result.data ?? null;
        } catch {
            match = null;
        }
    }
    if (!match) {
        scanError.value = `No active beneficiary found for "${code}".`;
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
        pendingBeneficiary.value = match;
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

function openOfflineAssignments(normalizedCode) {
    const assignments = offlineAssignments.value.filter((item) => item.beneficiary_no.toUpperCase() === normalizedCode);
    if (!assignments.length) return false;
    isOnline.value = false;
    scannerModal.value?.close();
    pendingBeneficiary.value = { beneficiary_no: assignments[0].beneficiary_no, full_name: assignments[0].full_name };
    pendingReleaseOptions.value = assignments.map((item) => ({ id: item.distribution_id, package_name: item.package_name, assigned_date: item.assigned_date }));
    pendingReleaseError.value = '';
    if (pendingReleaseOptions.value.length === 1) releasePendingDistribution(pendingReleaseOptions.value[0]);
    else document.getElementById('pending-release-modal')?.showModal();
    return true;
}

function releasePendingDistribution(assignment) {
    if (!pendingBeneficiary.value || !assignment) return;
    pendingReleaseError.value = '';
    if (!isOnline.value) {
        if (offlineClaims.value.some((claim) => Number(claim.distribution_id) === Number(assignment.id))) {
            pendingReleaseError.value = 'This package is already recorded on this device.';
            return;
        }
        offlineClaims.value.push({
            beneficiary_no: pendingBeneficiary.value.beneficiary_no,
            full_name: pendingBeneficiary.value.full_name,
            distribution_id: assignment.id,
            offline_claim_id: createOfflineClaimId(),
            package_name: assignment.package_name,
            recorded_at: new Date().toISOString(),
            syncMessage: 'Saved offline. Not confirmed until synced.',
        });
        saveOfflineState();
        offlineMessage.value = 'Claim saved offline. It is pending and not yet confirmed by the database.';
        document.getElementById('pending-release-modal')?.close();
        pendingReleaseOptions.value = [];
        pendingBeneficiary.value = null;
        return;
    }
    router.post(routes.value.distributionReleasePending, {
        beneficiary_no: pendingBeneficiary.value.beneficiary_no,
        distribution_id: assignment.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const code = pendingBeneficiary.value?.beneficiary_no?.trim().toUpperCase();
            if (code) rememberConfirmedClaim(code);
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

function createOfflineClaimId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.random() * 16 | 0;
        return (character === 'x' ? random : (random & 0x3 | 0x8)).toString(16);
    });
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
        <div><h2>{{ canAssignDistributions ? 'Distribution records' : canReleasePackages ? 'Beneficiary check-in' : 'Beneficiary verification' }}</h2><p>{{ canAssignDistributions ? 'Assign packages to beneficiaries or record a direct release.' : canReleasePackages ? 'Scan a beneficiary QR code to verify their identity and release an assigned package. New assignments are handled by an administrator.' : 'Scan a beneficiary QR code to verify their identity before directing them to the distribution desk.' }}</p></div>
        <div class="module-heading-actions">
            <button class="add-button" type="button" @click="openScanner">{{ canReleasePackages ? 'Scan QR' : 'Scan to verify' }}</button>
            <button v-if="canAssignDistributions" class="add-button" type="button" @click="openAddModal()"><span>+</span> Record Distribution</button>
        </div>
    </section>

    <section v-if="canReleasePackages" class="panel offline-panel">
        <div class="offline-panel-copy">
            <strong>{{ isOnline ? 'Online' : 'Offline mode' }}</strong>
            <span v-if="offlinePreparedAt">Offline list prepared {{ formatDate(offlinePreparedAt) }} · {{ offlineAssignments.length }} assignment(s)</span>
            <span v-else>Prepare the assigned QR list before signal is lost. Keep this page open during offline distribution.</span>
            <span v-if="offlineClaims.length">{{ offlineClaims.length }} claim(s) saved on this device; they are not confirmed yet.</span>
            <span v-if="offlineMessage" role="status">{{ offlineMessage }}</span>
            <span v-for="claim in offlineClaims" :key="claim.distribution_id" class="offline-claim-status">{{ claim.full_name }} — {{ claim.package_name }}: {{ claim.syncMessage }}</span>
        </div>
        <div class="offline-actions">
            <button class="action-btn" type="button" :disabled="!isOnline || offlineBusy" @click="prepareOfflineDistribution">{{ offlineBusy ? 'Working…' : 'Prepare for offline' }}</button>
            <button v-if="offlineClaims.length" class="add-button" type="button" :disabled="!isOnline || offlineBusy" @click="syncOfflineClaims">Sync pending claims</button>
            <button v-if="isOnline && !offlineClaims.length && offlineAssignments.length" class="action-btn" type="button" @click="clearOfflineData">Clear device data</button>
        </div>
    </section>

    <section v-if="!canReleasePackages" class="panel record-panel">
        <div class="panel-heading"><div><h3>Check-in station</h3><p>Scan the beneficiary QR code, confirm the name and beneficiary number, then direct them to staff for package release.</p></div></div>
    </section>

    <section v-if="canReleasePackages" class="panel record-panel">
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
                    <td><div v-if="canManageDistributions" class="inventory-row-actions"><button type="button" class="action-btn" @click="openEditModal(distribution)">Edit</button><button type="button" class="action-btn delete" @click="deleteDistribution(distribution)">Delete</button></div><span v-else>Recorded</span></td>
                </tr>
                <tr v-if="!rows.length"><td colspan="5" class="empty-cell">{{ search ? 'No distributions match this search.' : 'No distribution records yet.' }}</td></tr>
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
            <p><strong>Household size</strong><span>{{ scannedBeneficiary.household_size ?? 'Not recorded' }}</span></p>
            <p><strong>Priority</strong><span>{{ scannedBeneficiary.priority_type ?? 'Regular' }}</span></p>
            <p><strong>Status</strong><span>{{ scannedBeneficiary.status }}</span></p>
            <p v-if="scannedBeneficiary.pending_distributions?.length"><strong>Pending package</strong><span>{{ scannedBeneficiary.pending_distributions.map((item) => item.package_name).join(', ') }}</span></p>
        </div>
        <div class="modal-actions"><button class="primary-action" type="button" @click="closeBeneficiaryCheck">Done</button></div>
    </dialog>

    <dialog class="form-modal pending-release-modal" id="pending-release-modal" @click.self="$event.currentTarget.close()">
        <div class="modal-title"><div><h3>{{ isOnline ? 'Choose package to release' : 'Record offline claim' }}</h3><p>{{ pendingBeneficiary?.full_name }} · {{ isOnline ? 'This will be confirmed by the server.' : 'Saved on this device; confirmation waits for sync.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="$event.currentTarget.closest('dialog')?.close()">×</button></div>
        <div class="pending-release-options">
            <button v-for="assignment in pendingReleaseOptions" :key="assignment.id" type="button" class="pending-release-option" @click="releasePendingDistribution(assignment)">
                <strong>{{ assignment.package_name }}</strong><small>Assigned {{ assignment.assigned_date || 'date unavailable' }}</small>
            </button>
            <small v-if="pendingReleaseError" class="field-error">{{ pendingReleaseError }}</small>
        </div>
    </dialog>

    <AdminModal v-if="canAssignDistributions" ref="modal" class="form-modal" @click.self="modal?.close()" @close="recordModalOpen = false">
        <div class="modal-title"><div><h3>{{ editingId ? 'Edit Distribution' : 'Record Distribution' }}</h3><p>{{ editingId ? 'Update distribution information.' : 'Record a relief package distribution to a beneficiary.' }}</p></div><button type="button" class="modal-close" aria-label="Close" @click="modal?.close()">×</button></div>
        <form @submit.prevent="saveDistribution">
            <label>Recipients<select v-model="form.recipient_mode" :disabled="!!editingId" required><option value="individual">One beneficiary</option><option value="all">All active beneficiaries</option><option value="priority">Priority group</option></select><small v-if="form.errors.recipient_mode" class="field-error">{{ form.errors.recipient_mode }}</small></label>
            <label v-if="form.recipient_mode === 'individual'">Beneficiary<div class="beneficiary-scan-row"><select v-model="form.beneficiary_id" required><option value="">Select beneficiary</option><option v-for="beneficiary in beneficiaries" :key="beneficiary.id" :value="beneficiary.id">{{ beneficiary.full_name }} ({{ beneficiary.beneficiary_no }})</option></select><button type="button" class="action-btn" @click="openScanner">Scan QR</button></div><small v-if="form.errors.beneficiary_id" class="field-error">{{ form.errors.beneficiary_id }}</small></label>
            <label v-if="form.recipient_mode === 'priority'">Priority group<select v-model="form.priority_type" required><option value="">Choose group</option><option>PWD</option><option>Solo Parent</option><option>Senior Citizen</option></select><small v-if="form.errors.priority_type" class="field-error">{{ form.errors.priority_type }}</small></label>
            <p v-if="form.recipient_mode !== 'individual'" class="recipient-count">This will create {{ recipientCount }} distribution record(s), one per active beneficiary.</p>
            <label>Relief Package<select v-model="form.package_id" required><option value="">Select package</option><option v-for="item in packageOptions" :key="item.id" :value="item.id">{{ item.package_name }} — {{ item.available_quantity ?? '—' }} packs ready</option></select><small v-if="form.errors.package_id" class="field-error">{{ form.errors.package_id }}</small></label>
            <p v-if="insufficientPackageStock" class="recipient-warning">This package has {{ selectedPackage.available_quantity }} pack(s) ready, but {{ recipientCount }} active beneficiaries are selected. Add stock or choose a smaller group.</p>
            <label>Date Released<input v-model="form.date_released" type="date" required><small v-if="form.errors.date_released" class="field-error">{{ form.errors.date_released }}</small></label>
            <label>Distribution status<select v-model="form.status" required><option value="Released">Release now</option><option value="Pending">Assign as pending</option></select><small v-if="form.status === 'Pending'" class="recipe-help">Pending assignments reserve their package stock and appear in the beneficiary’s mobile relief history. Scan their QR code at pickup to mark the package released.</small><small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small></label>
            <label>Notes<textarea v-model="form.notes" rows="3"/><small v-if="form.errors.notes" class="field-error">{{ form.errors.notes }}</small></label>
            <div class="modal-actions"><button type="button" class="cancel-button" :disabled="form.processing" @click="modal?.close()">Cancel</button><button class="primary-action" type="submit" :disabled="form.processing || recipientCount === 0 || insufficientPackageStock">{{ form.processing ? 'Saving…' : editingId ? 'Update Distribution' : 'Record Distribution' }}</button></div>
        </form>
    </AdminModal>
</template>

<style scoped>
.recipient-count { margin: -4px 0 4px; color: #52657f; font-size: 12px; }
.recipient-warning { margin: -5px 0 4px; color: #a53d00; font-size: 12px; }
.offline-panel { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin: 0 0 18px; padding: 16px 18px; border-color: #cbd9ea; }
.offline-panel-copy { display: grid; gap: 5px; color: #52657f; font-size: 12px; }
.offline-panel-copy strong { color: #173b67; font-size: 14px; }
.offline-claim-status { color: #9a5a00; }
.offline-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.offline-actions button:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 680px) { .offline-panel { flex-direction: column; } }
.already-claimed-modal { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #e5eaf0; border-radius: 14px; box-shadow: 0 24px 64px rgba(15, 35, 60, .2); }
.pending-release-modal { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; border: 1px solid #e5eaf0; border-radius: 14px; box-shadow: 0 24px 64px rgba(15, 35, 60, .2); }
.pending-release-options { display: grid; gap: 8px; padding: 16px 20px 20px; }
.pending-release-option { display: grid; gap: 4px; padding: 12px; border: 1px solid #e1e8f0; border-radius: 9px; background: #f8fafc; color: #29496f; text-align: left; cursor: pointer; }
.pending-release-option:hover { border-color: #8fb5f8; background: #f1f6ff; }
.pending-release-option small { color: #718096; }
</style>
