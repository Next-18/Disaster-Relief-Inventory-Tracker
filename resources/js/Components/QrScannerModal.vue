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
    useBarCodeDetectorIfSupported: true,
};
const modal = ref(null);
const starting = ref(false);
const cameraError = ref('');
const manualCode = ref('');
const imageInput = ref(null);
const cameraControls = ref({ zoom: null, torch: false, torchSupported: false });
const detected = ref(false);
const cameraActive = ref(false);
const mirrored = ref(false);
const cameraList = ref([]);
let cameraIndex = 0;
let cameraCapabilities = null;
let scanner = null;
let detectFlashTimer = null;
let audioContext = null;
let processedCodes = new Set();

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
    processedCodes = new Set();
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
        loadCameraList();
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
            loadCameraControls(activeScanner);
            applyOrientation(activeScanner);
            return;
        } catch {
            // constraint set not satisfiable — try the next one
        }
    }

    const cameras = await Html5Qrcode.getCameras();
    const ordered = [...(cameras ?? [])].sort(
        (a, b) => Number(/back|rear|environment/i.test(b.label ?? '')) - Number(/back|rear|environment/i.test(a.label ?? '')),
    );
    for (const [index, camera] of ordered.entries()) {
        try {
            await activeScanner.start(camera.id, SCAN_CONFIG, handleDecoded, () => {});
            loadCameraControls(activeScanner);
            applyOrientation(activeScanner);
            cameraIndex = index;
            cameraList.value = ordered;
            return;
        } catch {
            // try the next camera
        }
    }
    throw new Error('No usable camera found.');
}

function loadCameraControls(activeScanner) {
    cameraCapabilities = null;
    cameraControls.value = { zoom: null, torch: false, torchSupported: false };
    try {
        const capabilities = activeScanner.getRunningTrackCameraCapabilities();
        const zoom = capabilities.zoomFeature();
        const controls = { zoom: null, torch: false, torchSupported: false };
        if (zoom.isSupported()) {
            controls.zoom = {
                min: zoom.min(),
                max: zoom.max(),
                step: zoom.step() || 0.1,
                value: zoom.value() ?? zoom.min(),
            };
        }
        const torch = capabilities.torchFeature();
        controls.torchSupported = torch.isSupported();
        controls.torch = torch.value() === true;
        cameraCapabilities = capabilities;
        cameraControls.value = controls;
    } catch {
        // capabilities unavailable — controls stay hidden
    }
    cameraActive.value = true;
}

// Front-facing ("user") cameras stream a mirrored preview; flip the
// display so the QR looks natural. Decoding is unaffected.
function applyOrientation(activeScanner) {
    try {
        mirrored.value = activeScanner.getRunningTrackSettings()?.facingMode === 'user';
    } catch {
        mirrored.value = false;
    }
}

async function loadCameraList() {
    if (cameraList.value.length) return;
    try {
        const cameras = await Html5Qrcode.getCameras();
        cameraList.value = [...(cameras ?? [])].sort(
            (a, b) => Number(/back|rear|environment/i.test(b.label ?? '')) - Number(/back|rear|environment/i.test(a.label ?? '')),
        );
    } catch {
        cameraList.value = [];
    }
}

async function switchCamera() {
    const list = cameraList.value;
    if (!scanner || list.length < 2) return;
    cameraError.value = '';
    cameraIndex = (cameraIndex + 1) % list.length;
    const next = list[cameraIndex];
    const active = scanner;
    scanner = null;
    try {
        if (active.isScanning) await active.stop();
        active.clear();
    } catch {
        // already stopped
    }
    try {
        scanner = new Html5Qrcode(SCANNER_ELEMENT_ID, SCANNER_OPTIONS);
        await scanner.start(next.id, SCAN_CONFIG, handleDecoded, () => {});
        loadCameraControls(scanner);
        applyOrientation(scanner);
    } catch (error) {
        cameraError.value = friendlyCameraError(error);
        await stopScanner();
    }
}

async function applyZoom() {
    try {
        await cameraCapabilities?.zoomFeature().apply(Number(cameraControls.value.zoom.value));
    } catch {
        // camera rejected the zoom value
    }
}

async function toggleTorch() {
    const next = !cameraControls.value.torch;
    try {
        await cameraCapabilities?.torchFeature().apply(next);
        cameraControls.value.torch = next;
    } catch {
        // torch not actually usable on this camera
    }
}

function retryCamera() {
    cameraError.value = '';
    startScanner();
}

// Each distinct QR is emitted at most once per modal open, so a code
// held in frame can't fire repeated lookups while one is being handled.
function handleDecoded(text) {
    const code = String(text ?? '').trim();
    if (!code || processedCodes.has(code)) return;
    processedCodes.add(code);
    flashDetected();
    emit('decoded', code);
}

function flashDetected() {
    detected.value = true;
    clearTimeout(detectFlashTimer);
    detectFlashTimer = setTimeout(() => { detected.value = false; }, 900);
    try {
        audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.15, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.18);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.18);
    } catch {
        // audio unavailable — flash still signals detection
    }
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
    cameraCapabilities = null;
    cameraControls.value = { zoom: null, torch: false, torchSupported: false };
    detected.value = false;
    cameraActive.value = false;
    mirrored.value = false;
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
            <div :id="SCANNER_ELEMENT_ID" class="qr-scanner-region" :class="{ detected, mirrored }" />
            <div v-if="cameraControls.zoom || cameraControls.torchSupported || cameraList.length > 1" class="qr-scanner-controls">
                <label v-if="cameraControls.zoom" class="qr-scanner-zoom">
                    <span>Zoom</span>
                    <input v-model="cameraControls.zoom.value" type="range" :min="cameraControls.zoom.min" :max="cameraControls.zoom.max" :step="cameraControls.zoom.step" @input="applyZoom">
                </label>
                <button v-if="cameraControls.torchSupported" type="button" class="qr-scanner-link" @click="toggleTorch">{{ cameraControls.torch ? 'Flashlight: on' : 'Flashlight: off' }}</button>
                <button v-if="cameraList.length > 1" type="button" class="qr-scanner-link" @click="switchCamera">Switch camera</button>
            </div>
            <p v-if="cameraError" class="field-error qr-scanner-error">{{ cameraError }}</p>
            <p v-else-if="error" class="field-error qr-scanner-error">{{ error }}</p>
            <p v-else-if="starting" class="qr-scanner-hint">Starting camera…</p>
            <p v-else-if="detected" class="qr-scanner-hint qr-scanner-success">QR detected — opening record…</p>
            <p v-else-if="cameraActive" class="qr-scanner-hint">Scanning… hold the QR anywhere in view — it reads automatically.</p>
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
