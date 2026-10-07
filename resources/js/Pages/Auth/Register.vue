<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const form = useForm({
    full_name: '',
    beneficiary_no: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(page.props.routeUrls.registerAttempt, {
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<template>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Register as a beneficiary to access relief services</p>
            </div>

            <div v-if="$page.props.flash.success" class="flash-success">
                {{ $page.props.flash.success }}
            </div>

            <form @submit.prevent="submit">
                <label>Full Name
                    <input 
                        v-model="form.full_name" 
                        type="text" 
                        required 
                        placeholder="Enter your full name"
                    >
                </label>
                <label>Beneficiary Number
                    <input 
                        v-model="form.beneficiary_no" 
                        type="text" 
                        required 
                        placeholder="Enter your beneficiary number (e.g., BEN-1001)"
                    >
                </label>
                <label>Password
                    <input 
                        v-model="form.password" 
                        type="password" 
                        required 
                        placeholder="Create a password (min 8 characters)"
                    >
                </label>
                <label>Confirm Password
                    <input 
                        v-model="form.password_confirmation" 
                        type="password" 
                        required 
                        placeholder="Confirm your password"
                    >
                </label>
                <button type="submit" class="primary-action" :disabled="form.processing">
                    {{ form.processing ? 'Creating Account...' : 'Create Account' }}
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a :href="page.props.routeUrls.login">Login here</a></p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.auth-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 450px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.auth-header {
    text-align: center;
    margin-bottom: 30px;
}

.auth-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.auth-header p {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}

.flash-success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
    color: #166534;
    font-size: 13px;
    font-weight: 600;
}

.auth-card label {
    display: block;
    margin-bottom: 16px;
}

.auth-card label input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    margin-top: 6px;
}

.auth-card label input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.primary-action {
    width: 100%;
    padding: 14px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.primary-action:hover {
    background: #5568d3;
}

.primary-action:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.auth-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.auth-footer p {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.auth-footer a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
}

.auth-footer a:hover {
    text-decoration: underline;
}
</style>
