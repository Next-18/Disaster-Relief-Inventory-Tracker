<script setup>
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import * as ZXing from 'html5-qrcode/third_party/zxing-js.umd.js';
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
    // html5-qrcode alternates its native BarcodeDetector and ZXing decoders
    // when the browser supports both, retaining ZXing as a fallback.
    useBarCodeDetectorIfSupported: true,
};
const modal = ref(null);
const starting = ref(false);
const cameraError = ref('');
const manualCode = ref('');
const imageInput = ref(null);
const scannerStage = ref(null);
const trackingBox = ref(null);
const cameraControls = ref({ zoom: null, torch: false, torchSupported: false, exposure: null });
const detected = ref(false);
const cameraActive = ref(false);
const lowLight = ref(false);
const mirrored = ref(false);
const cameraList = ref([]);
let cameraIndex = 0;
let cameraCapabilities = null;
let cameraTrack = null;
let scanner = null;
let detectFlashTimer = null;
let audioContext = null;
let processedCodes = new Set();
let enhanceTimer = null;
let enhanceCanvas = null;
let zxingReader = null;
let darkFrames = 0;
let lightingTouchedByUser = false;
let barcodeDetector = null;
let qrTrackingTimer = null;
let lastTrackedAt = 0;

// No qrbox: the decoder scans the whole camera frame, so the QR pass
// only has to be somewhere in view — no aligning into a target box.
const SCAN_CONFIG = {
    fps: 20,
};
// html5-qrcode only allows a single facingMode/deviceId key in the first
// argument — extra constraints (resolution etc.) must go via videoConstraints.
const VIDEO_CONSTRAINT_ATTEMPTS = [
    // focusMode keeps the pass sharp wherever it's held; browsers that
    // don't support it ignore the preference.
    { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 }, focusMode: 'continuous', exposureMode: 'continuous', whiteBalanceMode: 'continuous' },
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
    cameraTrack = null;
    cameraControls.value = { zoom: null, torch: false, torchSupported: false, exposure: null };
    try {
        const capabilities = activeScanner.getRunningTrackCameraCapabilities();
        const zoom = capabilities.zoomFeature();
        const controls = { zoom: null, torch: false, torchSupported: false, exposure: null };
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
    // html5-qrcode only wraps zoom/torch; exposure compensation needs the raw
    // MediaStreamTrack off the video element the library created.
    try {
        const track = getVideoTrack();
        const caps = track?.getCapabilities?.() ?? {};
        if (track && caps.exposureCompensation) {
            cameraControls.value.exposure = {
                min: caps.exposureCompensation.min,
                max: caps.exposureCompensation.max,
                step: caps.exposureCompensation.step || 0.1,
                value: track.getSettings?.().exposureCompensation ?? 0,
            };
            cameraTrack = track;
        }
    } catch {
        // exposure control unsupported
    }
    cameraActive.value = true;
    startQrTracking();
    startEnhanceLoop();
}

function startQrTracking() {
    if (!window.BarcodeDetector) return;
    try {
        barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code'] });
        lastTrackedAt = 0;
        trackQrPosition();
    } catch {
        barcodeDetector = null;
    }
}

async function trackQrPosition() {
    if (!barcodeDetector || !cameraActive.value) return;
    const video = document.querySelector(`#${SCANNER_ELEMENT_ID} video`);
    const stage = scannerStage.value;
    try {
        if (video?.readyState >= 2 && video.videoWidth && stage) {
            const matches = await barcodeDetector.detect(video);
            const bounds = matches
                .map((match) => match.boundingBox)
                .filter((box) => box && box.width > 0 && box.height > 0)
                .sort((a, b) => (b.width * b.height) - (a.width * a.height))[0];
            if (bounds) {
                const stageRect = stage.getBoundingClientRect();
                const scale = Math.max(stageRect.width / video.videoWidth, stageRect.height / video.videoHeight);
                const displayWidth = video.videoWidth * scale;
                const displayHeight = video.videoHeight * scale;
                let left = (stageRect.width - displayWidth) / 2 + bounds.x * scale;
                const top = (stageRect.height - displayHeight) / 2 + bounds.y * scale;
                const width = bounds.width * scale;
                const height = bounds.height * scale;
                if (mirrored.value) left = stageRect.width - left - width;
                const padding = Math.max(4, Math.min(width, height) * 0.04);
                const x = Math.max(0, left - padding);
                const y = Math.max(0, top - padding);
                const right = Math.min(stageRect.width, left + width + padding);
                const bottom = Math.min(stageRect.height, top + height + padding);
                trackingBox.value = {
                    left: `${x}px`,
                    top: `${y}px`,
                    width: `${Math.max(0, right - x)}px`,
                    height: `${Math.max(0, bottom - y)}px`,
                };
                lastTrackedAt = Date.now();
            } else if (Date.now() - lastTrackedAt > 450) {
                trackingBox.value = null;
            }
        }
    } catch {
        // Tracking is an enhancement; QR decoding continues without it.
    } finally {
        if (cameraActive.value && barcodeDetector) {
            qrTrackingTimer = setTimeout(trackQrPosition, 250);
        }
    }
}

