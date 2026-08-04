<script setup>
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useAnnouncements } from "../../store/StoreAnnouncements";
import { sanitizeHtml } from "../../models/html";

const announcementsStore = useAnnouncements();
const { getActiveTopAnnouncements } = storeToRefs(announcementsStore);
const { fetchActiveAnnouncements } = announcementsStore;

onMounted(() => {
    fetchActiveAnnouncements("top");
});
</script>

<template>
    <div
        v-for="announcement in getActiveTopAnnouncements"
        :key="announcement.id"
        class="p-1 text-center text-lg"
        :class="announcement.style_class"
    >
        <strong>
            <div>{{ announcement.title }}</div>
            <div v-if="announcement.body" class="html-content" v-html="sanitizeHtml(announcement.body)" />
        </strong>
    </div>
</template>
