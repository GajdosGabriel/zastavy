<script setup>
import SpinnerButton from '../../icons/spinnerButton.vue';
import loadingStore from '../../../store/StoreLoading';

const props = defineProps(['item']);

</script>

<template>
    <div>
        <button
            class=" bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center"
            type="submit" v-if="(typeof props.item !== 'undefined' && Object.keys(props.item).length)">
            <SpinnerButton style="margin-right: 15px" v-if="props.item.spinner && loadingStore.isLoading" />
            {{ props.item.name }}
        </button>
    </div>
</template>
