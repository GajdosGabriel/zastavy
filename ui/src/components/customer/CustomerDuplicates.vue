<script setup lang="ts">
import { onMounted, ref } from "vue";
import axiosInstance from "../../axiosInstance";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";

/**
 * Zákazníci, ktorí sú v tabuľke viackrát.
 *
 * Zlučuje sa vždy jedna skupina jedným kliknutím a vždy do najstaršieho
 * záznamu — na ňom visí história objednávok a jeho ID je vo vystavených
 * dokladoch. Ktorý záznam zostane, si dá prepnúť; čo sa doňho zlúči, sa
 * odklikáva.
 *
 * Hromadné „zluč všetko" tu zámerne nie je. Presun objednávok medzi
 * zákazníkmi je zásah, ktorý sa nemá dať spustiť jedným kliknutím na
 * stovky skupín naraz — na to je príkaz `app:customer-duplicates --merge`,
 * kde je vidieť, čo sa deje.
 */
interface DuplicateCustomer {
    id: number;
    company: string | null;
    name: string | null;
    street: string | null;
    city: string | null;
    postcode: string | null;
    ico: string | null;
    dic: string | null;
    email: string | null;
    orders: number;
}

interface DuplicateGroup {
    key: string;
    reason: string;
    customers: DuplicateCustomer[];
}

const groups = ref<DuplicateGroup[]>([]);
const loading = ref(false);
const busy = ref<string | null>(null);
const message = ref("");

/** Ktorý záznam v skupine zostáva. Predvolene najstarší, teda prvý. */
const keepId = ref<Record<string, number>>({});

const load = async () => {
    loading.value = true;

    try {
        const response = await axiosInstance.get("/customers/duplicates?limit=50");
        groups.value = response.data.data || [];
        keepId.value = Object.fromEntries(
            groups.value.map((group) => [group.key, group.customers[0]?.id])
        );
    } catch (e: any) {
        message.value = e.response?.data?.message || "Zoznam sa nepodarilo načítať.";
    } finally {
        loading.value = false;
    }
};

const onMerge = async (group: DuplicateGroup) => {
    const keep = keepId.value[group.key];
    const merge = group.customers.filter((c) => c.id !== keep).map((c) => c.id);

    if (!merge.length) return;

    if (!window.confirm(
        `Zlúčiť ${merge.length} záznamov do #${keep}? Objednávky a kontakty sa presunú, ostatné záznamy sa archivujú.`
    )) {
        return;
    }

    busy.value = group.key;
    message.value = "";

    try {
        const response = await axiosInstance.post(`/customers/${keep}/merge`, { merge });
        message.value = response.data.message;
        groups.value = groups.value.filter((g) => g.key !== group.key);
    } catch (e: any) {
        message.value = e.response?.data?.message || "Zlúčenie zlyhalo.";
    } finally {
        busy.value = null;
    }
};

onMounted(load);
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Duplicitní zákazníci', buttonLink: { name: 'Späť', spinner: true, link: '/zakaznici', icon: 'arrow-left' } }" />

                <p class="mt-4 text-sm text-gray-600">
                    Záznamy s rovnakým IČO (alebo s rovnakým názvom a mestom, keď IČO chýba).
                    Zlučuje sa do označeného záznamu — objednávky a kontaktné osoby sa naň presunú,
                    chýbajúce údaje sa doplnia z kópií a tie sa archivujú.
                </p>

                <p v-if="message" class="mt-3 rounded-lg bg-blue-50 px-4 py-2 text-sm text-blue-800">{{ message }}</p>

                <p v-if="loading" class="mt-6 text-sm text-gray-500">Načítavam…</p>
                <p v-else-if="!groups.length" class="mt-6 text-sm text-gray-500">Žiadne duplicity.</p>

                <div
                    v-for="group in groups"
                    :key="group.key"
                    class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-800">{{ group.reason }}</h2>
                        <button
                            type="button"
                            :disabled="busy === group.key"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                            @click="onMerge(group)"
                        >
                            {{ busy === group.key ? "Zlučujem…" : `Zlúčiť ${group.customers.length - 1} do označeného` }}
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-2">Ostáva</th>
                                    <th class="px-5 py-2">ID</th>
                                    <th class="px-5 py-2">Firma / kontakt</th>
                                    <th class="px-5 py-2">Adresa</th>
                                    <th class="px-5 py-2">IČO / DIČ</th>
                                    <th class="px-5 py-2">Obj.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="customer in group.customers"
                                    :key="customer.id"
                                    class="border-t border-gray-100"
                                    :class="keepId[group.key] === customer.id ? 'bg-green-50' : ''"
                                >
                                    <td class="px-5 py-2">
                                        <input
                                            type="radio"
                                            class="h-4 w-4"
                                            :value="customer.id"
                                            :checked="keepId[group.key] === customer.id"
                                            @change="keepId[group.key] = customer.id"
                                        />
                                    </td>
                                    <td class="px-5 py-2">
                                        <router-link
                                            :to="{ name: 'customers.edit', params: { customerId: customer.id } }"
                                            class="text-blue-600 hover:underline"
                                        >
                                            #{{ customer.id }}
                                        </router-link>
                                    </td>
                                    <td class="px-5 py-2">
                                        <div class="font-medium text-gray-900">{{ customer.company }}</div>
                                        <div class="text-xs text-gray-500">{{ customer.name }} · {{ customer.email }}</div>
                                    </td>
                                    <td class="px-5 py-2 text-gray-600">
                                        {{ customer.street }}<br />
                                        <span class="text-xs">{{ customer.postcode }} {{ customer.city }}</span>
                                    </td>
                                    <td class="px-5 py-2 text-gray-600">
                                        {{ customer.ico }}<br />
                                        <span class="text-xs">{{ customer.dic }}</span>
                                    </td>
                                    <td class="px-5 py-2 font-semibold text-gray-700">{{ customer.orders }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
