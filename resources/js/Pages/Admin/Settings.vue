<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    settings: { type: Object, required: true },
    isSuperAdmin: { type: Boolean, default: false },
    canManageSettings: { type: Boolean, default: false },
    hasBarangayWorkspace: { type: Boolean, default: false },
    mfaEnabled: { type: Boolean, default: false },
    mfaRecoveryCodes: { type: Array, default: () => [] },
    mfaSetup: { type: Object, default: null },
});
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const platformForm = useForm({
    site_name: props.settings.site_name ?? '',
    site_description: props.settings.site_description ?? '',
    contact_email: props.settings.contact_email ?? '',
    contact_phone: props.settings.contact_phone ?? '',
});
const locationForm = useForm({
    barangay_name: props.settings.barangay_name ?? '',
    municipality: props.settings.municipality ?? '',
    province: props.settings.province ?? '',
});
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const mfaForm = useForm({ current_password: '', code: '' });
const mfaEnableForm = useForm({ code: '' });

watch(() => props.settings, (settings) => {
    Object.assign(platformForm, {
        site_name: settings.site_name ?? '',
        site_description: settings.site_description ?? '',
        contact_email: settings.contact_email ?? '',
        contact_phone: settings.contact_phone ?? '',
    });
    Object.assign(locationForm, {
        barangay_name: settings.barangay_name ?? '',
        municipality: settings.municipality ?? '',
        province: settings.province ?? '',
    });
}, { deep: true });

function savePlatformSettings() {
    platformForm.transform((data) => ({ ...data, settings_scope: 'platform' }))
        .post(routes.value.settings, { preserveScroll: true });
}

function saveLocationSettings() {
    locationForm.transform((data) => ({ ...data, settings_scope: 'location' }))
        .post(routes.value.settings, { preserveScroll: true });
}

function updatePassword() {
    passwordForm.put(routes.value.passwordUpdate, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset(),
    });
}

function disableMfa() {
    mfaForm.post(routes.value.mfaDisable, { preserveScroll: true, onSuccess: () => mfaForm.reset() });
}

function beginMfaSetup() {
    router.post(routes.value.mfaSetup, {}, { preserveScroll: true });
}

function enableMfa() {
    mfaEnableForm.post(routes.value.mfaEnable, { preserveScroll: true, onSuccess: () => mfaEnableForm.reset() });
}
</script>

