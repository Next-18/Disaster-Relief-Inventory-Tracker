<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
defineProps({ title: { type: String, required: true }, icon: { type: String, required: true }, description: { type: String, required: true } });
</script>

<template>
    <section class="module-empty"><span>{{ icon }}</span><h2>{{ title }}</h2><p>{{ description }}</p><button class="primary-action" type="button" disabled>Coming soon</button></section>
</template>
