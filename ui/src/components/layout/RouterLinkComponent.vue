<script setup>

const props = defineProps(["permission"]);

const parts = props.permission.split('/');
const productId = parts[5]; // "1"


</script>

<template>
    <div v-if="permission">
        <router-link :to="{
            name: 'products.show',
            params: {
                productId: productId
            },
        }">
            <slot></slot>
        </router-link>
    </div>
    <slot v-else></slot>

</template>