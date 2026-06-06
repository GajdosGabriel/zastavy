<script setup>
import { onMounted } from "vue";
import useAnnouncements from "../../store/StoreAnnouncements";

const { fetchActiveAnnouncements, getActiveTopAnnouncements } = useAnnouncements();

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
            <div v-if="announcement.body">{{ announcement.body }}</div>
        </strong>
    </div>
</template>