<template>
    <section class="module-heading"><div><h2>Settings</h2><p v-if="!canManageSettings">Manage your account security and sign-in protection.</p><p v-else-if="isSuperAdmin && !hasBarangayWorkspace">Manage platform details. Select a barangay workspace to edit its location details.</p><p v-else>Configure {{ isSuperAdmin ? 'platform and barangay' : 'barangay' }} settings and account security.</p></div></section>
    <div v-if="canManageSettings" class="settings-form">
        <form v-if="isSuperAdmin" @submit.prevent="savePlatformSettings">
            <section class="panel record-panel">
                <div class="panel-heading"><div><h3>Platform Settings</h3><p>Site name, description, and platform contact details</p></div></div>
                <div class="settings-fields">
                    <label>Site Name<input v-model="platformForm.site_name" type="text" required><small v-if="platformForm.errors.site_name" class="field-error">{{ platformForm.errors.site_name }}</small></label>
                    <label>Site Description<textarea v-model="platformForm.site_description" rows="3"/><small v-if="platformForm.errors.site_description" class="field-error">{{ platformForm.errors.site_description }}</small></label>
                    <div class="settings-grid two-columns">
                        <label>Contact Email<input v-model="platformForm.contact_email" type="email"><small v-if="platformForm.errors.contact_email" class="field-error">{{ platformForm.errors.contact_email }}</small></label>
                        <label>Contact Phone<input v-model="platformForm.contact_phone" type="text"><small v-if="platformForm.errors.contact_phone" class="field-error">{{ platformForm.errors.contact_phone }}</small></label>
                    </div>
                    <div class="settings-save-row"><p>These details apply to the whole platform.</p><button class="primary-action" type="submit" :disabled="platformForm.processing">{{ platformForm.processing ? 'Saving...' : 'Save platform settings' }}</button></div>
                </div>
            </section>
        </form>
        <form v-if="hasBarangayWorkspace" @submit.prevent="saveLocationSettings">
            <section class="panel record-panel">
                <div class="panel-heading"><div><h3>Barangay Location</h3><p>Geographic details for the currently selected workspace</p></div></div>
                <div class="settings-fields settings-grid three-columns">
                    <label>Barangay Name<input v-model="locationForm.barangay_name" type="text" required><small v-if="locationForm.errors.barangay_name" class="field-error">{{ locationForm.errors.barangay_name }}</small></label>
                    <label>Municipality<input v-model="locationForm.municipality" type="text" required><small v-if="locationForm.errors.municipality" class="field-error">{{ locationForm.errors.municipality }}</small></label>
                    <label>Province<input v-model="locationForm.province" type="text" required><small v-if="locationForm.errors.province" class="field-error">{{ locationForm.errors.province }}</small></label>
                    <div class="settings-save-row settings-location-save"><p>Applies only to the selected barangay.</p><button class="primary-action" type="submit" :disabled="locationForm.processing">{{ locationForm.processing ? 'Saving...' : 'Save location settings' }}</button></div>
                </div>
            </section>
        </form>
    </div>
    <section class="panel record-panel security-panel">
        <div class="panel-heading"><div><h3>Account security</h3><p>Change your password and manage sign-in protection.</p></div></div>
        <form class="settings-fields" @submit.prevent="updatePassword">
            <p class="password-guidance">Use at least 12 characters with uppercase and lowercase letters and a number.</p>
            <div class="settings-grid two-columns">
                <label class="current-password-field">Current password<input v-model="passwordForm.current_password" type="password" autocomplete="current-password" required><small v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</small></label>
                <label>New password<input v-model="passwordForm.password" type="password" autocomplete="new-password" minlength="12" required><small v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</small></label>
                <label>Confirm new password<input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label>
            </div>
            <div class="settings-actions"><button class="security-save" type="submit" :disabled="passwordForm.processing">{{ passwordForm.processing ? 'Updating…' : 'Update password' }}</button></div>
        </form>
    </section>
    <section class="panel record-panel security-panel">
        <div class="panel-heading"><div><h3>Multi-factor authentication</h3><p v-if="isSuperAdmin">Authenticator codes are required every time you sign in.</p><p v-else>Optional extra protection. When enabled, an authenticator code will be required at each web or mobile sign-in.</p></div></div>
        <div class="settings-fields">
            <p v-if="mfaEnabled" class="mfa-state">Authenticator protection is enabled.</p>
            <p v-else-if="isSuperAdmin" class="mfa-state">Setup is required at your next sign-in. Keep your recovery codes somewhere private and separate from this device.</p>
            <p v-else class="mfa-state">Authenticator protection is currently off.</p>
            <div v-if="mfaRecoveryCodes.length" class="recovery-codes" role="status">
                <strong>Save these recovery codes now. Each code works once.</strong>
                <code v-for="code in mfaRecoveryCodes" :key="code">{{ code }}</code>
            </div>
            <div v-if="mfaSetup && !mfaEnabled" class="mfa-setup">
                <span>Scan this QR code with an authenticator app</span>
                <img class="setup-qr" :src="mfaSetup.qrCodeDataUri" alt="Authenticator setup QR code">
                <span>Or enter this key manually</span>
                <code>{{ mfaSetup.secret }}</code>
                <form class="settings-fields mfa-disable-form" @submit.prevent="enableMfa">
                    <label>Authenticator code<input v-model="mfaEnableForm.code" inputmode="numeric" autocomplete="one-time-code" required><small v-if="mfaEnableForm.errors.code" class="field-error">{{ mfaEnableForm.errors.code }}</small></label>
                    <button class="security-save" type="submit" :disabled="mfaEnableForm.processing">{{ mfaEnableForm.processing ? 'Enabling…' : 'Confirm and enable MFA' }}</button>
                </form>
            </div>
            <button v-if="!mfaEnabled && !isSuperAdmin && !mfaSetup" class="security-save" type="button" :disabled="mfaForm.processing" @click="beginMfaSetup">Set up authenticator</button>
            <form v-if="mfaEnabled" class="settings-fields mfa-disable-form" @submit.prevent="disableMfa">
                <label>Current password<input v-model="mfaForm.current_password" type="password" autocomplete="current-password" required><small v-if="mfaForm.errors.current_password" class="field-error">{{ mfaForm.errors.current_password }}</small></label>
                <label>Authenticator or recovery code<input v-model="mfaForm.code" autocomplete="one-time-code" required><small v-if="mfaForm.errors.code" class="field-error">{{ mfaForm.errors.code }}</small></label>
                <button class="security-save" type="submit" :disabled="mfaForm.processing">{{ mfaForm.processing ? 'Updating…' : (isSuperAdmin ? 'Reset authenticator setup' : 'Turn off MFA') }}</button>
            </form>
        </div>
    </section>
</template>

<style scoped>
.settings-form { display: grid; gap: 14px; }
.settings-fields { display: grid; gap: 15px; padding: 20px; }
.settings-fields label { display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700; }
.settings-fields input:not([type="checkbox"]), .settings-fields textarea { width: 100%; min-height: 38px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; background: #fff; color: #29496f; font: inherit; }
.settings-fields textarea { padding: 10px; resize: vertical; }
.settings-grid { display: grid; gap: 10px; }
.two-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.three-columns { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.current-password-field { grid-column: 1 / -1; }
.settings-save-row { display: flex; align-items: center; justify-content: flex-end; gap: 14px; padding: 2px 4px; }
.settings-save-row p { margin: 0 auto 0 0; color: #718096; font-size: 12px; }
.settings-location-save { grid-column: 1 / -1; }
.security-panel { margin-top: 14px; }
.password-guidance { margin: 0; color: #718096; font-size: 12px; }
.mfa-state { margin: 0; color: #344054; font-size: 13px; }
.mfa-setup { display: grid; justify-items: start; gap: 10px; padding: 16px; border: 1px solid #d9e2ec; border-radius: 10px; background: #f9fafb; color: #5b6d84; font-size: 13px; }
.mfa-setup code { color: #29496f; font-size: 15px; letter-spacing: 1px; word-break: break-all; }
.setup-qr { width: 220px; height: 220px; max-width: 100%; padding: 8px; background: #fff; border: 1px solid #d9e2ec; border-radius: 8px; image-rendering: pixelated; }
.mfa-disable-form { padding: 0; }
.recovery-codes { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; padding: 14px; border: 1px solid #fdb022; border-radius: 8px; background: #fffaeb; }
.recovery-codes strong { grid-column: 1 / -1; color: #7a2e0e; font-size: 13px; }
.recovery-codes code { color: #344054; font-size: 13px; }
.security-save { min-height: 38px; padding: 9px 14px; border: 1px solid #2563eb; border-radius: 8px; background: #2563eb; color: #fff; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
.security-save:hover { border-color: #1d4ed8; background: #1d4ed8; }
.security-save:disabled { cursor: wait; opacity: .65; }
.field-error { color: #b42318; }
@media (max-width: 650px) { .two-columns, .three-columns { grid-template-columns: 1fr; } }
</style>
