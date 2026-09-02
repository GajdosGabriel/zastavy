<script setup>
import ConfirmDialog from './components/plugins/ConfirmDialog.vue';
</script>

<template>
    <div>
        <router-view></router-view>
        <ConfirmDialog />
    </div>
</template>
