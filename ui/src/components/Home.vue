<script setup lang="ts">
import { onMounted } from 'vue';
import BaseLayout from './layout/BaseLayout.vue';
import cart from './checkout/cart.vue';
import nazoryZakaznikov from './pages/nazoryZakaznikov.vue';
import kosikLink from './checkout/kosikLink.vue';
import useHome from "../store/StoreHome";
import templateProduct from '../models/templateProduct';

const { getProducts, fetchProducts } = useHome();

onMounted(() => {
      fetchProducts();
});

</script>

<template>

      <BaseLayout>

            <template #main>
                  <section class="col-span-12 bg-slate-100 px-3 pb-8 md:px-6">
                        <div class="grid gap-6 lg:grid-cols-12">

                              <div class="lg:col-span-9">
                                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                          <cart v-for="card in getProducts" :item="templateProduct(card)"
                                                :key="card.id" />
                                    </div>
                              </div>

                              <aside class="space-y-5 lg:col-span-3">
                                    <div class="sticky top-4 space-y-5">
                                          <kosikLink />
                                          <nazoryZakaznikov />
                                    </div>
                              </aside>
                        </div>
                  </section>
            </template>

      </BaseLayout>

</template>
