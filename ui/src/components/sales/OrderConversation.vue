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
    <section class="border rounded p-4 space-y-4">
        <h2 class="font-semibold">Správy k objednávke</h2>
        <p v-if="!messages.length" class="text-sm text-gray-600">
            {{ staff ? 'Zatiaľ žiadne správy. Správa odíde zákazníkovi e-mailom s odkazom na odpoveď.' : 'Zatiaľ žiadne správy. Napíšte nám čokoľvek k objednávke.' }}
        </p>

        <ol class="space-y-3">
            <li v-for="m in messages" :key="m.id" class="flex" :class="mine(m) ? 'justify-end' : 'justify-start'">
                <div class="max-w-[85%] rounded-lg border px-3 py-2 text-sm"
                    :class="[mine(m) ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200', m.kind === 'approved' ? '!bg-green-50 !border-green-300' : '']">
                    <p class="text-xs text-gray-500">
                        <strong class="text-gray-700">{{ m.author }}</strong> · {{ new Date(m.created_at).toLocaleString('sk-SK') }}
                    </p>
                    <p v-if="label(m)" class="font-semibold" :class="m.kind === 'approved' ? 'text-green-800' : 'text-gray-800'">{{ label(m) }}</p>
                    <p v-if="m.body" class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                    <ul v-if="m.attachments.length" class="mt-1">
                        <li v-for="f in m.attachments" :key="f.id">
                            <button type="button" class="underline text-blue-700" @click="download(m, f)">{{ f.name }}</button>
                            <span class="text-xs text-gray-500"> ({{ formatFileSize(f.size) }})</span>
                        </li>
                    </ul>
                </div>
            </li>
        </ol>

        <form class="space-y-3 border-t pt-4" @submit.prevent="send">
            <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
            <label v-if="!staff" class="block">Vaše meno
                <input v-model="name" required maxlength="150" class="block border p-2" />
            </label>
            <label class="block">{{ staff ? 'Správa zákazníkovi' : 'Vaša správa' }}
                <textarea v-model="body" required maxlength="5000" rows="4" class="block border w-full p-2" />
            </label>
            <label class="block text-sm">Prílohy (najviac {{ MAX_FILES }}, každá do 10 MB)
                <input ref="input" type="file" multiple class="block" @change="pick" />
            </label>
            <button :disabled="busy || !body.trim() || (!staff && !name.trim())" class="bg-blue-700 text-white rounded p-2 disabled:opacity-40">
                {{ busy ? 'Odosielam…' : staff ? 'Odoslať zákazníkovi' : 'Odoslať správu' }}
            </button>
            <p v-if="staff" class="text-sm text-gray-600">Zákazník dostane e-mail vo vlákne objednávky a odpovie cez odkaz – odpoveď sa zobrazí tu.</p>
        </form>
    </section>
</template>