function getVideoTrack() {
    const video = document.querySelector(`#${SCANNER_ELEMENT_ID} video`);
    return video?.srcObject?.getVideoTracks?.()[0] ?? null;
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

async function applyExposure() {
    lightingTouchedByUser = true;
    try {
        await cameraTrack?.applyConstraints({
            advanced: [{ exposureCompensation: Number(cameraControls.value.exposure.value) }],
        });
    } catch {
        // camera rejected the exposure value
    }
}

async function toggleTorch() {
    const next = !cameraControls.value.torch;
    lightingTouchedByUser = true;
    try {
        await cameraCapabilities?.torchFeature().apply(next);
        cameraControls.value.torch = next;
    } catch {
        // torch not actually usable on this camera
    }
}

// --- Lighting adaptation + enhanced decode -------------------------------
// The library decodes raw frames only. This loop periodically samples a
// downscaled frame, measures its brightness, and:
//  - auto-turns the torch on in dark rooms (like a real barcode scanner)
//  - bumps exposure compensation when no torch exists
//  - retries ZXing on contrast-stretched and inverted copies of the frame,
//    which rescues dim, backlit, and low-contrast codes the raw pass misses.
function startEnhanceLoop() {
    stopEnhanceLoop();
    darkFrames = 0;
    lightingTouchedByUser = false;
    lowLight.value = false;
    enhanceTimer = setInterval(sampleFrame, 800);
}

function stopEnhanceLoop() {
    clearInterval(enhanceTimer);
    enhanceTimer = null;
    darkFrames = 0;
    lowLight.value = false;
}

function sampleFrame() {
    const video = document.querySelector(`#${SCANNER_ELEMENT_ID} video`);
    if (!video?.videoWidth || video.readyState < 2) return;
    enhanceCanvas ??= document.createElement('canvas');
    const scale = Math.min(1, 640 / video.videoWidth);
    const w = Math.round(video.videoWidth * scale);
    const h = Math.round(video.videoHeight * scale);
    enhanceCanvas.width = w;
    enhanceCanvas.height = h;
    const ctx = enhanceCanvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(video, 0, 0, w, h);
    let imageData;
    try {
        imageData = ctx.getImageData(0, 0, w, h);
    } catch {
        return; // frame not readable (e.g. tainted)
    }
    const px = imageData.data;
    const hist = new Uint32Array(256);
    let sum = 0;
    for (let i = 0; i < px.length; i += 4) {
        const l = (px[i] * 299 + px[i + 1] * 587 + px[i + 2] * 114) / 1000 | 0;
        hist[l]++;
        sum += l;
    }
    adaptLighting(sum / (w * h));
    if (detected.value) return;
    // Retry decode on enhanced copies: contrast-stretched, then inverted
    // (covers white-on-dark codes the normal binarizer misses).
    for (const enhance of [contrastStretch, null]) {
        try {
            if (enhance) enhance(imageData, hist);
            ctx.putImageData(imageData, 0, 0);
            const text = zxingDecode(enhanceCanvas, enhance === null);
            if (text) { handleDecoded(text); return; }
        } catch {
            // this variant didn't decode — try the next
        }
    }
}

function adaptLighting(mean) {
    const dark = mean < 55;
    darkFrames = dark ? darkFrames + 1 : 0;
    lowLight.value = dark;
    if (!dark || darkFrames < 3 || lightingTouchedByUser) return;
    if (cameraControls.value.torchSupported && !cameraControls.value.torch) {
        cameraControls.value.torch = true;
        try {
            cameraCapabilities?.torchFeature().apply(true);
        } catch {
            cameraControls.value.torch = false;
        }
    } else if (cameraControls.value.exposure && cameraControls.value.exposure.value <= 0) {
        cameraControls.value.exposure.value = Math.round(
            (cameraControls.value.exposure.max * 0.6) / cameraControls.value.exposure.step,
        ) * cameraControls.value.exposure.step;
        applyExposure();
    }
}

function contrastStretch(imageData, hist) {
    const total = imageData.width * imageData.height;
    let lo = 0, hi = 255, acc = 0;
    for (let i = 0; i < 256; i++) { acc += hist[i]; if (acc > total * 0.02) { lo = i; break; } }
    acc = 0;
    for (let i = 255; i >= 0; i--) { acc += hist[i]; if (acc > total * 0.02) { hi = i; break; } }
    const range = Math.max(hi - lo, 1);
    const px = imageData.data;
    for (let i = 0; i < px.length; i += 4) {
        const l = (px[i] * 299 + px[i + 1] * 587 + px[i + 2] * 114) / 1000;
        const g = Math.max(0, Math.min(255, ((l - lo) * 255) / range));
        px[i] = px[i + 1] = px[i + 2] = g;
    }
}

function zxingDecode(canvas, inverted) {
    if (!zxingReader) {
        const hints = new Map();
        hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE]);
        hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
        zxingReader = new ZXing.MultiFormatReader(false, hints);
    }
    const source = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
    const bitmap = new ZXing.BinaryBitmap(
        new ZXing.HybridBinarizer(inverted ? new ZXing.InvertedLuminanceSource(source) : source),
    );
    return zxingReader.decode(bitmap)?.text ?? null;
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
    cameraActive.value = false;
    stopEnhanceLoop();
    clearTimeout(qrTrackingTimer);
    qrTrackingTimer = null;
    barcodeDetector = null;
    trackingBox.value = null;
    lastTrackedAt = 0;
    const active = scanner;
    scanner = null;
    if (active) {
        try {
            if (active.isScanning) await active.stop();
            active.clear();
        } catch {
            // already stopped
        }
    }
    cameraCapabilities = null;
    cameraTrack = null;
    cameraControls.value = { zoom: null, torch: false, torchSupported: false, exposure: null };
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
            <div ref="scannerStage" class="qr-scanner-stage" :class="{ detected, scanning: cameraActive && !detected, mirrored, tracking: !!trackingBox }" aria-label="QR code camera preview">
                <div :id="SCANNER_ELEMENT_ID" class="qr-scanner-region" />
                <div class="qr-scanner-guide" :class="{ tracking: !!trackingBox, detected }" :style="trackingBox || undefined" aria-hidden="true"><span /></div>
                <p v-if="!cameraActive && !starting && !cameraError" class="qr-scanner-camera-placeholder">Camera preview will appear here</p>
            </div>
            <div v-if="cameraControls.zoom || cameraControls.torchSupported || cameraControls.exposure || cameraList.length > 1" class="qr-scanner-controls">
                <label v-if="cameraControls.zoom" class="qr-scanner-zoom">
                    <span>Zoom</span>
                    <input v-model="cameraControls.zoom.value" type="range" :min="cameraControls.zoom.min" :max="cameraControls.zoom.max" :step="cameraControls.zoom.step" @input="applyZoom">
                </label>
                <label v-if="cameraControls.exposure" class="qr-scanner-zoom">
                    <span>Brightness</span>
                    <input v-model="cameraControls.exposure.value" type="range" :min="cameraControls.exposure.min" :max="cameraControls.exposure.max" :step="cameraControls.exposure.step" @input="applyExposure">
                </label>
                <button v-if="cameraControls.torchSupported" type="button" class="qr-scanner-link" @click="toggleTorch">{{ cameraControls.torch ? 'Flashlight: on' : 'Flashlight: off' }}</button>
                <button v-if="cameraList.length > 1" type="button" class="qr-scanner-link" @click="switchCamera">Switch camera</button>
            </div>
            <p v-if="cameraError" class="field-error qr-scanner-error">{{ cameraError }}</p>
            <p v-else-if="error" class="field-error qr-scanner-error">{{ error }}</p>
            <p v-else-if="starting" class="qr-scanner-hint">Starting camera…</p>
            <p v-else-if="detected" class="qr-scanner-hint qr-scanner-success">QR detected — opening record…</p>
            <p v-else-if="cameraActive && lowLight && !cameraControls.torch" class="qr-scanner-hint">Scene looks dark — raise the pass's brightness or move to better light.</p>
            <p v-else-if="cameraActive" class="qr-scanner-hint">Keep the QR code in view and hold it steady. The guide follows it when detected.</p>
            <p v-else class="qr-scanner-hint">Keep the QR code anywhere in view and hold it steady. The guide will follow when detected.</p>
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
