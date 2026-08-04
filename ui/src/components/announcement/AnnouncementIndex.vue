<script setup>
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import PaginationComponent from "../plugins/pagination.vue";
import PanelDropdown from "../layout/PanelDropdown.vue";
import { storeToRefs } from "pinia";
import router from "../../router";
import { useAnnouncements } from "../../store/StoreAnnouncements";
import { htmlToText } from "../../models/html";

const store = useAnnouncements();
const { getAnnouncements } = storeToRefs(store);
const {
    fetchAnnouncements,
    destroyAnnouncement,
    toggleAnnouncement,
    setPaginator,
} = store;

const isVisible = (item) => (item.status?.value ?? item.status) === 'active';

onMounted(fetchAnnouncements);

const pageHeader = {
    title: "Oznamy",
    buttonLink: { name: "Nový oznam", link: "/oznamy/create", icon: "plus" },
};

const dropdownItems = (announcement) => [
    {
        label: "Upraviť",
        onClick: () => router.push({ name: "announcements.edit", params: { announcementId: announcement.id } }),
    },
    {
        label: "Zmazať",
        onClick: () => destroyAnnouncement(announcement),
    },
];
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="pageHeader" />

                <div class="mt-5 overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th">Názov</th>
                                <th class="thead_th">Umiestnenie</th>
                                <th class="thead_th">Status</th>
                                <th class="thead_th">Zobrazovanie</th>
                                <th class="thead_th">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="announcement in getAnnouncements" :key="announcement.id">
                                <td class="tbody_td">
                                    <div class="font-semibold text-slate-800">{{ announcement.title }}</div>
                                    <div class="text-slate-500">{{ htmlToText(announcement.body) || "-" }}</div>
                                </td>
                                <td class="tbody_td">{{ announcement.placement }}</td>
                                <td class="tbody_td">
                                    <button type="button" @click="toggleAnnouncement(announcement)"
                                        :title="isVisible(announcement)
                                            ? 'Vypnúť zobrazovanie — text zostane uložený'
                                            : 'Zapnúť zobrazovanie'"
                                        class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                                        :class="isVisible(announcement)
                                            ? 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100'
                                            : 'border-slate-300 bg-slate-50 text-slate-500 hover:bg-slate-100'">
                                        <span class="h-2 w-2 rounded-full"
                                            :class="isVisible(announcement) ? 'bg-green-500' : 'bg-slate-400'" />
                                        {{ isVisible(announcement) ? 'zobrazuje sa' : 'vypnutý' }}
                                    </button>
                                </td>
                                <td class="tbody_td">
                                    <div>Od: {{ announcement.published_from || "-" }}</div>
                                    <div>Do: {{ announcement.published_until || "-" }}</div>
                                </td>
                                <td class="tbody_td">
                                    <PanelDropdown :items="dropdownItems(announcement)" />
                                </td>
                            </tr>
                            <tr v-if="!getAnnouncements.length">
                                <td colspan="5" class="tbody_td py-8 text-center">Žiadne oznamy</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <PaginationComponent @setUrl="setPaginator" />
            </div>
        </template>
    </BaseLayout>
</template>
