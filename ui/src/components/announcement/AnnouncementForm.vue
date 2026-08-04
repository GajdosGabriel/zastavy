<script setup>
import { computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { storeToRefs } from "pinia";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import ButtonSubmit from "../layout/page/ButtonSubmit.vue";
import FormInput from "../forms/FormInput.vue";
import HtmlEditor from "../forms/HtmlEditor.vue";
import router from "../../router";
import { useAnnouncements } from "../../store/StoreAnnouncements";

const store = useAnnouncements();
const { announcement, getStatuses, getPlacements, getStyleClasses } = storeToRefs(store);
const { fetchAnnouncement, fetchAnnouncements, saveAnnouncement, resetAnnouncement } = store;

const announcementId = computed(() => useRoute().params.announcementId);

onMounted(async () => {
    if (announcementId.value) {
        await fetchAnnouncement(announcementId.value);
        return;
    }

    resetAnnouncement();

    // Číselníky (umiestnenie, farby, statusy) chodia v meta zoznamu — pri
    // priamom vstupe na /oznamy/create nie sú v store ešte načítané.
    if (!getPlacements.value.length) {
        await fetchAnnouncements();
    }
});

const onSubmitForm = async () => {
    if (await saveAnnouncement()) {
        router.push({ name: "announcements.index" });
    }
};

const pageHeader = computed(() => ({
    title: announcementId.value ? "Upraviť oznam" : "Nový oznam",
    buttonLink: { name: "Späť", link: "/oznamy", icon: "arrow-left" },
}));

const buttonSubmit = { name: "Uložiť oznam", spinner: true };
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="pageHeader" />

                <form class="mt-5 rounded-md border border-slate-300 bg-white p-4 shadow-sm" @submit.prevent="onSubmitForm">
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
                            <HtmlEditor v-model="announcement.body" placeholder="Text oznamu" min-height="6rem" />
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

                    <div class="mt-5 flex justify-end">
                        <ButtonSubmit :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
