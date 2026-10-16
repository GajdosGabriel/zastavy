<script setup>
import { computed, onMounted, ref } from "vue";
import axiosInstance from "../../../axiosInstance";
import useErrors from "../../../store/StoreErrors";
import useFlash from "../../../store/StoreFlash";
import { PAGE_ORDER } from "../../../constants";

// História objednávky: čo sa s ňou stalo (dodacie listy, vrátenia) a čo o tom
// zákazník dostal e-mailom, na jednej časovej osi. Odtiaľto sa dá dodatočne
// poslať oznam, ktorý obsluha zabudla poslať — vždy s aktuálnym stavom objednávky.
const props = defineProps({ orderId: { type: [String, Number], required: true } });

const url = computed(() => `${PAGE_ORDER.URL}/${props.orderId}/emails`);
const emails = ref([]);
const timeline = ref([]);
const options = ref([]);
const recipient = ref(null);
const changedSinceLastEmail = ref(false);
const loaded = ref(false);

const showModal = ref(false);
const selected = ref(null);
const note = ref("");
const preview = ref(null);
const previewLoading = ref(false);
const sending = ref(false);
const viewed = ref(null);

const load = async () => {
    try {
        const { data } = await axiosInstance.get(url.value);
        emails.value = data.data;
        timeline.value = data.timeline ?? [];
        options.value = data.options;
        recipient.value = data.recipient;
        changedSinceLastEmail.value = data.changed_since_last_email;
    } catch (e) {
        useErrors().setErrors(e);
    } finally {
        loaded.value = true;
    }
};

onMounted(load);

const optionKey = (o) => o.type + (o.shipping_id ? ":" + o.shipping_id : "");
const payload = () => ({
    type: selected.value.type,
    shipping_id: selected.value.shipping_id ?? null,
    note: selected.value.type === "update" ? note.value : null,
});

/** @param {string|null} type  predvolený typ oznamu, napr. „update“ po úprave položiek */
const openModal = (type = null) => {
    selected.value = null;
    note.value = "";
    preview.value = null;
    showModal.value = true;
    const option = type && options.value.find((o) => o.type === type);
    if (option) choose(option);
};

const choose = async (option) => {
    selected.value = option;
    await loadPreview();
};

const loadPreview = async () => {
    if (!selected.value) return;
    previewLoading.value = true;
    try {
        const { data } = await axiosInstance.post(url.value + "/preview", payload());
        preview.value = data;
    } catch (e) {
        preview.value = null;
        useErrors().setErrors(e);
    } finally {
        previewLoading.value = false;
    }
};

const send = async () => {
    sending.value = true;
    try {
        const { data } = await axiosInstance.post(url.value, payload());
        showModal.value = false;
        useFlash().success(data.message);
        // Fronta e-mail odošle o chvíľu; históriu obnovíme s malým odstupom.
        setTimeout(load, 3000);
    } catch (e) {
        useErrors().setErrors(e);
    } finally {
        sending.value = false;
    }
};

const openEmail = async (event) => {
    try {
        const { data } = await axiosInstance.get(`${url.value}/${event.email_id}`);
        viewed.value = { ...data, label: event.label, created_at: event.at_human };
    } catch (e) {
        useErrors().setErrors(e);
    }
};
defineExpose({ load, openModal });
</script>

