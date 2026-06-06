<template>
    <div>
        <GlobalErrorPanel />

        <router-view></router-view>
    </div>
</template>

<script setup>
import GlobalErrorPanel from './components/plugins/GlobalErrorPanel.vue';
</script>
