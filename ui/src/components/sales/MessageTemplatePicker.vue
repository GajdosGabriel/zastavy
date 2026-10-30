<script setup>
// Výber predpripravenej správy pre obsluhu; vybraný text vloží do poľa správy.
import { ref, onMounted } from 'vue';
import axios from '../../axiosInstance';

const text = defineModel({ type: String, default: '' });
const templates = ref([]);
// Text, ktorý obsluha sama nenapísala, sa nahradí bez pýtania.
let untouched = text.value;

onMounted(async () => {
    try {
        templates.value = (await axios.get('/message-templates')).data.data;
    } catch {
        // Bez šablón sa dá správa stále napísať ručne.
    }
});

function insert(event) {
    const template = templates.value.find((t) => String(t.id) === event.target.value);
    // Výber sa vráti na začiatok, aby sa dala tá istá šablóna vložiť znova.
    event.target.value = '';
    if (!template) return;
    if (text.value.trim() && text.value !== untouched && !window.confirm('Nahradiť rozpísaný text šablónou?')) return;
    text.value = untouched = template.body;
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
        <select aria-label="Vložiť šablónu" class="rounded-lg border border-gray-300 px-2 py-1 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" @change="insert">
            <option value="">Vložiť šablónu…</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <router-link to="/predaj/sablony" target="_blank" class="text-xs text-blue-700 hover:underline">Spravovať šablóny</router-link>
    </div>
</template>
