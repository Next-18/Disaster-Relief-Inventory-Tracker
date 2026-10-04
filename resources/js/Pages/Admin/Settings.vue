<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ settings: { type: Object, required: true } });
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const form = useForm({
    site_name: props.settings.site_name ?? '',
    site_description: props.settings.site_description ?? '',
    contact_email: props.settings.contact_email ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    barangay_name: props.settings.barangay_name ?? '',
    municipality: props.settings.municipality ?? '',
    province: props.settings.province ?? '',
    session_timeout: Number(props.settings.session_timeout ?? 30),
    notifications_enabled: Boolean(props.settings.notifications_enabled),
    auto_backup_enabled: Boolean(props.settings.auto_backup_enabled),
});

watch(() => props.settings, (settings) => {
    Object.assign(form, {
        site_name: settings.site_name ?? '',
        site_description: settings.site_description ?? '',
        contact_email: settings.contact_email ?? '',
        contact_phone: settings.contact_phone ?? '',
        barangay_name: settings.barangay_name ?? '',
        municipality: settings.municipality ?? '',
        province: settings.province ?? '',
        session_timeout: Number(settings.session_timeout ?? 30),
        notifications_enabled: Boolean(settings.notifications_enabled),
        auto_backup_enabled: Boolean(settings.auto_backup_enabled),
    });
}, { deep: true });

function saveSettings() {
    form.post(routes.value.settings, { preserveScroll: true });
}
</script>

<template>
    <section class="module-heading"><div><h2>Settings</h2><p>Configure relief tracker preferences and access.</p></div></section>
    <form class="settings-form" @submit.prevent="saveSettings">
        <section class="panel record-panel">
            <div class="panel-heading"><div><h3>General Settings</h3><p>Basic site configuration and contact information</p></div></div>
            <div class="settings-fields">
                <label>Site Name<input v-model="form.site_name" type="text" required><small v-if="form.errors.site_name" class="field-error">{{ form.errors.site_name }}</small></label>
                <label>Site Description<textarea v-model="form.site_description" rows="3"/><small v-if="form.errors.site_description" class="field-error">{{ form.errors.site_description }}</small></label>
                <div class="settings-grid two-columns">
                    <label>Contact Email<input v-model="form.contact_email" type="email"><small v-if="form.errors.contact_email" class="field-error">{{ form.errors.contact_email }}</small></label>
                    <label>Contact Phone<input v-model="form.contact_phone" type="text"><small v-if="form.errors.contact_phone" class="field-error">{{ form.errors.contact_phone }}</small></label>
                </div>
            </div>
        </section>
        <section class="panel record-panel">
            <div class="panel-heading"><div><h3>Location Settings</h3><p>Geographic information for reports and documentation</p></div></div>
            <div class="settings-fields settings-grid three-columns">
                <label>Barangay Name<input v-model="form.barangay_name" type="text" required><small v-if="form.errors.barangay_name" class="field-error">{{ form.errors.barangay_name }}</small></label>
                <label>Municipality<input v-model="form.municipality" type="text" required><small v-if="form.errors.municipality" class="field-error">{{ form.errors.municipality }}</small></label>
                <label>Province<input v-model="form.province" type="text" required><small v-if="form.errors.province" class="field-error">{{ form.errors.province }}</small></label>
            </div>
        </section>
        <section class="panel record-panel">
            <div class="panel-heading"><div><h3>System Settings</h3><p>System configuration and security preferences</p></div></div>
            <div class="settings-fields">
                <label class="timeout-field">Session Timeout (minutes)<input v-model.number="form.session_timeout" type="number" min="5" max="1440" required><small v-if="form.errors.session_timeout" class="field-error">{{ form.errors.session_timeout }}</small></label>
                <div class="settings-grid two-columns checkbox-grid">
                    <label class="checkbox-setting"><input v-model="form.notifications_enabled" type="checkbox">Enable System Notifications</label>
                    <label class="checkbox-setting"><input v-model="form.auto_backup_enabled" type="checkbox">Enable Automatic Backups</label>
                </div>
                <div class="settings-actions"><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Settings' }}</button></div>
            </div>
        </section>
    </form>
</template>

<style scoped>
.settings-form { display: grid; gap: 18px; }
.settings-fields { display: grid; gap: 15px; padding: 20px; }
.settings-fields label { display: grid; gap: 6px; color: #5b6d84; font-size: 11px; font-weight: 700; }
.settings-fields input:not([type="checkbox"]), .settings-fields textarea { width: 100%; min-height: 38px; padding: 0 10px; border: 1px solid #d9e2ec; border-radius: 7px; background: #fff; color: #29496f; font: inherit; }
.settings-fields textarea { padding: 10px; resize: vertical; }
.settings-grid { display: grid; gap: 10px; }
.two-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.three-columns { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.timeout-field { max-width: 360px; }
.checkbox-grid .checkbox-setting { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-setting input { width: 18px; height: 18px; accent-color: #2563eb; }
.settings-actions { display: flex; justify-content: flex-end; }
.field-error { color: #b42318; }
@media (max-width: 650px) { .two-columns, .three-columns { grid-template-columns: 1fr; } }
</style>
