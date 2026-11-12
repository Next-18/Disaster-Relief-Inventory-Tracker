<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';
import { appConfirm } from '../../composables/useAppDialog';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: { type: Object, required: true },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const perPage = ref(Number(props.users.per_page ?? 10));

function updatePerPage() {
    router.get(routes.value.users, { per_page: perPage.value }, {
        preserveScroll: true,
        replace: true,
    });
}

const form = useForm({
    name: '',
    email: '',
    username: '',
    password: '',
    password_confirmation: '',
    beneficiary_id: '',
    role: 'staff',
    status: 'Active',
});

const editingId = ref(null);

function createUser() {
    form.post(routes.value.users, {
        onSuccess: () => {
            form.reset();
            closeModal();
        },
    });
}

function editUser(user) {
    editingId.value = user.id;
    form.name = user.name;
    form.email = user.email ?? '';
    form.username = user.username ?? '';
    form.role = user.role;
    form.status = user.beneficiary?.status ?? 'Active';
    form.beneficiary_id = user.beneficiary_id ?? '';
    form.password = '';
    form.password_confirmation = '';
    document.getElementById('user-modal').showModal();
}

function updateUser() {
    form.put(`${routes.value.users}/${editingId.value}`, {
        onSuccess: () => {
            form.reset();
            editingId.value = null;
            closeModal();
        },
        onError: () => {
            // Handle validation errors
        },
    });
}

async function deleteUser(id) {
    const confirmed = await appConfirm('This action cannot be undone.', {
        title: 'Delete this user account?',
        confirmLabel: 'Delete account',
        danger: true,
    });
    if (confirmed) router.delete(`${routes.value.users}/${id}`);
}

async function toggleUserStatus(user) {
    const nextActive = !user.is_active;
    const confirmed = await appConfirm(
        nextActive
            ? `Reactivate ${user.name}'s account? They will be able to sign in again.`
            : `Deactivate ${user.name}'s account? They will be signed out and their mobile tokens revoked.`,
        {
            title: nextActive ? 'Reactivate account?' : 'Deactivate account?',
            confirmLabel: nextActive ? 'Reactivate' : 'Deactivate',
            danger: !nextActive,
        },
    );
    if (confirmed) router.patch(`${routes.value.users}/${user.id}/status`, { is_active: nextActive });
}

function closeModal() {
    document.getElementById('user-modal').close();
    form.reset();
    editingId.value = null;
}

function openCreateModal() {
    form.reset();
    editingId.value = null;
    document.getElementById('user-modal').showModal();
}
</script>

