<script setup>
import { onMounted } from "vue";
import useAnnouncements from "../../store/StoreAnnouncements";

const { fetchActiveAnnouncements, getActiveBottomAnnouncements } = useAnnouncements();

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
                <p v-if="announcement.body" class="text-sm">{{ announcement.body }}</p>
            </div>
        </div>
    </section>
</template>
