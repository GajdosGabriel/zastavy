<script setup>
import { onMounted, ref, watch } from 'vue';
import { sanitizeHtml } from '../../models/html';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    // Poznámky sú krátke, popis produktu dlhý — výšku si určí volajúci.
    minHeight: { type: String, default: '10rem' },
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(null);
const isEmpty = ref(true);

// Formátovanie ide cez execCommand — je síce označený za zastaraný, ale ako
// jediný funguje vo všetkých prehliadačoch bez ďalšej závislosti.
const exec = (command, value = null) => {
    editor.value?.focus();
    document.execCommand(command, false, value);
    onInput();
};

const tools = [
    { label: 'B', title: 'Tučné', command: 'bold', class: 'font-bold' },
    { label: 'I', title: 'Kurzíva', command: 'italic', class: 'italic' },
    { label: 'U', title: 'Podčiarknuté', command: 'underline', class: 'underline' },
    { label: 'H2', title: 'Nadpis', command: 'formatBlock', value: '<h2>' },
    { label: 'H3', title: 'Podnadpis', command: 'formatBlock', value: '<h3>' },
    { label: '¶', title: 'Odsek', command: 'formatBlock', value: '<p>' },
    { label: '• zoznam', title: 'Odrážky', command: 'insertUnorderedList' },
    { label: '1. zoznam', title: 'Číslovaný zoznam', command: 'insertOrderedList' },
];

const onLink = () => {
    const url = window.prompt('Adresa odkazu (https://…)');
    if (!url) return;

    // javascript: v odkaze by bol XSS — povolíme len bežné schémy.
    if (!/^(https?:\/\/|mailto:|\/)/i.test(url)) {
        window.alert('Povolené sú len odkazy začínajúce https://, http://, mailto: alebo /.');
        return;
    }

    exec('createLink', url);
};

const onClearFormat = () => {
    exec('removeFormat');
    exec('unlink');
};

const onInput = () => {
    const html = sanitizeHtml(editor.value?.innerHTML ?? '');
    isEmpty.value = html.replace(/<[^>]*>/g, '').trim() === '';
    emit('update:modelValue', isEmpty.value ? '' : html);
};

// Vložený text zbavíme formátovania zdroja (Word, weby) — inak sa do popisu
// dostanú cudzie štýly a triedy.
const onPaste = (event) => {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, text);
    onInput();
};

const render = (html) => {
    if (!editor.value) return;
    const clean = sanitizeHtml(html ?? '');
    if (editor.value.innerHTML !== clean) {
        editor.value.innerHTML = clean;
    }
    isEmpty.value = clean.replace(/<[^>]*>/g, '').trim() === '';
};

onMounted(() => render(props.modelValue));

// Popis sa načíta až po dotiahnutí produktu; pri písaní sa hodnota zhoduje,
// takže sa innerHTML neprepisuje a kurzor neuteká na začiatok.
watch(() => props.modelValue, (value) => {
    if (document.activeElement === editor.value) return;
    render(value);
});
</script>

<template>
    <div class="rounded-md border border-gray-300 shadow-sm focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
        <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
            <button v-for="tool in tools" :key="tool.label" type="button"
                :title="tool.title" @click="exec(tool.command, tool.value ?? null)"
                class="rounded px-2 py-1 text-xs text-gray-700 transition hover:bg-gray-200"
                :class="tool.class">
                {{ tool.label }}
            </button>
            <button type="button" title="Odkaz" @click="onLink"
                class="rounded px-2 py-1 text-xs text-gray-700 transition hover:bg-gray-200">
                🔗 odkaz
            </button>
            <button type="button" title="Zrušiť formátovanie" @click="onClearFormat"
                class="rounded px-2 py-1 text-xs text-gray-500 transition hover:bg-gray-200">
                vyčistiť
            </button>
        </div>

        <div class="relative">
            <p v-if="isEmpty && placeholder"
                class="pointer-events-none absolute left-3 top-2 text-sm text-gray-400">
                {{ placeholder }}
            </p>
            <div ref="editor" contenteditable="true" @input="onInput" @blur="onInput" @paste="onPaste"
                :style="{ minHeight: props.minHeight }"
                class="html-editor w-full px-3 py-2 text-sm leading-6 focus:outline-none" />
        </div>
    </div>
</template>

<style scoped>
/* Tailwind resetuje nadpisy a zoznamy — v editore ich potrebujeme vidieť. */
.html-editor :deep(h2) { font-size: 1.125rem; font-weight: 600; margin: 0.75rem 0 0.35rem; }
.html-editor :deep(h3) { font-size: 1rem; font-weight: 600; margin: 0.6rem 0 0.3rem; }
.html-editor :deep(p) { margin: 0.35rem 0; }
.html-editor :deep(ul) { list-style: disc; padding-left: 1.4rem; margin: 0.35rem 0; }
.html-editor :deep(ol) { list-style: decimal; padding-left: 1.4rem; margin: 0.35rem 0; }
.html-editor :deep(a) { color: #2563eb; text-decoration: underline; }
</style>
