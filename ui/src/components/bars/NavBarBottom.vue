<script setup>
import { onMounted } from "vue";
import useAnnouncements from "../../store/StoreAnnouncements";

const { fetchActiveAnnouncements, getActiveBottomAnnouncements } = useAnnouncements();

onMounted(() => {
    fetchActiveAnnouncements("bottom");
});
</script>

<template>
    <div
        v-for="announcement in getActiveBottomAnnouncements"
        :key="announcement.id"
        class="p-2 text-center font-semibold"
        :class="announcement.style_class"
    >
        <p>{{ announcement.title }}</p>
        <p v-if="announcement.body" class="text-sm">{{ announcement.body }}</p>
    </div>
</template>
