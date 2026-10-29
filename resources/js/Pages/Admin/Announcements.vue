<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { appConfirm } from '../../composables/useAppDialog';

defineOptions({ layout: AdminLayout });

defineProps({
    announcements: { type: Array, required: true },
    canDeleteAnnouncements: { type: Boolean, default: false },
});
const page = usePage();
const routes = computed(() => page.props.routeUrls ?? {});
const form = useForm({ title: '', body: '' });

function publish() {
    form.post(routes.value.announcementStore, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

async function removeAnnouncement(announcement) {
    const confirmed = await appConfirm(`Remove “${announcement.title}” from the staff portal?`, {
        title: 'Remove announcement?',
        confirmLabel: 'Remove announcement',
        danger: true,
    });
    if (!confirmed) return;

    router.delete(routes.value.announcementDelete.replace('__ID__', encodeURIComponent(announcement.id)), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="module-heading"><div><h2>Announcements</h2><p>Publish updates for the staff portal and beneficiary mobile app.</p></div></section>

    <section class="panel record-panel announcement-compose">
        <div class="panel-heading"><div><h3>Write an announcement</h3><p>It will appear in the staff portal and the mobile app announcements feed.</p></div></div>
        <form class="announcement-form" @submit.prevent="publish">
            <label>Title
                <input v-model="form.title" maxlength="150" required placeholder="e.g. Distribution schedule update">
                <small v-if="form.errors.title" class="field-error">{{ form.errors.title }}</small>
            </label>
            <label>Message
                <textarea v-model="form.body" maxlength="5000" rows="5" required placeholder="Write the details staff need to know."></textarea>
                <small v-if="form.errors.body" class="field-error">{{ form.errors.body }}</small>
            </label>
            <div class="announcement-actions"><span>Visible to staff, volunteers, and beneficiaries</span><button class="primary-action" type="submit" :disabled="form.processing">{{ form.processing ? 'Publishing…' : 'Publish announcement' }}</button></div>
        </form>
    </section>

    <section class="panel record-panel announcement-list">
        <div class="panel-heading"><div><h3>Published announcements</h3><p>{{ announcements.length }} {{ announcements.length === 1 ? 'announcement' : 'announcements' }}</p></div></div>
        <div v-if="announcements.length" class="announcement-items">
            <article v-for="announcement in announcements" :key="announcement.id" class="announcement-item">
                <div class="announcement-item-copy">
                    <h4>{{ announcement.title }}</h4>
                    <p>{{ announcement.body }}</p>
                    <small>Published {{ new Date(announcement.created_at).toLocaleString() }}<span v-if="announcement.creator"> · {{ announcement.creator.name }}</span></small>
                </div>
                <button v-if="canDeleteAnnouncements" type="button" class="announcement-remove" @click="removeAnnouncement(announcement)">Remove</button>
            </article>
        </div>
        <div v-else class="announcement-empty">No announcements have been published yet.</div>
    </section>
</template>

<style scoped>
.announcement-compose, .announcement-list { margin-bottom: 16px; }
.announcement-form { display: grid; gap: 16px; padding: 20px; }
.announcement-form label { display: grid; gap: 7px; color: #51647c; font-size: 12px; font-weight: 700; }
.announcement-form input, .announcement-form textarea { width: 100%; padding: 11px 12px; border: 1px solid #d9e2ec; border-radius: 8px; color: #29496f; font: inherit; font-size: 13px; }
.announcement-form textarea { resize: vertical; line-height: 1.5; }
.announcement-form input:focus, .announcement-form textarea:focus { border-color: #8db4ed; outline: 3px solid #e7f0ff; }
.announcement-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.announcement-actions span { color: #718096; font-size: 12px; }
.announcement-items { display: grid; }
.announcement-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; padding: 18px 20px; border-top: 1px solid #edf1f5; }
.announcement-item-copy { min-width: 0; }
.announcement-item h4 { margin: 0; color: #203c5c; font-size: 15px; }
.announcement-item p { margin: 7px 0; color: #52657d; font-size: 13px; line-height: 1.6; white-space: pre-line; }
.announcement-item small { color: #8592a3; font-size: 11px; }
.announcement-remove { flex: 0 0 auto; padding: 7px 10px; border: 1px solid #f0d1cd; border-radius: 7px; background: #fff; color: #a34338; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.announcement-remove:hover { background: #fff5f3; }
.announcement-empty { padding: 28px 20px; color: #8190a1; font-size: 13px; text-align: center; }
@media (max-width: 600px) { .announcement-actions { align-items: stretch; flex-direction: column; }.announcement-actions .primary-action { width: 100%; }.announcement-item { padding: 16px; } }
</style>
