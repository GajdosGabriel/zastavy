<script setup>
import router from '../../router';
import Smile from '../icons/smile.vue';
import BaseLayout from './BaseLayout.vue';
</script>
<template>
    <BaseLayout>

        <template #main>
            <!-- <h1 class="page-heading">{{ getProduct.name }}</h1> -->

            <div class="page-body col-span-12 text-center min-h-screen">
                <div class="font-semibold text-2xl text-gray-100 flex justify-center mb-6 p-4 bg-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <div>Ďakujeme za objednávku</div>
                </div>

                <Smile class="mx-auto h-32"/>
                <div class="font-semibold text-2xl mb-6">Objednávka bola úspešne odoslaná.</div>
                <div class="font-semibold">V emailovej schránke nájdete rekapituláciu objednávky.</div>
                <router-link :to="{ name: 'public.index' }">
                    <div class="btn btn-primary mt-10">Prejsť na začiatok</div>
                </router-link>
            </div>

        </template>
    </BaseLayout>

</template>