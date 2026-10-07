<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import InertiaPagination from '../../Components/InertiaPagination.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: { type: Object, required: true },
});

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'admin',
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
    form.email = user.email;
    form.role = user.role;
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

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user account? This action cannot be undone.')) {
        router.delete(`${routes.value.users}/${id}`);
    }
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
                <p>Manage admin accounts. Beneficiaries can create their own accounts using their beneficiary number.</p>
            </div>
            <button class="add-button" type="button" @click="openCreateModal">
                <span>+</span> Add admin
            </button>
        </section>

        <div v-if="$page.props.flash.success" class="flash-success">
            {{ $page.props.flash.success }}
        </div>

        <div v-if="$page.props.flash.error" class="flash-error">
            {{ $page.props.flash.error }}
        </div>

        <div class="panel-heading">
            <div>
                <h3>System users</h3>
                <p>{{ users.total }} total user accounts</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>USER</th>
                        <th>USERNAME</th>
                        <th>ROLE</th>
                        <th>BENEFICIARY</th>
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
                        <td>{{ user.email }}</td>
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
                            <span class="tag success">Active</span>
                        </td>
                        <td>
                            <button type="button" @click="editUser(user)" class="action-btn">Edit</button>
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
                        <td colspan="5" class="empty-cell">No user accounts yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <InertiaPagination :pagination="users" />
    </div>

    <dialog class="form-modal" id="user-modal">
        <div class="modal-title">
            <h3>{{ editingId ? 'Edit admin account' : 'Add admin account' }}</h3>
            <p>{{ editingId ? 'Update admin account information.' : 'Create a new admin account for system management.' }}</p>
        </div>
        <form @submit.prevent="editingId ? updateUser() : createUser()">
            <label>Full name
                <input v-model="form.name" type="text" required placeholder="Enter full name">
            </label>
            <label>Email address
                <input v-model="form.email" type="email" required placeholder="Enter email address">
            </label>
            <label>Password
                <input 
                    v-model="form.password" 
                    type="password" 
                    :required="!editingId" 
                    placeholder="Enter password (min 8 characters)"
                >
            </label>
            <label>Confirm password
                <input 
                    v-model="form.password_confirmation" 
                    type="password" 
                    :required="!editingId" 
                    placeholder="Confirm password"
                >
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
