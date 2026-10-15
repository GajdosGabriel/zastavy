<script setup>
import { computed } from 'vue';
import plus from '../../icons/plus.vue';
import arrow from '../../icons/arrowLeft.vue';

const props = defineProps(['item']);

// Návrat (šípka späť) je sekundárna akcia, všetko ostatné (Nový…, Upraviť) je primárna.
const isSecondary = computed(() => props.item?.variant === 'secondary'
    || (props.item?.variant !== 'primary' && props.item?.icon === 'arrow-left'));
</script>

<template>
    <div>
        <router-link
            :to="typeof props.item.link === 'string' && !props.item.link.startsWith('/') ? { name: props.item.link } : props.item.link"
            v-if="props.item && Object.keys(props.item).length"
            class="flex items-center rounded px-4 py-2 font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
            :class="isSecondary
                ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                : 'bg-blue-700 text-white hover:bg-blue-800'">
            <plus v-if="props.item.icon == 'plus'" class="mr-1" />
            <arrow v-if="props.item.icon == 'arrow-left'" class="mr-1" />
            {{ props.item.name }}
        </router-link>
    </div>
</template>
