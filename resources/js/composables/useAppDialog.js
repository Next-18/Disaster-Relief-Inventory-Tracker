import { reactive } from 'vue';

const dialog = reactive({
    visible: false,
    type: 'alert',
    title: '',
    message: '',
    confirmLabel: 'Continue',
    cancelLabel: 'Cancel',
    danger: false,
});

const queue = [];
let activeResolve = null;

function showNextDialog() {
    if (dialog.visible || activeResolve || queue.length === 0) return;
    const next = queue.shift();
    Object.assign(dialog, next.options, { visible: true });
    activeResolve = next.resolve;
}

function enqueueDialog(options) {
    return new Promise((resolve) => {
        queue.push({ options, resolve });
        showNextDialog();
    });
}

function resolveAppDialog(result) {
    const resolve = activeResolve;
    activeResolve = null;
    dialog.visible = false;
    resolve?.(result);
    queueMicrotask(showNextDialog);
}

export function appAlert(message, options = {}) {
    return enqueueDialog({
        type: 'alert',
        title: options.title ?? 'Notice',
        message: String(message ?? ''),
        confirmLabel: options.confirmLabel ?? 'Got it',
        cancelLabel: 'Cancel',
        danger: false,
    });
}

export function appConfirm(message, options = {}) {
    return enqueueDialog({
        type: 'confirm',
        title: options.title ?? 'Are you sure?',
        message: String(message ?? ''),
        confirmLabel: options.confirmLabel ?? 'Continue',
        cancelLabel: options.cancelLabel ?? 'Cancel',
        danger: options.danger === true,
    });
}

export { dialog as appDialog, resolveAppDialog };
