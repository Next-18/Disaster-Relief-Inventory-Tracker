<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ beneficiaries: { type: Array, required: true } });
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const search = ref('');
const processingId = ref(null);
const rows = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.beneficiaries;
    return props.beneficiaries.filter((beneficiary) =>
        [beneficiary.full_name, beneficiary.beneficiary_no].some((value) => String(value ?? '').toLowerCase().includes(term)),
    );
});

function reportLost(beneficiary) {
    if (!window.confirm(`Report the QR code for ${beneficiary.full_name} as lost?`)) return;
    processingId.value = beneficiary.id;
    router.post(routes.value.lostQrReport.replace('__ID__', encodeURIComponent(beneficiary.id)), {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}

function generateNew(beneficiary) {
    processingId.value = beneficiary.id;
    router.post(routes.value.qrGenerate.replace('__ID__', encodeURIComponent(beneficiary.id)), {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}
</script>

<template>
    <section class="module-heading"><div><h2>Lost QR codes</h2><p>Report and replace lost beneficiary QR codes.</p></div></section>
    <section class="panel record-panel">
        <div class="panel-heading">
            <div><h3>Report lost QR code</h3><p>Select a beneficiary to report their QR code as lost</p></div>
            <label class="sr-only" for="lost-qr-search">Search beneficiaries</label>
            <input id="lost-qr-search" v-model="search" class="table-search" type="search" placeholder="Search beneficiaries" aria-label="Search beneficiaries">
        </div>
        <div class="table-wrap"><table class="record-table">
            <thead><tr><th>BENEFICIARY</th><th>QR CODE STATUS</th><th>ACTIONS</th></tr></thead>
            <tbody>
                <tr v-for="beneficiary in rows" :key="beneficiary.id">
                    <td><b>{{ beneficiary.full_name }}</b><small>{{ beneficiary.beneficiary_no }}</small></td>
                    <td><span class="tag" :class="beneficiary.qr_code ? 'success' : 'warning'">{{ beneficiary.qr_code ? 'Active' : 'Lost/Not Generated' }}</span></td>
                    <td>
                        <button v-if="beneficiary.qr_code" class="action-btn delete" type="button" :disabled="processingId === beneficiary.id" @click="reportLost(beneficiary)">{{ processingId === beneficiary.id ? 'Updating…' : 'Report Lost' }}</button>
                        <button v-else class="action-btn" type="button" :disabled="processingId === beneficiary.id" @click="generateNew(beneficiary)">{{ processingId === beneficiary.id ? 'Generating…' : 'Generate New' }}</button>
                    </td>
                </tr>
                <tr v-if="!rows.length"><td colspan="3" class="empty-cell">{{ search ? 'No beneficiaries match this search.' : 'No beneficiaries found.' }}</td></tr>
            </tbody>
        </table></div>
    </section>
</template>
