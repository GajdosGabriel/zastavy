<script setup>
import { onMounted, reactive, ref } from 'vue';
import { storeToRefs } from 'pinia';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import { useAttributes, emptyAttribute } from '../../store/StoreAttributes';

const store = useAttributes();
const { getAttributes } = storeToRefs(store);
const {
    fetchAttributes, storeAttribute, updateAttribute, destroyAttribute,
    storeValue, updateValue, destroyValue,
} = store;

const draft = ref(emptyAttribute());
const isCreating = ref(false);
const expanded = reactive({});
// Rozpísaná nová hodnota, samostatne pre každú vlastnosť.
const newValue = reactive({});

onMounted(() => fetchAttributes());

const toggle = (id) => {
    expanded[id] = !expanded[id];
    if (newValue[id] === undefined) {
        newValue[id] = '';
    }
};

const onCreate = async () => {
    const created = await storeAttribute(draft.value);
    if (created) {
        draft.value = emptyAttribute();
        isCreating.value = false;
    }
};

const onAddValue = async (attribute) => {
    const value = (newValue[attribute.id] ?? '').trim();
    if (!value) return;
    await storeValue(attribute.id, { value });
    newValue[attribute.id] = '';
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Vlastnosti produktov' }" />

                <p class="mb-6 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Vlastnosti sú zdieľané celým katalógom. Z tých označených ako
                    <strong>variantné</strong> sa skladajú skladové položky (vlastná cena, sklad, EAN),
                    <strong>filtrovateľné</strong> sa zobrazujú ako filtre v katalógu.
                </p>

                <div class="mb-4 flex justify-end">
                    <button type="button" @click="isCreating = !isCreating"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        {{ isCreating ? 'Zrušiť' : 'Nová vlastnosť' }}
                    </button>
                </div>

                <!-- Nová vlastnosť -->
                <form v-if="isCreating" @submit.prevent="onCreate"
                    class="mb-6 rounded-md border-2 border-dashed border-blue-300 bg-blue-50/40 p-5">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Názov</label>
                            <input v-model="draft.name" type="text" required placeholder="napr. Materiál"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Jednotka</label>
                            <input v-model="draft.unit" type="text" placeholder="napr. cm"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Poradie</label>
                            <input v-model.number="draft.sort_order" type="number" min="0"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="draft.is_variant" class="accent-blue-600" />
                            Rozlišuje variant
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="draft.is_filterable" class="accent-blue-600" />
                            Filtrovateľná
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="draft.is_public" class="accent-blue-600" />
                            Verejná
                        </label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Vytvoriť
                        </button>
                    </div>
                </form>

                <!-- Zoznam -->
                <div class="space-y-3">
                    <article v-for="attribute in getAttributes" :key="attribute.id"
                        class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <button type="button" @click="toggle(attribute.id)" class="flex items-center gap-3 text-left">
                                <span class="text-gray-400">{{ expanded[attribute.id] ? '▾' : '▸' }}</span>
                                <span>
                                    <span class="font-semibold text-gray-900">{{ attribute.name }}</span>
                                    <span class="ml-2 font-mono text-xs text-gray-400">{{ attribute.code }}</span>
                                </span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ attribute.values?.length ?? 0 }} hodnôt
                                </span>
                            </button>

                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <label class="flex items-center gap-1.5 text-gray-600">
                                    <input type="checkbox" v-model="attribute.is_variant"
                                        @change="updateAttribute(attribute)" class="accent-blue-600" />
                                    variantná
                                </label>
                                <label class="flex items-center gap-1.5 text-gray-600">
                                    <input type="checkbox" v-model="attribute.is_filterable"
                                        @change="updateAttribute(attribute)" class="accent-blue-600" />
                                    filter
                                </label>
                                <label class="flex items-center gap-1.5 text-gray-600">
                                    <input type="checkbox" v-model="attribute.is_public"
                                        @change="updateAttribute(attribute)" class="accent-blue-600" />
                                    verejná
                                </label>
                                <button type="button" @click="destroyAttribute(attribute)"
                                    :disabled="!attribute.permissions?.delete?.allowed"
                                    :title="attribute.permissions?.delete?.allowed ? 'Zmazať' : 'Vlastnosť používa aspoň jeden variant'"
                                    class="rounded-md border border-gray-300 px-2.5 py-1 font-semibold text-gray-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40">
                                    Zmazať
                                </button>
                            </div>
                        </div>

                        <div v-if="expanded[attribute.id]" class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                            <div class="space-y-2">
                                <div v-for="value in attribute.values" :key="value.id"
                                    class="flex flex-wrap items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2">
                                    <input v-model="value.value" type="text"
                                        @change="updateValue(attribute.id, value)"
                                        class="min-w-40 flex-1 rounded border border-gray-200 px-2 py-1 text-sm" />
                                    <span class="font-mono text-xs text-gray-400">{{ value.code }}</span>
                                    <input v-model.number="value.sort_order" type="number" min="0"
                                        @change="updateValue(attribute.id, value)" title="Poradie"
                                        class="w-16 rounded border border-gray-200 px-2 py-1 text-sm" />
                                    <button type="button" @click="destroyValue(attribute.id, value)"
                                        class="rounded px-2 py-1 text-xs text-gray-400 transition hover:bg-red-50 hover:text-red-600">
                                        ×
                                    </button>
                                </div>

                                <p v-if="!attribute.values?.length" class="text-sm text-gray-500">
                                    Vlastnosť nemá žiadne hodnoty.
                                </p>
                            </div>

                            <form class="mt-3 flex gap-2" @submit.prevent="onAddValue(attribute)">
                                <input v-model="newValue[attribute.id]" type="text"
                                    :placeholder="`Nová hodnota (${attribute.name})`"
                                    class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm" />
                                <button type="submit"
                                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                                    Pridať
                                </button>
                            </form>
                        </div>
                    </article>

                    <p v-if="!getAttributes.length" class="rounded-md border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500">
                        Zatiaľ nie je definovaná žiadna vlastnosť.
                    </p>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
