<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });
defineEmits(['close', 'cancel']);

const dialog = ref(null);

function showModal(options) {
    dialog.value?.showModal(options);
}

function close(returnValue) {
    dialog.value?.close(returnValue);
}

defineExpose({ showModal, close });
</script>

<template>
    <dialog ref="dialog" v-bind="$attrs" @close="$emit('close', $event)" @cancel="$emit('cancel', $event)">
        <slot />
    </dialog>
</template>
