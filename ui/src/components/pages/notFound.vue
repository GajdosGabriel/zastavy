<script setup lang="ts">
import BaseLayout from '../layout/BaseLayout.vue';


</script>

<template>

      <BaseLayout>
            <!-- Content for the default slot -->
            <template #main>
                  <div class="p-3 order-first">
                        <div class="md:grid grid-cols-2 gap-8">
                              <h1 class="text-2xl font-semibold text-slate-900">Stránka sa nenašla</h1>
                              <p class="mt-2 text-slate-600">Stránka, ktorú hľadáte, neexistuje alebo bola presunutá.</p>
                              <div class="mt-6 flex flex-wrap gap-3">
                                    <router-link :to="{ name: 'public.index' }" class="rounded-md bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Späť na úvod</router-link>
                                    <router-link :to="{ name: 'public.index' }" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Prejsť do katalógu</router-link>
                              </div>
                        </div>
                  </div>
            </template>

      </BaseLayout>

</template>
