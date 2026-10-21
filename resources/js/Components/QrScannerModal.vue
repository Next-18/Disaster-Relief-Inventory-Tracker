<script setup>
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { nextTick, onBeforeUnmount, ref } from 'vue';
import AdminModal from './AdminModal.vue';

defineProps({
    error: { type: String, default: '' },
});
const emit = defineEmits(['decoded']);

const SCANNER_ELEMENT_ID = 'qr-scanner-region';
const SCANNER_OPTIONS = {
    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
    verbose: false,
    rememberLastUsedCamera: true,
    useBarCodeDetectorIfSupported: true,
};
const modal = ref(null);
const starting = ref(false);
const cameraError = ref('');
const manualCode = ref('');
const imageInput = ref(null);
let scanner = null;
let lastDecoded = '';
let lastDecodedAt = 0;

// No qrbox: the decoder scans the whole camera frame, so the QR pass
// only has to be somewhere in view — no aligning into a target box.
const SCAN_CONFIG = { fps: 15 };
// html5-qrcode only allows a single facingMode/deviceId key in the first
// argument — extra constraints (resolution etc.) must go via videoConstraints.
const VIDEO_CONSTRAINT_ATTEMPTS = [
    // focusMode keeps the pass sharp wherever it's held; browsers that
    // don't support it ignore the preference.
    { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 }, focusMode: 'continuous', exposureMode: 'continuous' },
    { facingMode: 'environment', focusMode: 'continuous' },
    { facingMode: 'environment' },
    { facingMode: 'user' },
];

async function open() {
    cameraError.value = '';
    manualCode.value = '';
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
        scanner = new Html5Qrcode(SCANNER_ELEMENT_ID, SCANNER_OPTIONS);
        await startCamera(scanner);
    } catch (error) {
        cameraError.value = friendlyCameraError(error);
        await stopScanner();
    } finally {
        starting.value = false;
    }
}

async function startCamera(activeScanner) {
    for (const videoConstraints of VIDEO_CONSTRAINT_ATTEMPTS) {
        try {
            await activeScanner.start(
                { facingMode: 'environment' },
                { ...SCAN_CONFIG, videoConstraints },
                handleDecoded,
                () => {},
            );
            return;
        } catch {
            // constraint set not satisfiable — try the next one
        }
    }

    const cameras = await Html5Qrcode.getCameras();
    const ordered = [...(cameras ?? [])].sort(
        (a, b) => Number(/back|rear|environment/i.test(b.label ?? '')) - Number(/back|rear|environment/i.test(a.label ?? '')),
    );
    for (const camera of ordered) {
        try {
            await activeScanner.start(camera.id, SCAN_CONFIG, handleDecoded, () => {});
            return;
        } catch {
            // try the next camera
        }
    }
    throw new Error('No usable camera found.');
}

function retryCamera() {
    cameraError.value = '';
    startScanner();
}

function handleDecoded(text) {
    const now = Date.now();
    if (text === lastDecoded && now - lastDecodedAt < 2500) return;
    lastDecoded = text;
    lastDecodedAt = now;
    emit('decoded', text);
}

function submitManualCode() {
    const code = manualCode.value.trim();
    if (!code) return;
    cameraError.value = '';
    emit('decoded', code);
}

async function uploadQrImage(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    cameraError.value = '';
    starting.value = true;
    await stopScanner();
    let text = '';
    try {
        scanner = new Html5Qrcode(SCANNER_ELEMENT_ID, SCANNER_OPTIONS);
        text = String(await scanner.scanFile(file, true)).trim();
    } catch {
        cameraError.value = 'No QR code could be read from that image.';
    } finally {
        await stopScanner();
        starting.value = false;
    }
    if (text) emit('decoded', text);
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
    // html5-qrcode rejects start() with a plain string like
    // "Error getting userMedia, error = NotAllowedError: Permission denied",
    // so match on the rendered text as well as DOMException names.
    const label = String(error?.name ?? error?.message ?? error ?? '');
    if (/NotAllowedError|PermissionDeniedError|Permission denied/i.test(label)) return 'Camera access was blocked. Allow camera permission for this site, then try again.';
    if (/NotFoundError|DevicesNotFoundError|No video input devices|Requested device not found|No usable camera/i.test(label)) return 'No camera was found on this device — enter the beneficiary number below instead.';
    if (/NotReadableError|TrackStartError|Could not start video source|already in use/i.test(label)) return 'The camera is already in use by another application.';
    return 'The camera could not be started. Enter the beneficiary number below or try again.';
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
            <p v-else class="qr-scanner-hint">Just hold the QR anywhere in view — it reads automatically. Raise the screen brightness if it won't pick up.</p>
            <button v-if="cameraError || error" type="button" class="qr-scanner-link" @click="retryCamera">Restart camera</button>

            <div class="qr-scanner-divider"><span>or type the number</span></div>
            <form class="qr-scanner-manual" @submit.prevent="submitManualCode">
                <input v-model="manualCode" type="text" placeholder="e.g. BEN-1020" aria-label="Beneficiary number" autocomplete="off">
                <button type="submit" class="action-btn" :disabled="!manualCode.trim()">Use code</button>
            </form>
            <button type="button" class="qr-scanner-link" @click="imageInput?.click()">Scan from a QR image instead</button>
            <input ref="imageInput" type="file" accept="image/*" class="sr-only" @change="uploadQrImage">
            <div class="modal-actions"><button type="button" class="cancel-button" @click="close">Cancel</button></div>
        </div>
    </AdminModal>
</template>
