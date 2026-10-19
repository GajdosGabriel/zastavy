<script setup>

const props = defineProps(["permission"]);

// ID je posledný segment (…/api/products/1); pevný index by zlyhal pri API pod /web/api.
const productId = props.permission ? props.permission.split('/').filter(Boolean).pop() : null;


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