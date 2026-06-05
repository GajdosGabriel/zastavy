<script setup>
import usePaginator from '../../store/StorePaginator';
import { onBeforeUnmount } from 'vue';


const { state, getPaginator, resetPaginator, getLinks } = usePaginator();

const emit = defineEmits(['setUrl'])


const fetchPaginate = (url) => {
    emit('setUrl', url);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onBeforeUnmount(() => {
    resetPaginator();
});

</script>

<template>
    <div class="flex justify-center my-10 space-x-3" v-if="getPaginator">
        <button @click="fetchPaginate(getLinks.first)"
            class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm cursor-pointer"
            :disabled="!getLinks.first">
            <!--  Icon -->
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
        <div
            class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm">
            {{ getPaginator.current_page }} / {{ getPaginator.last_page }}
        </div>
        <button @click="fetchPaginate(getLinks.next)"
            class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm cursor-pointer"
            :disabled="!getLinks.next">
            <!--  Icon -->
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </button>
    </div>

</template>
