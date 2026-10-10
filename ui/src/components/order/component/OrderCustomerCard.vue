<script setup>
import { computed, reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useUsers } from "../../../store/StoreUsers";
import useOrders from "../../../store/StoreOrders";
import axiosInstance from "../../../axiosInstance";

const props = defineProps({
    customer: { type: Object, default: () => ({}) },
    user:     { type: Object, default: null },
    order:    { type: Object, default: () => ({}) },
});

// Karta „Zákazník" ukazuje dnešný riadok z tabuľky zákazníkov (aj s DIČ
// doplneným z registra), karta „Objednávka" to, čo bolo v objednávke.
const current = computed(() => props.order?.customer_current ?? props.customer ?? {});
const billing = computed(() => props.order?.billing ?? props.customer ?? {});

const { getUser } = storeToRefs(useUsers());
const isSuperAdmin = computed(() => getUser.value?.roles?.includes("super-admin"));
const { fetchOrder } = useOrders();

const copied = ref(null);

const copyValue = async (key, value) => {
    if (!value) return;
    try {
        await navigator.clipboard.writeText(String(value));
    } catch (e) {
        // Fallback pre prehliadače bez clipboard API (napr. http bez localhost)
        const input = document.createElement("textarea");
        input.value = String(value);
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
    }
    copied.value = key;
    setTimeout(() => { if (copied.value === key) copied.value = null; }, 1500);
};

const taxIds = computed(() => [
    { key: 'ico', label: 'IČO' },
    { key: 'dic', label: 'DIČ' },
    { key: 'ic_dic', label: 'IČ DPH' },
].filter(item => current.value[item.key]));

const billingText = computed(() => [
    current.value.company || current.value.name,
    current.value.street,
    [current.value.postcode, current.value.city].filter(Boolean).join(' '),
    ...taxIds.value.map(item => `${item.label}: ${current.value[item.key]}`),
].filter(Boolean).join('\n'));

// Čím sa údaje z objednávky líšia od dnešného zákazníka. Porovnáva sa bez
// medzier a veľkosti písmen, IČO bez úvodných núl — inak by sa „líšilo" všetko.
const DIFF_FIELDS = [
    { key: 'company', label: 'Firma' },
    { key: 'name', label: 'Meno' },
    { key: 'street', label: 'Ulica' },
    { key: 'postcode', label: 'PSČ' },
    { key: 'city', label: 'Mesto' },
    { key: 'ico', label: 'IČO' },
    { key: 'dic', label: 'DIČ' },
    { key: 'ic_dic', label: 'IČ DPH' },
];
const normalize = (key, value) => {
    const text = String(value ?? '').toLowerCase().replace(/\s+/g, key === 'company' || key === 'name' || key === 'street' || key === 'city' ? ' ' : '').trim();
    return key === 'ico' ? text.replace(/^0+/, '') : text;
};
const differences = computed(() => DIFF_FIELDS
    .filter(({ key }) => normalize(key, billing.value[key]) !== normalize(key, current.value[key]))
    .map(({ key, label }) => ({ key, label, value: billing.value[key] })));

// Zákazník má IČO, ale DIČ nie — dohľadá sa v registri hneď pri otvorení,
// nie až pri behu post-kontroly. Zapisuje sa zákazníkovi, nie do objednávky.
const lookup = reactive({ loading: false, failed: false, orderId: null });
const canLookup = computed(() => !!props.order?.id && !!current.value.ico && !current.value.dic
    && !!props.order?.permissions?.ship?.allowed);

const lookupTaxIds = async () => {
    if (lookup.loading || !canLookup.value) return;
    const orderId = props.order.id;
    lookup.loading = true;
    lookup.failed = false;
    lookup.orderId = orderId;
    try {
        const { data } = await axiosInstance.post(`/orders/${orderId}/customer-tax-ids`);
        if (data.filled?.length) await fetchOrder(orderId);
        else lookup.failed = true;
    } catch (e) {
        lookup.failed = true;
    } finally {
        lookup.loading = false;
    }
};

