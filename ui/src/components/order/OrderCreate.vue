<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import ButtonLink from '../layout/page/ButtonLink.vue';

const buttonBack = { name: 'Späť', spinner: true, link: '/objednavky', icon: 'arrow-left' }
</script>

<template>

    <BaseLayout>

        <template #main>

            <h1 class="page-heading">Upraviť objednávku
                <buttonLink :item="buttonBack" class="text-sm" />
            </h1>


            <h1 class="page-heading">Nová objednávka</h1>

            <div class="page-body col-span-12">
                tu
            </div>
        </template>
    </BaseLayout>
</template>