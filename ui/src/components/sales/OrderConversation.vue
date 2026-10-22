<script setup>
// Konverzácia k objednávke — spoločná pre obsluhu aj pre zákazníka so súkromným
// odkazom. Udalosti grafiky (odoslaný návrh, pripomienky, schválenie) sú v tej
// istej časovej osi ako bežné správy.
import { ref } from 'vue';
import axios from '../../axiosInstance';
import { salesError, tokenHeaders, downloadSales } from '../../models/sales';
import { formatFileSize } from '../../models/functions';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    staff: { type: Boolean, default: false },
    // Kam sa posiela nová správa a odkiaľ sa sťahujú prílohy.
    endpoint: { type: String, required: true },
    uuid: { type: String, required: true },
    token: { type: String, default: '' },
});
const emit = defineEmits(['updated']);

const MAX_FILES = 5;
const name = ref(''), body = ref(''), files = ref([]), input = ref(null), busy = ref(false), error = ref('');

const events = {
    artwork_sent: (m) => `Odoslaný grafický návrh – verzia ${m.version}`,
    changes_requested: (m) => `Pripomienky k verzii ${m.version}`,
    approved: (m) => `Schválená verzia ${m.version}`,
};
const label = (m) => events[m.kind]?.(m);
const tones = {
    approved: { box: '!bg-green-50 !border-green-300', text: 'text-green-800' },
    changes_requested: { box: '!bg-orange-50 !border-orange-200', text: 'text-orange-800' },
};
const field = 'w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const mine = (m) => (m.direction === 'staff') === props.staff;

function pick(event) {
    files.value = [...event.target.files].slice(0, MAX_FILES);
}

async function send() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const data = new FormData();
        data.append('body', body.value);
        if (!props.staff) data.append('name', name.value);
        files.value.forEach((file) => data.append('files[]', file));
        emit('updated', (await axios.post(props.endpoint, data, { headers: tokenHeaders(props.token) })).data);
        body.value = '';
        files.value = [];
        if (input.value) input.value.value = '';
    } catch (e) {
        error.value = salesError(e);
    } finally {
        busy.value = false;
    }
}

async function download(message, file) {
    error.value = '';
    try {
        await downloadSales(`/public-artworks/${props.uuid}/messages/${message.id}/files/${file.id}`, file.name, props.token);
    } catch (e) {
        error.value = salesError(e);
    }
}
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
            <h2 class="text-base font-semibold text-gray-800">{{ staff ? 'Správy so zákazníkom' : 'Správy k objednávke' }}</h2>
        </div>
        <div class="space-y-4 px-5 py-4">
            <p v-if="!messages.length" class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
                {{ staff ? 'Zatiaľ žiadne správy. Správa odíde zákazníkovi e-mailom s odkazom na odpoveď.' : 'Zatiaľ žiadne správy. Napíšte nám čokoľvek k objednávke.' }}
            </p>

            <ol v-else class="max-h-[60vh] space-y-3 overflow-y-auto pr-1">
                <li v-for="m in messages" :key="m.id" class="flex" :class="mine(m) ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[85%] rounded-2xl border px-4 py-2 text-sm shadow-sm"
                        :class="[mine(m) ? 'rounded-br-sm bg-blue-50 border-blue-200' : 'rounded-bl-sm bg-gray-50 border-gray-200', tones[m.kind]?.box]">
                        <p class="text-xs text-gray-500">
                            <strong class="text-gray-700">{{ m.author }}</strong> · {{ new Date(m.created_at).toLocaleString('sk-SK') }}
                        </p>
                        <p v-if="label(m)" class="font-semibold" :class="tones[m.kind]?.text ?? 'text-gray-800'">{{ label(m) }}</p>
                        <p v-if="m.body" class="whitespace-pre-wrap break-words text-gray-900">{{ m.body }}</p>
                        <ul v-if="m.attachments.length" class="mt-1 space-y-0.5">
                            <li v-for="f in m.attachments" :key="f.id">
                                <button type="button" class="break-all text-left font-semibold text-blue-700 hover:underline" @click="download(m, f)">📎 {{ f.name }}</button>
                                <span class="text-xs text-gray-500"> ({{ formatFileSize(f.size) }})</span>
                            </li>
                        </ul>
                    </div>
                </li>
            </ol>

            <form class="space-y-3 border-t border-gray-100 pt-4" @submit.prevent="send">
                <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">{{ error }}</p>
                <label v-if="!staff" class="block text-sm font-semibold text-gray-700">Vaše meno
                    <input v-model="name" required maxlength="150" class="mt-1" :class="field" />
                </label>
                <label class="block text-sm font-semibold text-gray-700">{{ staff ? 'Správa zákazníkovi' : 'Vaša správa' }}
                    <textarea v-model="body" required maxlength="5000" rows="4" class="mt-1" :class="field" />
                </label>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <label class="text-xs text-gray-600">Prílohy (najviac {{ MAX_FILES }}, každá do 10 MB)
                        <input ref="input" type="file" multiple class="mt-1 block text-sm file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:font-semibold file:text-gray-700 hover:file:bg-gray-200" @change="pick" />
                    </label>
                    <button :disabled="busy || !body.trim() || (!staff && !name.trim())" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:bg-gray-400">
                        {{ busy ? 'Odosielam…' : staff ? 'Odoslať zákazníkovi' : 'Odoslať správu' }}
                    </button>
                </div>
                <p v-if="staff" class="text-xs text-gray-500">Zákazník dostane e-mail vo vlákne objednávky a odpovie cez odkaz – odpoveď sa zobrazí tu.</p>
            </form>
        </div>
    </section>
</template>
