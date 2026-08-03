<script setup>
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import ButtonSubmit from "../layout/page/ButtonSubmit.vue";
import PaginationComponent from "../plugins/pagination.vue";
import PanelDropdown from "../layout/PanelDropdown.vue";
import { storeToRefs } from "pinia";
import { useAnnouncements } from "../../store/StoreAnnouncements";
import FormInput from "../forms/FormInput.vue";

const store = useAnnouncements();
const { announcement, getAnnouncements, getStatuses, getPlacements, getStyleClasses } = storeToRefs(store);
const {
    fetchAnnouncements,
    saveAnnouncement,
    editAnnouncement,
    destroyAnnouncement,
    toggleAnnouncement,
    resetAnnouncement,
    setPaginator,
} = store;

const isVisible = (item) => (item.status?.value ?? item.status) === 'active';

onMounted(fetchAnnouncements);

const pageHeader = {
    title: "Oznamy",
};

const buttonSubmit = {
    name: "Uložiť oznam",
    spinner: true,
};

const dropdownItems = (announcement) => [
    {
        label: "Upraviť",
        onClick: () => editAnnouncement(announcement),
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

                <form class="mb-6 rounded-md border border-slate-300 bg-white p-4 shadow-sm" @submit.prevent="saveAnnouncement">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Názov</label>
                            <FormInput v-model="announcement.title" placeholder="Názov oznamu" required />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Umiestnenie</label>
                            <select v-model="announcement.placement" required class="form-control rounded border px-3 py-2">
                                <option v-for="placement in getPlacements" :key="placement.value" :value="placement.value">
                                    {{ placement.label }}
                                </option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-bold text-slate-700">Text</label>
                            <textarea v-model="announcement.body" rows="3" class="form-control rounded border px-3 py-2"></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Farba</label>
                            <select v-model="announcement.style_class" required class="form-control rounded border px-3 py-2">
                                <option v-for="styleClass in getStyleClasses" :key="styleClass.value" :value="styleClass.value">
                                    {{ styleClass.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Status</label>
                            <select v-model="announcement.status.value" required class="form-control rounded border px-3 py-2">
                                <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Zobrazovať od</label>
                            <input v-model="announcement.published_from" type="datetime-local" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Zobrazovať do</label>
                            <input v-model="announcement.published_until" type="datetime-local" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Poradie</label>
                            <input v-model.number="announcement.sort_order" type="number" min="0" class="form-control rounded border px-3 py-2" />
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-between">
                        <button type="button" class="btn btn-secondary" @click="resetAnnouncement">Nový oznam</button>
                        <ButtonSubmit :item="buttonSubmit" />
                    </div>
                </form>

                <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
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
                                    <div class="text-slate-500">{{ announcement.body || "-" }}</div>
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
