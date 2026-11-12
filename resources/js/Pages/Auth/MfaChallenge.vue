<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ email: { type: String, required: true }, needsSetup: { type: Boolean, default: false }, secret: { type: String, default: null }, provisioningUri: { type: String, default: null }, qrCodeDataUri: { type: String, default: null } });
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const form = useForm({ code: '' });
function submit() { form.post(routes.value.mfaVerify, { replace: true, onFinish: () => form.reset('code') }); }
</script>

<template>
    <Head title="Account verification" />
    <main class="mfa-page">
        <section class="mfa-card">
            <div class="lock-mark" aria-hidden="true">◆</div>
            <h1>{{ needsSetup ? 'Protect your account' : 'Verify it’s you' }}</h1>
            <p v-if="needsSetup">Set up an authenticator app for <strong>{{ email }}</strong>. Scan this QR code with your authenticator app, or enter the account key manually.</p>
            <p v-else>Enter the six-digit code from your authenticator app, or use one of your recovery codes.</p>
            <div v-if="needsSetup" class="setup-key">
                <span>Scan with your authenticator app</span>
                <img v-if="qrCodeDataUri" class="setup-qr" :src="qrCodeDataUri" alt="Authenticator setup QR code">
                <span>Or enter this account key manually</span>
                <code>{{ secret }}</code>
                <a v-if="provisioningUri" :href="provisioningUri">Open in authenticator app</a>
                <small>Keep this key private. It will not be shown again after setup.</small>
            </div>
            <form @submit.prevent="submit">
                <label for="code">{{ needsSetup ? 'Authenticator code' : 'Verification or recovery code' }}</label>
                <input id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
                <small v-if="form.errors.code" class="error">{{ form.errors.code }}</small>
                <button type="submit" :disabled="form.processing">{{ form.processing ? 'Verifying…' : (needsSetup ? 'Enable MFA and sign in' : 'Verify and sign in') }}</button>
            </form>
            <p class="footnote">This verification expires if you close this browser session. Failed attempts are rate limited.</p>
        </section>
    </main>
</template>

<style scoped>
.mfa-page{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f2f5f9;font-family:inherit;color:#1d2939}.mfa-card{width:min(100%,480px);box-sizing:border-box;padding:32px;background:#fff;border:1px solid #e4e7ec;border-radius:16px;box-shadow:0 16px 40px #10182812}.lock-mark{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#e9f5f0;color:#176b54}h1{margin:18px 0 8px;font-size:24px}.mfa-card p{line-height:1.55;color:#667085}.setup-key{display:grid;justify-items:start;gap:8px;margin:20px 0;padding:16px;background:#f9fafb;border-radius:10px}.setup-key span,.setup-key small{color:#667085;font-size:13px}.setup-key code{font-size:18px;letter-spacing:1px;word-break:break-all}.setup-key a{color:#176b54;font-weight:600}.setup-qr{width:220px;height:220px;max-width:100%;padding:8px;background:#fff;border:1px solid #e4e7ec;border-radius:8px;image-rendering:pixelated}.mfa-card form{display:grid;gap:10px;margin-top:22px}.mfa-card label{font-size:14px;font-weight:650}.mfa-card input{padding:12px;border:1px solid #d0d5dd;border-radius:8px;font:inherit;font-size:18px;letter-spacing:2px}.mfa-card button{padding:12px;border:0;border-radius:8px;background:#176b54;color:#fff;font-weight:700;cursor:pointer}.mfa-card button:disabled{opacity:.65}.error{color:#b42318}.footnote{margin-top:18px!important;font-size:12px}
</style>
