<script setup>
import { computed, onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import { storeToRefs } from "pinia";
import { useCustomerExport } from "../../store/StoreCustomerExport";

const store = useCustomerExport();
const { getAttributes, getSelected, getOnlyWithEmail, isExporting } = storeToRefs(store);
const {
    fetchAttributes, toggleAttribute, selectAllAttributes, clearAttributes, setOnlyWithEmail, exportCustomers,
} = store;

// Poradie zaškrtnutia = poradie stĺpcov, tak ho ukážeme priamo pri políčku.
const orderOf = computed(() => Object.fromEntries(
    getSelected.value.map((value, index) => [value, index + 1])
));

onMounted(() => {
    fetchAttributes();
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Export zákazníkov' }" />

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm text-slate-600">
                        Vyberte stĺpce, ktoré má obsahovať CSV súbor. Číslo pri políčku ukazuje,
                        v akom poradí sa stĺpec v súbore objaví — určuje ho poradie zaškrtnutia.
                    </p>

                    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs font-semibold">
                        <button type="button" class="text-blue-600 hover:underline" @click="selectAllAttributes">
                            Vybrať všetko
                        </button>
                        <button type="button" class="text-slate-500 hover:underline" @click="clearAttributes">
                            Zrušiť výber
                        </button>
                        <label class="flex items-center gap-2 font-normal text-slate-600">
                            <input type="checkbox" class="h-4 w-4 accent-blue-600"
                                :checked="getOnlyWithEmail"
                                @change="setOnlyWithEmail($event.target.checked)" />
                            Len zákazníci s vyplneným e-mailom
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <label v-for="attribute in getAttributes" :key="attribute.value"
                            class="flex items-center gap-2 rounded border px-3 py-2 text-sm hover:bg-slate-50"
                            :class="getSelected.includes(attribute.value)
                                ? 'border-blue-300 bg-blue-50/60 text-slate-900'
                                : 'border-slate-200 text-slate-700'">
                            <input type="checkbox" class="h-4 w-4 accent-blue-600"
                                :checked="getSelected.includes(attribute.value)"
                                @change="toggleAttribute(attribute.value)" />
                            <span class="flex-1">{{ attribute.label }}</span>
                            <span v-if="orderOf[attribute.value]"
                                class="rounded-full bg-blue-100 px-1.5 text-xs font-semibold text-blue-700">
                                {{ orderOf[attribute.value] }}
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <button type="button" :disabled="!getSelected.length || isExporting"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            @click="exportCustomers">
                            {{ isExporting ? "Exportujem..." : "Exportovať do CSV" }}
                        </button>
                        <span v-if="!getSelected.length" class="text-xs text-slate-400">
                            Vyberte aspoň jeden stĺpec
                        </span>
                    </div>

                    <p class="mt-4 text-xs text-slate-400">
                        Súbor je CSV s bodkočiarkou ako oddeľovačom a kódovaním UTF-8 s BOM —
                        otvorí sa priamo v Exceli aj v Google Sheets bez rozbitej diakritiky.
                    </p>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
