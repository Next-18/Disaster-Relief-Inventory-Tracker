<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const form = useForm({ email: '', password: '', remember: false });
const passwordVisible = ref(false);
const firstError = computed(() => Object.values(form.errors)[0] ?? '');

function submit() {
    form.post(routes.value.loginAttempt, { replace: true, onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Admin / Staff Login | ReliefTrack" />
    <div class="login-page">
        <main class="page">
        <section class="shell" aria-label="Disaster relief inventory tracker login">
            <aside class="brand-panel">
                <div class="brand"><img :src="routes.logo" alt="Disaster Relief Inventory Tracker" class="logo-image"><span>Disaster Relief<br>Inventory Tracker</span></div>
                <div class="brand-copy"><h1>Barangay operations portal.</h1><p>This portal is only for authorized Barangay Admin and Relief Staff. They can manage inventory, beneficiary records, and relief distribution.</p></div>
                <div class="secure"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Secure access for authorized personnel</div>
            </aside>
            <section class="form-panel">
                <h2>Admin / Staff Login</h2>
                <p class="intro">Use your barangay-issued account. Beneficiaries can <a :href="routes.register">register here</a>.</p>
                <div v-if="firstError" class="notice" role="alert">{{ firstError }}</div>
                <form @submit.prevent="submit">
                    <label for="email">Email address</label>
                    <input id="email" v-model="form.email" class="field" type="email" placeholder="admin@barangay.gov.ph" required autofocus autocomplete="username">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" v-model="form.password" class="field" :type="passwordVisible ? 'text' : 'password'" placeholder="Enter password" required autocomplete="current-password">
                        <button class="toggle" type="button" :aria-label="passwordVisible ? 'Hide password' : 'Show password'" @click="passwordVisible = !passwordVisible">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path v-if="!passwordVisible" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><template v-else><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="m14.12 14.12a3 3 0 1 1-4.24-4.24M1 1l22 22"/></template><circle v-if="!passwordVisible" cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="below"><label class="remember"><input v-model="form.remember" type="checkbox">Remember me</label><a href="mailto:admin@barangay.gov.ph?subject=Account%20access%20help">Need help?</a></div>
                    <button class="submit" type="submit" :disabled="form.processing">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
                </form>
                <p class="help">For account concerns, please contact your Barangay Administrator.<br>Only registered Admin and Staff accounts can access this system.</p>
            </section>
        </section>
        </main>
    </div>
</template>

<style src="../../../css/login.css"></style>