<template>
    <div class="page-content">
        <section class="module-heading">
            <div>
                <h2>User accounts</h2>
                <p>Manage administrator, staff, volunteer, and beneficiary accounts. Beneficiaries sign in with their name-based username or beneficiary code.</p>
            </div>
            <button class="add-button" type="button" @click="openCreateModal">
                <span>+</span> Add team account
            </button>
        </section>

        <div class="panel-heading">
            <div>
                <h3>System users</h3>
                <p>
                    {{ users.total === 0 ? '0' : `${users.from}–${users.to}` }} of {{ users.total }} user accounts
                </p>
            </div>
            <label class="users-page-size">
                <span>Show</span>
                <select v-model.number="perPage" class="filter-select" aria-label="User accounts per page" @change="updatePerPage">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
                <span>per page</span>
            </label>
        </div>

        <div class="table-wrap">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>USER</th>
                        <th>USERNAME</th>
                        <th>ROLE</th>
                        <th>BENEFICIARY</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id">
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar a1">{{ user.name.charAt(0).toUpperCase() }}</div>
                                <div>
                                    <b>{{ user.name }}</b>
                                    <small>Created {{ new Date(user.created_at).toLocaleDateString() }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ user.username || user.email }}</td>
                        <td>
                            <span :class="['tag', user.role === 'admin' ? 'warning' : 'success']">
                                {{ user.role.charAt(0).toUpperCase() + user.role.slice(1) }}
                            </span>
                        </td>
                        <td>
                            <div v-if="user.beneficiary">
                                {{ user.beneficiary.full_name }}
                                <small>{{ user.beneficiary.beneficiary_no }}</small>
                            </div>
                            <span v-else style="color: #94a3b8;">No beneficiary linked</span>
                        </td>
                        <td>
                            <span :class="['tag', user.is_active ? 'success' : 'warning']">{{ user.is_active ? 'Active' : 'Inactive' }}</span>
                            <small v-if="user.role === 'user'">Beneficiary: {{ user.beneficiary?.status ?? 'Unknown' }}</small>
                        </td>
                        <td>
                            <button type="button" @click="editUser(user)" class="action-btn">Edit</button>
                            <button v-if="user.id !== $page.props.auth.user.id" type="button" @click="toggleUserStatus(user)" class="action-btn">{{ user.is_active ? 'Deactivate' : 'Reactivate' }}</button>
                            <button 
                                v-if="user.id !== $page.props.auth.user.id" 
                                type="button" 
                                @click="deleteUser(user.id)" 
                                class="action-btn delete"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="6" class="empty-cell">No user accounts yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <InertiaPagination :links="users.links" :show="users.last_page > 1" />
    </div>

    <dialog class="form-modal" id="user-modal">
        <div class="modal-title">
            <h3>{{ editingId ? (form.role === 'user' ? 'Edit beneficiary account' : 'Edit team account') : 'Add team account' }}</h3>
            <p>{{ editingId && form.role === 'user' ? 'Update the beneficiary profile and linked login account.' : editingId ? 'Update account information and role.' : 'Staff can release packages. Volunteers can scan and verify beneficiaries.' }}</p>
        </div>
        <form @submit.prevent="editingId ? updateUser() : createUser()">
            <label>Full name
                <input v-model="form.name" type="text" required placeholder="Enter full name" :class="{ 'input-error': form.errors.name }">
                <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
            </label>
            <label v-if="form.role !== 'user'">Email address
                <input v-model="form.email" type="email" required placeholder="Enter email address" :class="{ 'input-error': form.errors.email }">
                <small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small>
            </label>
            <label v-if="form.role === 'user'">Username
                <input v-model="form.username" type="text" maxlength="100" required autocomplete="username" placeholder="Enter username" :class="{ 'input-error': form.errors.username }">
                <small v-if="form.errors.username" class="field-error">{{ form.errors.username }}</small>
            </label>
            <label>Password
                <input 
                    v-model="form.password" 
                    type="password" 
                    minlength="12"
                    :required="!editingId" 
                    placeholder="Enter password (12+ characters, mixed case and a number)"
                    :class="{ 'input-error': form.errors.password }"
                >
                <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
            </label>
            <label>Confirm password
                <input 
                    v-model="form.password_confirmation" 
                    type="password" 
                    :required="!editingId" 
                    placeholder="Confirm password"
                    :class="{ 'input-error': form.errors.password }"
                >
            </label>
            <label v-if="form.role !== 'user'">Role
                <select v-model="form.role" required :class="{ 'input-error': form.errors.role }">
                    <option value="admin">Administrator</option>
                    <option value="staff">Staff — release packages and record distributions</option>
                    <option value="volunteer">Volunteer — scan and verify beneficiaries</option>
                    <option v-if="editingId && form.role === 'user'" value="user">Beneficiary</option>
                </select>
                <small v-if="form.errors.role" class="field-error">{{ form.errors.role }}</small>
            </label>
            <label v-if="form.role === 'user'">Status
                <select v-model="form.status" required :class="{ 'input-error': form.errors.status }">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small>
            </label>
            <div class="modal-actions">
                <button type="button" class="cancel-button" @click="closeModal">Cancel</button>
                <button class="primary-action" type="submit">
                    {{ editingId ? 'Update account' : 'Create account' }}
                </button>
            </div>
        </form>
    </dialog>
</template>

<style scoped>
.users-page-size {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #5f6f84;
    font-size: 12px;
    white-space: nowrap;
}

@media (max-width: 560px) {
    .users-page-size {
        flex-wrap: wrap;
    }
}
</style>
