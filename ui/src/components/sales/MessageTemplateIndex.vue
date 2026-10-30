<script setup>
import { ref, onMounted } from 'vue';
import axios from '../../axiosInstance';
import BaseLayout from '../layout/BaseLayout.vue';
import { salesError } from '../../models/sales';

const blank = () => ({ id: null, name: '', body: '', sort_order: 99 });
const templates = ref([]), form = ref(blank()), busy = ref(false), error = ref('');
const field = 'w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const label = 'block text-sm font-semibold text-gray-700';

async function run(fn) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        await fn();
    } catch (e) {
        error.value = salesError(e);
    } finally {
        busy.value = false;
    }
}

const load = async () => { templates.value = (await axios.get('/message-templates')).data.data; };
onMounted(() => run(load));

function edit(template) {
    form.value = { ...template };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function save() {
    return run(async () => {
        const { id, ...data } = form.value;
        await (id ? axios.put('/message-templates/' + id, data) : axios.post('/message-templates', data));
        form.value = blank();
        await load();
    });
}

function destroy(template) {
    if (!window.confirm(`Zmazať šablónu „${template.name}“?`)) return;
    return run(async () => {
        await axios.delete('/message-templates/' + template.id);
        if (form.value.id === template.id) form.value = blank();
        await load();
    });
}
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <h1 class="page-heading">Šablóny správ</h1>
                <p class="mb-4 text-sm text-gray-600">Texty, ktoré sa dajú jedným výberom vložiť do správy zákazníkovi pri objednávke, grafickom návrhu aj cenovej ponuke. Pred odoslaním ich možno upraviť.</p>

                <p v-if="error" role="alert" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</p>

                <form class="mb-6 space-y-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="save">
                    <div class="grid gap-3 md:grid-cols-4">
                        <label class="md:col-span-3" :class="label">Názov
                            <input v-model="form.name" required maxlength="150" placeholder="Napr. Grafický návrh obecnej zástavy" class="mt-1 font-normal" :class="field" />
                        </label>
                        <label :class="label">Poradie
                            <input v-model.number="form.sort_order" type="number" min="0" class="mt-1 font-normal" :class="field" />
                        </label>
                    </div>
                    <label :class="label">Text správy
                        <textarea v-model="form.body" required maxlength="5000" rows="10" class="mt-1 font-normal" :class="field" />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button :disabled="busy" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:bg-gray-400">{{ form.id ? 'Uložiť zmeny' : 'Pridať šablónu' }}</button>
                        <button v-if="form.id" type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="form = blank()">Zrušiť úpravu</button>
                    </div>
                </form>

                <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <li v-if="!templates.length" class="px-5 py-8 text-center text-sm text-gray-600">Zatiaľ žiadne šablóny.</li>
                    <li v-for="t in templates" :key="t.id" class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold text-gray-800">{{ t.name }}</h2>
                            <div class="flex gap-3 text-sm">
                                <button type="button" class="text-blue-700 hover:underline" @click="edit(t)">Upraviť</button>
                                <button type="button" :disabled="busy" class="text-red-700 hover:underline" @click="destroy(t)">Zmazať</button>
                            </div>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700">{{ t.body }}</p>
                    </li>
                </ul>
            </div>
        </template>
    </BaseLayout>
</template>
