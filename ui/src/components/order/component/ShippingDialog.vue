<script setup>
import { computed, ref, watch } from "vue";
import axiosInstance from "../../../axiosInstance";
import useErrors from "../../../store/StoreErrors";
import { useShippings } from "../../../store/StoreShippings";

// Potvrdenie dodacieho listu s náhľadom e-mailu zákazníkovi:
//  - items:    nový DL z vybraných kusov (tovar sa začne pripravovať v sklade),
//  - shipping: potvrdenie, že pripravený DL prevzal prepravca.
const props = defineProps({
    order: { type: Object, required: true },
    items: { type: Array, default: null },
    shipping: { type: Object, default: null },
});
const emit = defineEmits(["close", "done"]);

const { storeShipping } = useShippings();
const { setErrors } = useErrors();

const open = computed(() => !!props.items || !!props.shipping);
const isDispatch = computed(() => !!props.shipping);
const notifyCustomer = ref(true);
const preview = ref(null);
const previewLoading = ref(false);
const confirming = ref(false);

const loadPreview = async () => {
    preview.value = null;
    previewLoading.value = true;
    try {
        const { data } = await axiosInstance.post(`/orders/${props.order.id}/shippings/preview`,
            isDispatch.value ? { shipping_id: props.shipping.id } : { items: props.items });
        preview.value = data;
    } catch (e) {
        setErrors(e);
    } finally {
        previewLoading.value = false;
    }
};

watch(open, (isOpen) => {
    if (!isOpen) return;
    notifyCustomer.value = true;
    loadPreview();
}, { immediate: true });

const confirm = async () => {
    if (confirming.value) return;
    confirming.value = true;
    try {
        if (isDispatch.value) {
            await axiosInstance.put(`/orders/${props.order.id}/shippings/${props.shipping.id}`, { notify_customer: notifyCustomer.value });
        } else if (!(await storeShipping(props.order, { notify_customer: notifyCustomer.value, items: props.items }))) {
            return;
        }
        emit("done");
    } catch (e) {
        setErrors(e);
    } finally {
        confirming.value = false;
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded bg-white p-5 shadow-lg">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">{{ isDispatch ? "Potvrdiť odoslanie" : "Pripraviť dodací list" }}</h3>
                <p class="mb-4 text-sm text-gray-600">
                    {{ isDispatch ? "Potvrdzujete skutočné odoslanie zásielky. Sklad sa odpíše teraz." : "Objednávka sa začne pripravovať v sklade. Zásielka ešte nie je odoslaná." }}
                </p>
                <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="notifyCustomer" type="checkbox" class="rounded" />
                    {{ isDispatch ? "Informovať zákazníka o odoslaní" : "Informovať zákazníka o príprave v sklade" }}
                </label>
                <div v-if="notifyCustomer" class="mb-4">
                    <p v-if="previewLoading" class="text-sm">Načítavam náhľad e-mailu…</p>
                    <template v-if="preview">
                        <p class="mb-2 text-sm font-semibold">Predmet: {{ preview.subject }}</p>
                        <iframe title="Náhľad e-mailu zákazníkovi" sandbox="" :srcdoc="preview.html" class="h-72 w-full rounded border bg-white"></iframe>
                    </template>
                </div>
                <p v-else class="mb-4 text-sm text-amber-700">E-mail sa dá poslať aj neskôr z Histórie objednávky.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="emit('close')"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Zrušiť</button>
                    <button type="button" @click="confirm" :disabled="confirming || previewLoading || (notifyCustomer && !preview)"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-400">
                        {{ confirming ? "Ukladám…" : "Potvrdiť" }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
