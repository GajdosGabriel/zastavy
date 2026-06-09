<script setup>
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import ButtonSubmit from "../layout/page/ButtonSubmit.vue";
import PaginationComponent from "../plugins/pagination.vue";
import PanelDropdown from "../layout/PanelDropdown.vue";
import useAnnouncements from "../../store/StoreAnnouncements";
import FormInput from "../forms/FormInput.vue";

const {
    state,
    fetchAnnouncements,
    saveAnnouncement,
    editAnnouncement,
    destroyAnnouncement,
    resetAnnouncement,
    setPaginator,
    getAnnouncements,
    getStatuses,
    getPlacements,
    getStyleClasses,
} = useAnnouncements();

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
                            <FormInput v-model="state.announcement.title" placeholder="Názov oznamu" required />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Umiestnenie</label>
                            <select v-model="state.announcement.placement" required class="form-control rounded border px-3 py-2">
                                <option v-for="placement in getPlacements" :key="placement.value" :value="placement.value">
                                    {{ placement.label }}
                                </option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-bold text-slate-700">Text</label>
                            <textarea v-model="state.announcement.body" rows="3" class="form-control rounded border px-3 py-2"></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Farba</label>
                            <select v-model="state.announcement.style_class" required class="form-control rounded border px-3 py-2">
                                <option v-for="styleClass in getStyleClasses" :key="styleClass.value" :value="styleClass.value">
                                    {{ styleClass.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Status</label>
                            <select v-model="state.announcement.status.value" required class="form-control rounded border px-3 py-2">
                                <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Zobrazovať od</label>
                            <input v-model="state.announcement.published_from" type="datetime-local" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Zobrazovať do</label>
                            <input v-model="state.announcement.published_until" type="datetime-local" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">Poradie</label>
                            <input v-model.number="state.announcement.sort_order" type="number" min="0" class="form-control rounded border px-3 py-2" />
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
                                <td class="tbody_td">{{ announcement.status?.label || "-" }}</td>
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
