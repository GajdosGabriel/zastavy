<script setup>
import { computed, nextTick, ref } from 'vue';
import axios from '../../axiosInstance';
import useCustomers from '../../store/StoreCustomers';
import useCheckouts from '../../store/StoreCheckouts';
import { formatPrice } from '../../models/functions';

const emit = defineEmits(['applied']);
const dialog = ref(null);
const textInput = ref(null);
const text = ref('');
const busy = ref(false);
const error = ref('');
const draft = ref(null);
const acknowledged = ref(false);
const fields = { company: 'Firma / obec', name: 'Kontaktné meno', email: 'E-mail', phone: 'Telefón', street: 'Ulica a číslo', postcode: 'PSČ', city: 'Mesto', ico: 'IČO', dic: 'DIČ', ic_dic: 'IČ DPH' };
const selected = computed(() => draft.value?.items.filter(item => item.selected && item.cart) ?? []);
const validQuantities = computed(() => selected.value.every(item => Number.isInteger(Number(item.cart.input_order)) && Number(item.cart.input_order) >= item.cart.min_order && Number(item.cart.input_order) <= 100000));
let opener;
async function open() {
    opener = document.activeElement;
    dialog.value.showModal();
    await nextTick();
    textInput.value?.focus();
}
function close() {
    if (busy.value) return;
    dialog.value.close();
    opener?.focus();
}
async function analyze() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    draft.value = null;
    acknowledged.value = false;
    try {
        const response = await axios.post('/email-order-preview', { text: text.value }, { timeout: 155000 });
        draft.value = response.data.data;
        draft.value.items.forEach(item => { item.selected = Boolean(item.cart); });
    } catch (e) {
        error.value = e.response?.data?.message || 'Spracovanie sa nepodarilo. Skúste to znova.';
    } finally {
        busy.value = false;
    }
}
function apply() {
    if (!draft.value || !acknowledged.value || !validQuantities.value || busy.value) return;
    const carts = useCheckouts();
    // Replace identity too: never retain an ID from a previously selected customer.
    useCustomers().setCustomer({ ...draft.value.customer });
    selected.value.forEach(item => carts.submitCartToIndex({ ...item.cart, input_order: Number(item.cart.input_order) }));
    if (draft.value.note) carts.note = [carts.note, draft.value.note].filter(Boolean).join('\n');
    emit('applied', draft.value.customer);
    draft.value = null;
    text.value = '';
    close();
}
defineExpose({ open });
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="m-auto max-h-[90vh] w-[min(56rem,95vw)] overflow-y-auto rounded-xl bg-white p-0 shadow-xl backdrop:bg-slate-950/50" aria-labelledby="email-import-title" @cancel.prevent="close">
            <div class="flex items-center justify-between border-b p-5">
                <h2 id="email-import-title" class="text-lg font-semibold">Doplniť objednávku z e-mailu</h2>
                <button type="button" :disabled="busy" aria-label="Zavrieť" class="rounded px-3 py-1 text-xl disabled:opacity-40" @click="close">×</button>
            </div>
            <div class="space-y-4 p-5">
                <p class="text-sm text-slate-600">Vložte celý text e-mailu vrátane odosielateľa a podpisu. AI vyhľadá zákazníka, chýbajúce firemné údaje a produkty. Text sa odošle službe OpenAI na spracovanie.</p>
                <label for="email-order-text" class="block text-sm font-semibold">Text e-mailu</label>
                <textarea id="email-order-text" ref="textInput" v-model="text" :disabled="busy" maxlength="20000" rows="8" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Sem vložte objednávku z e-mailu…" @input="draft = null" />
                <button type="button" :disabled="busy || text.trim().length < 20" class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="analyze">{{ busy ? 'Spracúvam e-mail a dohľadávam údaje…' : 'Vyhľadať a pripraviť návrh' }}</button>
                <p v-if="busy" role="status" class="text-sm text-slate-600">Dohľadanie môže trvať približne dve minúty.</p>
                <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
                <template v-if="draft">
                    <div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                        {{ draft.customer_status === 'existing' ? `Existujúci zákazník #${draft.customer.id}` : draft.customer_status === 'ambiguous' ? 'Nejednoznačný zákazník — skontrolujte údaje' : 'Zákazník sa nenašiel v databáze — nový klient' }}
                    </div>
                    <dl class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                        <div v-for="(label, field) in fields" :key="field">
                            <dt class="text-xs text-slate-500">{{ label }}</dt>
                            <dd class="break-words text-sm font-medium">{{ draft.customer[field] || '—' }}</dd>
                        </div>
                    </dl>
                    <p class="text-sm text-slate-600">Údaje môžete po doplnení upraviť vo formulári košíka.</p>
                    <h3 class="font-semibold">Objednávané položky</h3>
                    <div v-for="(item, index) in draft.items" :key="index" class="rounded-lg border p-3 text-sm">
                        <template v-if="item.cart">
                            <label class="flex items-start gap-2"><input v-model="item.selected" type="checkbox" class="mt-1" /><span>{{ item.cart.name }} — {{ item.cart.variant_name }}<span class="block text-xs text-slate-500">Z e-mailu: {{ item.description }}</span></span></label>
                            <div class="mt-2 flex items-center gap-3">
                                <label :for="`email-quantity-${index}`">Počet</label>
                                <input :id="`email-quantity-${index}`" v-model.number="item.cart.input_order" type="number" :min="item.cart.min_order" max="100000" step="1" class="w-24 rounded border-slate-300" />
                                <span>{{ formatPrice(item.cart.active_price) }} € / ks</span>
                            </div>
                        </template>
                        <p v-else class="text-amber-800">{{ item.description }} — treba vybrať produkt alebo množstvo ručne.</p>
                    </div>
                    <p v-if="draft.note" class="whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{{ draft.note }}</p>
                    <ul v-if="draft.warnings.length" class="list-disc space-y-1 rounded-lg bg-amber-50 p-4 pl-8 text-sm text-amber-900"><li v-for="warning in draft.warnings" :key="warning">{{ warning }}</li></ul>
                    <div v-if="draft.sources.length" class="text-sm">
                        <h3 class="mb-1 font-semibold">Zdroje dohľadaných údajov</h3>
                        <a v-for="url in draft.sources" :key="url" :href="url" target="_blank" rel="noopener noreferrer" class="block break-all text-blue-700 underline">{{ url }}</a>
                    </div>
                    <label class="flex items-start gap-2 text-sm"><input v-model="acknowledged" type="checkbox" class="mt-1" /><span>Skontroloval/a som zákazníka, varianty a množstvá. Fakturačné údaje sa nahradia týmto návrhom a vybrané položky sa pridajú do košíka.</span></label>
                    <button type="button" :disabled="!acknowledged || !validQuantities" class="rounded-lg bg-blue-800 px-4 py-2 font-semibold text-white disabled:opacity-40" @click="apply">Doplniť do košíka ({{ selected.length }} položiek)</button>
                    <p class="text-xs text-slate-500">Objednávku odošlete samostatne z košíka. Nový zákazník sa uloží pri vytvorení objednávky.</p>
                </template>
            </div>
        </dialog>
    </Teleport>
</template>