watch(() => [props.order?.id, canLookup.value], ([id]) => {
    if (id !== lookup.orderId) lookup.failed = false;
    if (id && id !== lookup.orderId) lookupTaxIds();
}, { immediate: true });
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">

        <!-- Firma / zákazník -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</div>

            <div class="flex gap-4">
                <!-- Ľavá strana: firma, adresa, IČO -->
                <div class="flex-1 min-w-0">
                    <div class="mb-1 text-base font-bold text-gray-900 truncate">
                        <router-link v-if="isSuperAdmin && current.id"
                            :to="{ name: 'customers.show', params: { customerId: current.id } }"
                            class="cursor-pointer">
                            {{ current.company || current.name || '—' }}
                        </router-link>
                        <template v-else>{{ current.company || current.name || '—' }}</template>
                    </div>

                    <div v-if="current.street || current.city" class="mb-2 text-sm text-gray-600 leading-snug">
                        <div v-if="current.street">{{ current.street }}</div>
                        <div>{{ [current.postcode, current.city].filter(Boolean).join(' ') }}</div>
                    </div>

                    <div v-if="taxIds.length" class="border-t border-gray-100 pt-2 text-xs text-gray-500 space-y-0.5">
                        <div v-for="item in taxIds" :key="item.key" class="flex items-center gap-1">
                            <span>{{ item.label }}: <strong class="text-gray-700">{{ current[item.key] }}</strong></span>
                            <button type="button" @click="copyValue(item.key, current[item.key])"
                                class="rounded p-0.5 text-gray-400 transition hover:bg-gray-100 hover:text-blue-600"
                                :title="copied === item.key ? 'Skopírované' : `Kopírovať ${item.label}`">
                                <svg v-if="copied === item.key" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m0 0H5.625" />
                                </svg>
                            </button>
                            <span v-if="copied === item.key" class="text-[10px] font-semibold text-green-600">Skopírované</span>
                        </div>
                    </div>

                    <!-- DIČ chýba: dohľadáva sa v registri podľa IČO -->
                    <div v-if="canLookup" class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="lookup.loading" class="text-gray-500">Hľadám DIČ v registri…</span>
                        <template v-else>
                            <span class="font-semibold text-red-600">
                                {{ lookup.failed ? 'DIČ sa v registri nenašlo' : 'DIČ chýba' }}
                            </span>
                            <button type="button" @click="lookupTaxIds"
                                class="rounded border border-gray-200 px-2 py-0.5 font-semibold text-gray-700 transition hover:bg-gray-100">
                                Dohľadať v registri
                            </button>
                        </template>
                    </div>

                    <button v-if="billingText" type="button" @click="copyValue('billing', billingText)"
                        class="mt-2 rounded border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-100">
                        {{ copied === 'billing' ? 'Skopírované' : 'Kopírovať fakturačné údaje' }}
                    </button>
                </div>

                <!-- Pravá strana: kontaktná osoba, tel, email -->
                <div class="shrink-0 border-l border-gray-100 pl-4 text-sm space-y-1.5">
                    <div v-if="current.company && current.name" class="font-medium text-gray-700">
                        {{ current.name }}
                    </div>
                    <a v-if="user?.phone || current.phone"
                        :href="`tel:${user?.phone || current.phone}`"
                        class="flex items-center gap-1.5 font-medium text-blue-600 hover:text-blue-800">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                        </svg>
                        {{ user?.phone || current.phone }}
                    </a>
                    <a v-if="user?.email || current.email"
                        :href="`mailto:${user?.email || current.email}`"
                        class="flex items-center gap-1.5 font-medium text-blue-600 hover:text-blue-800">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        {{ user?.email || current.email }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Objednávka -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Objednávka</div>

            <div class="flex gap-4">
            <!-- Ľavá strana: číslo, kupón, dátumy -->
            <div class="flex-1 min-w-0">
            <div class="mb-3 flex items-center gap-2">
                <span class="text-xl font-bold text-gray-900">{{ order.serial_number }}</span>
                <span v-if="order.wants_coupon"
                    :class="['inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold text-white',
                        order.issued_coupon ? 'bg-green-600' : 'bg-red-600']"
                    :title="order.issued_coupon ? 'Zľavový kupón bol vydaný' : 'Zákazník požaduje zľavový kupón'">K</span>
            </div>

            <!-- Stav kupónu „Získaj kupón“ -->
            <div v-if="order.wants_coupon" class="mb-3 text-xs">
                <template v-if="order.issued_coupon">
                    <span class="text-gray-500">Kupón vydaný {{ order.issued_coupon.created_at }}:</span>
                    <span class="ml-1 font-mono font-semibold text-green-700">{{ order.issued_coupon.code }}</span>
                    <span class="ml-1 text-gray-500">
                        · platí {{ order.issued_coupon.valid_from }} – {{ order.issued_coupon.valid_to }}
                        · {{ order.issued_coupon.used_count > 0 ? 'použitý' : 'nepoužitý' }}
                    </span>
                </template>
                <span v-else class="text-red-600">Kupón čaká na vydanie – odošle sa e-mailom po úplnej expedícii.</span>
            </div>

            <div class="space-y-1 text-sm text-gray-600">
                <div class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                    <span>{{ order.created_at }} <span class="text-gray-400">prijatá</span></span>
                </div>
                <!-- Dodací list v príprave (dispatched_at = null) ešte nič neexpedoval. -->
                <div v-for="shipping in (order.shippings ?? []).filter(s => s.dispatched_at)" :key="shipping.id" class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-green-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                    <span>{{ shipping.dispatched_at }} <span class="text-gray-400">expedovaná</span></span>
                </div>
            </div>
            </div>

            <!-- Pravá strana: len to, čím sa objednávka líši od zákazníka -->
            <div class="shrink-0 max-w-[55%] border-l border-gray-100 pl-4 text-sm leading-snug">
                <div v-if="!differences.length" class="flex items-center gap-1.5 text-xs font-semibold text-green-700">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    Údaje zhodné so zákazníkom
                </div>
                <template v-else>
                    <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-600">V objednávke inak</div>
                    <div v-for="diff in differences" :key="diff.key" class="text-xs text-gray-500">
                        {{ diff.label }}:
                        <strong v-if="diff.value" class="text-amber-800">{{ diff.value }}</strong>
                        <span v-else class="italic text-gray-400">nevyplnené</span>
                    </div>
                </template>
            </div>
            </div>

            <div v-if="order.note" class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <span class="font-semibold">Poznámka: </span>{{ order.note }}
            </div>
        </div>

        <!-- Adresa doručenia — vyzdvihnutá len keď sa líši od sídla, inak by
             karta zopakovala tú istú adresu druhýkrát. -->
        <div v-if="order.delivery?.is_custom"
             class="rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4 shadow-sm sm:col-span-2">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Doručiť na inú adresu</span>
                <span v-if="order.delivery_changed_at" class="text-xs text-indigo-400">
                    zmenené {{ order.delivery_changed_at }}{{ order.delivery_changed_by ? ` — ${order.delivery_changed_by}` : '' }}
                </span>
            </div>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="text-sm text-gray-800">
                    <div v-if="order.delivery.company" class="font-bold">{{ order.delivery.company }}</div>
                    <div v-if="order.delivery.name">{{ order.delivery.name }}</div>
                    <div v-if="order.delivery.street">{{ order.delivery.street }}</div>
                    <div>{{ [order.delivery.postcode, order.delivery.city].filter(Boolean).join(' ') }}</div>
                </div>
                <div class="text-sm text-gray-600">
                    <a v-if="order.delivery.phone" :href="`tel:${order.delivery.phone}`"
                       class="font-medium text-blue-600 hover:text-blue-800">{{ order.delivery.phone }}</a>
                    <div v-if="order.delivery.note" class="mt-1 text-xs text-gray-500">{{ order.delivery.note }}</div>
                </div>
                <button
                    v-if="order.delivery.phone || order.delivery.street"
                    type="button"
                    @click="copyValue('delivery', [order.delivery.company, order.delivery.name, order.delivery.street, [order.delivery.postcode, order.delivery.city].filter(Boolean).join(' ')].filter(Boolean).join('\n'))"
                    class="rounded border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                >
                    {{ copied === 'delivery' ? 'Skopírované' : 'Kopírovať adresu' }}
                </button>
            </div>
        </div>

    </div>
</template>
