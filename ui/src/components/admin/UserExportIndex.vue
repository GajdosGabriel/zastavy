<script setup>
import { onMounted } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import useUserExport from "../../store/StoreUserExport";

const {
    state, getAttributes, getSelected, isExporting,
    fetchAttributes, toggleAttribute, selectAllAttributes, clearAttributes, exportUsers,
} = useUserExport();

onMounted(() => {
    fetchAttributes();
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Export používateľov' }" />

                <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="mb-4 text-sm text-slate-600">
                        Vyber atribúty, ktoré chceš zahrnúť do CSV exportu používateľov. Export rešpektuje rovnaké
                        oprávnenia ako zoznam používateľov (bežný admin vidí len svojich zákazníkov, super admin všetkých).
                    </p>

                    <div class="mb-4 flex gap-3 text-xs font-semibold">
                        <button type="button" class="text-blue-600 hover:underline" @click="selectAllAttributes">
                            Vybrať všetko
                        </button>
                        <button type="button" class="text-slate-500 hover:underline" @click="clearAttributes">
                            Zrušiť výber
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <label
                            v-for="attribute in getAttributes"
                            :key="attribute.value"
                            class="flex items-center gap-2 rounded border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 accent-blue-600"
                                :checked="getSelected.includes(attribute.value)"
                                @change="toggleAttribute(attribute.value)"
                            />
                            {{ attribute.label }}
                        </label>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <button
                            type="button"
                            :disabled="!getSelected.length || isExporting"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            @click="exportUsers"
                        >
                            {{ isExporting ? "Exportujem..." : "Exportovať do CSV" }}
                        </button>
                        <span v-if="!getSelected.length" class="text-xs text-slate-400">
                            Vyber aspoň jeden atribút
                        </span>
                    </div>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
