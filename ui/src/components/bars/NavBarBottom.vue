<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAnnouncements } from "../../store/StoreAnnouncements";
import { sanitizeHtml } from "../../models/html";

const announcementsStore = useAnnouncements();
const { getActiveBottomAnnouncements } = storeToRefs(announcementsStore);
const { fetchActiveAnnouncements } = announcementsStore;

onMounted(() => {
    fetchActiveAnnouncements("bottom");
});
</script>

<template>
    <section v-if="getActiveBottomAnnouncements.length" class="bg-slate-200 px-3 pb-3">
        <div class="container mx-auto">
            <div
                v-for="announcement in getActiveBottomAnnouncements"
                :key="announcement.id"
                class="border-x border-slate-300 p-2 text-center font-semibold shadow-sm"
                :class="announcement.style_class"
            >
                <p>{{ announcement.title }}</p>
                <div v-if="announcement.body" class="html-content text-sm" v-html="sanitizeHtml(announcement.body)" />
            </div>
        </div>
    </section>
</template>
