<script setup>
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { nextTick, onBeforeUnmount, ref } from 'vue';
import AdminModal from './AdminModal.vue';

defineProps({
    error: { type: String, default: '' },
});
const emit = defineEmits(['decoded']);

const SCANNER_ELEMENT_ID = 'qr-scanner-region';
const modal = ref(null);
const starting = ref(false);
const cameraError = ref('');
let scanner = null;
let lastDecoded = '';
let lastDecodedAt = 0;

async function open() {
    cameraError.value = '';
    lastDecoded = '';
    lastDecodedAt = 0;
    modal.value?.showModal();
    await nextTick();
    await startScanner();
}

async function startScanner() {
    if (scanner) return;
    starting.value = true;
    try {
        scanner = new Html5Qrcode(SCANNER_ELEMENT_ID, {
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
            verbose: false,
        });
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 220, height: 220 } },
            handleDecoded,
            () => {},
        );
    } catch (error) {
        cameraError.value = friendlyCameraError(error);
        await stopScanner();
    } finally {
        starting.value = false;
    }
}

function handleDecoded(text) {
    const now = Date.now();
    if (text === lastDecoded && now - lastDecodedAt < 2500) return;
    lastDecoded = text;
    lastDecodedAt = now;
    emit('decoded', text);
}

async function stopScanner() {
    if (!scanner) return;
    const active = scanner;
    scanner = null;
    try {
        if (active.isScanning) await active.stop();
        active.clear();
    } catch {
        // already stopped
    }
}

function close() {
    stopScanner();
    modal.value?.close();
}

function onDialogClose() {
    stopScanner();
}

function friendlyCameraError(error) {
    if (!window.isSecureContext) return 'The camera needs a secure connection (HTTPS or localhost).';
    const name = error?.name ?? '';
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Camera access was blocked. Allow camera permission for this site, then try again.';
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No camera was found on this device.';
    if (name === 'NotReadableError' || name === 'TrackStartError') return 'The camera is already in use by another application.';
    return 'The camera could not be started. Please try again.';
}

onBeforeUnmount(() => { stopScanner(); });

defineExpose({ open, close });
</script>

<template>
    <AdminModal ref="modal" class="form-modal qr-scanner-modal" @close="onDialogClose" @click.self="close">
        <div class="modal-title">
            <div><h3>Scan beneficiary QR</h3><p>Point the camera at the beneficiary's QR pass.</p></div>
            <button type="button" class="modal-close" aria-label="Close" @click="close">×</button>
        </div>
        <div class="qr-scanner-body">
            <div :id="SCANNER_ELEMENT_ID" class="qr-scanner-region" />
            <p v-if="cameraError" class="field-error qr-scanner-error">{{ cameraError }}</p>
            <p v-else-if="error" class="field-error qr-scanner-error">{{ error }}</p>
            <p v-else-if="starting" class="qr-scanner-hint">Starting camera…</p>
            <p v-else class="qr-scanner-hint">Align the QR code inside the frame.</p>
            <div class="modal-actions"><button type="button" class="cancel-button" @click="close">Cancel</button></div>
        </div>
    </AdminModal>
</template>