<template>
    <div class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-2.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                História objednávky
                <span v-if="emails.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600"
                    :title="'E-mailov zákazníkovi: ' + emails.length">✉ {{ emails.length }}</span>
            </span>
            <button v-if="options.length && recipient" type="button" @click="openModal()"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                Poslať zákazníkovi e-mail
            </button>
        </div>

        <p v-if="loaded && !recipient" class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
            Objednávka nemá e-mail zákazníka, oznamy sa nedajú poslať.
        </p>
        <p v-else-if="changedSinceLastEmail" class="flex flex-wrap items-center gap-2 border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
            Položky objednávky sa zmenili po poslednom e-maile — zákazník nemusí mať aktuálny stav.
            <button type="button" class="font-semibold underline" @click="openModal('update')">Poslať zmenu objednávky</button>
        </p>
        <p v-else-if="loaded && recipient && !emails.length" class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
            Zákazníkovi zatiaľ neodišiel žiadny e-mail.
        </p>

        <div v-if="!loaded" class="px-5 py-5 text-center text-sm text-gray-500">Načítavam…</div>

        <ol v-else class="px-5 py-3">
            <li v-for="(event, index) in timeline" :key="index" class="relative flex gap-3 pb-4 last:pb-0">
                <!-- Zvislá čiara časovej osi -->
                <span v-if="index < timeline.length - 1" class="absolute left-[11px] top-6 h-full w-px bg-gray-200" aria-hidden="true"></span>
                <span class="relative z-[1] mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="event.status === 'failed' ? 'bg-red-100 text-red-700'
                        : event.status === 'pending' ? 'bg-sky-100 text-sky-700'
                        : event.kind === 'email' ? 'bg-blue-100 text-blue-700'
                        : event.kind === 'shipping' ? 'bg-green-100 text-green-700'
                        : event.kind === 'return' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600'"
                    aria-hidden="true">{{ { email: '✉', shipping: '▣', return: '↩', order: '●' }[event.kind] ?? '•' }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-3">
                        <span class="text-sm font-medium" :class="event.status === 'failed' ? 'text-red-700' : 'text-gray-900'">{{ event.label }}</span>
                        <span v-if="event.status === 'pending'" class="text-xs font-semibold text-sky-700">čaká na odoslanie</span>
                        <span class="text-xs text-gray-500">{{ event.at_human }}</span>
                        <button v-if="event.email_id" type="button" @click="openEmail(event)"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800">Zobraziť e-mail</button>
                        <span v-else-if="event.body_expired" class="text-xs text-gray-500"
                            title="Obsah starších e-mailov sa po čase maže, záznam o odoslaní zostáva.">obsah sa už neuchováva</span>
                    </div>
                    <div v-if="event.detail" class="truncate text-xs text-gray-500" :title="event.detail">{{ event.detail }}</div>
                </div>
            </li>
        </ol>
    </div>

    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showModal = false">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded bg-white p-5 shadow-lg">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">Poslať zákazníkovi e-mail</h3>
                <p class="mb-4 text-sm text-gray-600">Odíde na <strong>{{ recipient }}</strong> s aktuálnym stavom objednávky, do toho istého vlákna ako potvrdenie.</p>

                <div class="mb-4 grid gap-2 sm:grid-cols-2">
                    <label v-for="option in options" :key="optionKey(option)"
                        class="flex cursor-pointer gap-2 rounded border p-3 text-sm"
                        :class="selected && optionKey(selected) === optionKey(option) ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" name="order-email-type" class="mt-0.5"
                            :checked="selected && optionKey(selected) === optionKey(option)" @change="choose(option)" />
                        <span>
                            <span class="block font-semibold text-gray-800">{{ option.label }}</span>
                            <span class="block text-xs text-gray-500">{{ option.hint }}</span>
                        </span>
                    </label>
                </div>

                <label v-if="selected?.type === 'update'" class="mb-4 block text-sm text-gray-700">
                    Poznámka pre zákazníka (napr. dôvod storna položky)
                    <textarea v-model="note" rows="2" maxlength="2000" @change="loadPreview"
                        class="mt-1 block w-full rounded border border-gray-300 p-2 text-sm"></textarea>
                </label>

                <div v-if="selected" class="mb-4 min-h-0 flex-1 overflow-hidden rounded border border-gray-200">
                    <div class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-xs text-gray-600">
                        Náhľad{{ preview ? ': ' + preview.subject : '' }}
                    </div>
                    <div v-if="previewLoading" class="p-6 text-center text-sm text-gray-500">Pripravujem náhľad…</div>
                    <iframe v-else-if="preview" :srcdoc="preview.html" sandbox="" title="Náhľad e-mailu" class="h-[45vh] w-full"></iframe>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="showModal = false"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Zrušiť</button>
                    <button type="button" @click="send" :disabled="!selected || !preview || sending || previewLoading"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ sending ? 'Odosielam…' : 'Odoslať' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="viewed" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="viewed = null">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded bg-white p-5 shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800">{{ viewed.label }}</h3>
                <p class="mb-3 text-sm text-gray-600">{{ viewed.subject }} · {{ viewed.created_at }}</p>
                <iframe :srcdoc="viewed.html" sandbox="" title="Odoslaný e-mail" class="mb-4 h-[60vh] w-full rounded border border-gray-200"></iframe>
                <div class="flex justify-end">
                    <button type="button" @click="viewed = null"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Zavrieť</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
