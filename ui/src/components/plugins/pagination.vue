<script setup>
import usePaginator from '../../store/StorePaginator';
import { computed, onBeforeUnmount } from 'vue';

const { getPaginator, resetPaginator, getLinks } = usePaginator();

const emit = defineEmits(['setUrl']);

const fetchPaginate = (url) => {
    if (!url) return;
    emit('setUrl', url);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const getPageUrl = (page) => {
    const base = getLinks.value?.first;
    if (!base) return null;
    try {
        const url = new URL(base);
        url.searchParams.set('page', page);
        return url.toString();
    } catch {
        return base + (base.includes('?') ? '&' : '?') + 'page=' + page;
    }
};

const visiblePages = computed(() => {
    const current = getPaginator.value?.current_page ?? 1;
    const last    = getPaginator.value?.last_page    ?? 1;
    if (last <= 1) return [];

    const delta  = 2;
    const range  = [];
    const result = [];

    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
        range.push(i);
    }

    let prev = null;
    const all = [1, ...range, last];

    for (const page of all) {
        if (prev !== null && page - prev > 1) {
            result.push({ type: 'dots', key: `dots-${prev}` });
        }
        result.push({ type: 'page', page, key: `p-${page}` });
        prev = page;
    }

    return result;
});

onBeforeUnmount(() => resetPaginator());
</script>

<template>
    <div v-if="getPaginator?.last_page > 1" class="mt-6 flex flex-col items-center gap-3">

        <!-- Počítadlo -->
        <p class="text-xs text-gray-500">
            <span v-if="getPaginator.from && getPaginator.to">
                {{ getPaginator.from }}–{{ getPaginator.to }} z
            </span>
            {{ getPaginator.total }} záznamov
        </p>

        <!-- Tlačidlá -->
        <div class="flex items-center gap-1">

            <!-- Prvá strana -->
            <button @click="fetchPaginate(getLinks.first)"
                :disabled="getPaginator.current_page === 1"
                class="flex h-8 w-8 items-center justify-center rounded border text-gray-600 transition"
                :class="getPaginator.current_page === 1
                    ? 'border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed'
                    : 'border-gray-300 bg-white hover:bg-gray-100'"
                title="Prvá strana">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Predchádzajúca -->
            <button @click="fetchPaginate(getLinks.prev)"
                :disabled="!getLinks.prev"
                class="flex h-8 w-8 items-center justify-center rounded border text-gray-600 transition"
                :class="!getLinks.prev
                    ? 'border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed'
                    : 'border-gray-300 bg-white hover:bg-gray-100'"
                title="Predchádzajúca">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <!-- Čísla strán -->
            <template v-for="item in visiblePages" :key="item.key">
                <span v-if="item.type === 'dots'"
                    class="flex h-8 w-8 items-center justify-center text-sm text-gray-400">
                    …
                </span>
                <button v-else
                    @click="fetchPaginate(getPageUrl(item.page))"
                    class="flex h-8 min-w-8 items-center justify-center rounded border px-2 text-sm font-semibold transition"
                    :class="item.page === getPaginator.current_page
                        ? 'border-blue-600 bg-blue-600 text-white cursor-default'
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'">
                    {{ item.page }}
                </button>
            </template>

            <!-- Ďalšia -->
            <button @click="fetchPaginate(getLinks.next)"
                :disabled="!getLinks.next"
                class="flex h-8 w-8 items-center justify-center rounded border text-gray-600 transition"
                :class="!getLinks.next
                    ? 'border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed'
                    : 'border-gray-300 bg-white hover:bg-gray-100'"
                title="Ďalšia">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <!-- Posledná strana -->
            <button @click="fetchPaginate(getLinks.last)"
                :disabled="getPaginator.current_page === getPaginator.last_page"
                class="flex h-8 w-8 items-center justify-center rounded border text-gray-600 transition"
                :class="getPaginator.current_page === getPaginator.last_page
                    ? 'border-gray-200 bg-gray-50 text-gray-300 cursor-not-allowed'
                    : 'border-gray-300 bg-white hover:bg-gray-100'"
                title="Posledná strana">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>
</template>
