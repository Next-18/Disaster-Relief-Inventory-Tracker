<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Array, required: true },
    show: { type: Boolean, default: false },
});

function labelFor(label) {
    if (label.toLowerCase().includes('previous')) return '‹';
    if (label.toLowerCase().includes('next')) return '›';
    return label.replaceAll('&laquo;', '‹').replaceAll('&raquo;', '›');
}
</script>

<template>
    <nav v-if="show" class="pagination-wrap" aria-label="Pagination">
        <template v-for="(link, index) in links" :key="index">
            <Link v-if="link.url" :href="link.url" class="pagination-link" :class="{ active: link.active }" preserve-scroll>{{ labelFor(link.label) }}</Link>
            <span v-else class="pagination-link disabled" aria-disabled="true">{{ labelFor(link.label) }}</span>
        </template>
    </nav>
</template>
