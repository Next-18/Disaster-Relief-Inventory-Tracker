<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { appConfirm } from '../../composables/useAppDialog';

defineOptions({ layout: AdminLayout });

const props = defineProps({ barangays: { type: Array, default: () => [] }, activeBarangayId: { type: Number, default: null } });
const search = ref('');
const filteredBarangays = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();
    if (!query) return props.barangays;

    return props.barangays.filter((barangay) =>
        [barangay.name, barangay.municipality, barangay.province]
            .some((value) => String(value ?? '').toLocaleLowerCase().includes(query)),
    );
});
const form = useForm({ name: '', municipality: '', province: '', admin_name: '', admin_email: '', admin_password: '', admin_password_confirmation: '' });
const submit = () => form.post('/super-admin/barangays', { onSuccess: () => form.reset() });
const activate = (id) => router.post(`/super-admin/barangays/${id}/activate`);
const clearContext = () => router.delete('/super-admin/barangay-context');
async function toggleStatus(barangay) {
    const nextActive = !barangay.is_active;
    const confirmed = await appConfirm(
        nextActive
            ? `Reactivate ${barangay.name}? Its administrators and staff will be able to sign in again.`
            : `Deactivate ${barangay.name}? Its administrators and staff will lose access until it is reactivated.`,
        {
            title: nextActive ? 'Reactivate barangay?' : 'Deactivate barangay?',
            confirmLabel: nextActive ? 'Reactivate' : 'Deactivate',
            danger: !nextActive,
        },
    );
    if (confirmed) router.patch(`/super-admin/barangays/${barangay.id}/status`, { is_active: nextActive });
}
</script>

<template>
    <div class="page-wrap">
        <section class="panel">
            <header><h2>Barangays</h2><p>Create a barangay workspace and its first administrator.</p></header>
            <form class="form-grid" @submit.prevent="submit">
                <label>Barangay name<input v-model="form.name" required maxlength="255"><small>{{ form.errors.name }}</small></label>
                <label>Municipality<input v-model="form.municipality" required maxlength="255"><small>{{ form.errors.municipality }}</small></label>
                <label>Province<input v-model="form.province" required maxlength="255"><small>{{ form.errors.province }}</small></label>
                <label>Administrator name<input v-model="form.admin_name" required maxlength="255"><small>{{ form.errors.admin_name }}</small></label>
                <label>Administrator email<input v-model="form.admin_email" type="email" required><small>{{ form.errors.admin_email }}</small></label>
                <label>Temporary password<input v-model="form.admin_password" type="password" required minlength="12" autocomplete="new-password"><small>{{ form.errors.admin_password }}</small></label>
                <label>Confirm password<input v-model="form.admin_password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
                <button type="submit" :disabled="form.processing">Create barangay and administrator</button>
            </form>
        </section>

        <section class="panel">
            <header><h2>Workspace access</h2><p>Select a barangay to work in its admin portal.</p></header>
            <label class="search-label" for="barangay-search">Search barangays</label>
            <input id="barangay-search" v-model="search" class="search-input" type="search" placeholder="Name, municipality, or province" autocomplete="off">
            <button v-if="activeBarangayId" type="button" class="secondary" @click="clearContext">Clear workspace selection</button>
            <div class="table-wrap"><table><thead><tr><th>Barangay</th><th>Municipality</th><th>Province</th><th>Accounts</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody><tr v-for="barangay in filteredBarangays" :key="barangay.id"><td>{{ barangay.name }}</td><td>{{ barangay.municipality }}</td><td>{{ barangay.province }}</td><td>{{ barangay.users_count }}</td><td><span class="status-tag" :class="barangay.is_active ? 'is-active' : 'is-inactive'">{{ barangay.is_active ? 'Active' : 'Inactive' }}</span></td><td class="row-actions">
                    <button v-if="barangay.is_active" type="button" @click="activate(barangay.id)">{{ activeBarangayId === barangay.id ? 'Current workspace' : 'Open workspace' }}</button>
                    <button type="button" class="status-toggle" :class="{ deactivate: barangay.is_active }" @click="toggleStatus(barangay)">{{ barangay.is_active ? 'Deactivate' : 'Reactivate' }}</button>
                </td></tr><tr v-if="!filteredBarangays.length"><td colspan="6">{{ search.trim() ? 'No barangays match your search.' : 'No barangays found.' }}</td></tr></tbody>
            </table></div>
        </section>
    </div>
</template>

<style scoped>
.page-wrap{display:grid;gap:20px}.panel{background:#fff;border:1px solid #e5e9ef;border-radius:14px;padding:22px}.panel header h2{margin:0 0 6px}.panel header p{margin:0 0 20px;color:#667085}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.form-grid label{display:grid;gap:6px;font-weight:600}.form-grid input{border:1px solid #ccd3dd;border-radius:8px;padding:10px}.form-grid small{color:#b42318}.form-grid button{align-self:end}button{border:0;border-radius:8px;background:#176b54;color:#fff;padding:10px 14px;font-weight:700;cursor:pointer}.secondary{background:#475467;margin-bottom:14px}.table-wrap{overflow:auto;margin-top:16px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #eaecf0}@media(max-width:700px){.form-grid{grid-template-columns:1fr}}
.search-label{display:block;margin:0 0 6px;font-weight:600}.search-input{width:min(100%,420px);box-sizing:border-box;border:1px solid #ccd3dd;border-radius:8px;padding:10px 12px;font:inherit}.search-input:focus{outline:2px solid #176b54;outline-offset:1px}
.row-actions{display:flex;flex-wrap:wrap;gap:8px}.status-toggle{background:#475467}.status-toggle.deactivate{background:#b42318}.status-tag{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700}.status-tag.is-active{background:#ecfdf3;color:#067647}.status-tag.is-inactive{background:#f2f4f7;color:#667085}
</style>
