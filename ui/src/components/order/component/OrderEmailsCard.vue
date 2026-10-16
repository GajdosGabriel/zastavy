<script setup>
import { computed, onMounted, ref } from "vue";
import axiosInstance from "../../../axiosInstance";
import useErrors from "../../../store/StoreErrors";
import useFlash from "../../../store/StoreFlash";
import { PAGE_ORDER } from "../../../constants";

// História e-mailov zákazníkovi k objednávke a dodatočné odoslanie oznamu,
// ktorý obsluha pri zmene zabudla poslať. Posiela sa vždy aktuálny stav objednávky.
const props = defineProps({ orderId: { type: [String, Number], required: true } });

const url = computed(() => `${PAGE_ORDER.URL}/${props.orderId}/emails`);
const emails = ref([]);
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
defineExpose({ load });

const optionKey = (o) => o.type + (o.shipping_id ? ":" + o.shipping_id : "");
const payload = () => ({
    type: selected.value.type,
    shipping_id: selected.value.shipping_id ?? null,
    note: selected.value.type === "update" ? note.value : null,
});

const openModal = () => {
    selected.value = null;
    note.value = "";
    preview.value = null;
    showModal.value = true;
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

const openEmail = async (email) => {
    try {
        const { data } = await axiosInstance.get(`${url.value}/${email.id}`);
        viewed.value = { ...data, label: email.label, created_at: email.created_at };
    } catch (e) {
        useErrors().setErrors(e);
    }
};
</script>

<template>
    <div class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-2.5">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                E-maily zákazníkovi
                <span v-if="emails.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600">{{ emails.length }}</span>
            </span>
            <button v-if="options.length && recipient" type="button" @click="openModal"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                Poslať zákazníkovi e-mail
            </button>
        </div>

        <p v-if="loaded && !recipient" class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
            Objednávka nemá e-mail zákazníka, oznamy sa nedajú poslať.
        </p>
        <p v-else-if="changedSinceLastEmail" class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
            Položky objednávky sa zmenili po poslednom e-maile — zákazník nemusí mať aktuálny stav.
        </p>

        <div v-if="!loaded" class="px-5 py-5 text-center text-sm text-gray-500">Načítavam…</div>
        <div v-else-if="!emails.length" class="px-5 py-5 text-center text-sm text-gray-500">
            Zákazníkovi zatiaľ neodišiel žiadny e-mail.
        </div>

        <ul v-else class="divide-y divide-gray-50">
            <li v-for="email in emails" :key="email.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900">
                        {{ email.label }}
                        <span v-if="email.status === 'failed'" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700"
                            :title="email.error">Neodoslaný</span>
                    </div>
                    <div class="truncate text-xs text-gray-500">{{ email.subject }} · {{ email.recipient }}</div>
                </div>
                <span class="flex shrink-0 items-center gap-3">
                    <span class="text-xs text-gray-500">{{ email.created_at }}</span>
                    <button v-if="email.has_body" type="button" @click="openEmail(email)"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-800">Zobraziť</button>
                </span>
            </li>
        </ul>
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
