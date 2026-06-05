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
                  <div class="page-body col-span-12">

                        <div class="md:grid grid-cols-10 gap-10">

                              <div class="p-3 md:col-span-7 border-solid pl-2 border-slate-300  border-4 rounded-md">
                                    <div class="md:grid lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-10">
                                          <cart v-for="card in getProducts" :item="templateProduct(card)"
                                                :key="card.id" />
                                    </div>
                              </div>

                              <div class="p-3 col-span-3 border-solid pl-2 border-slate-300  border-4 rounded-md">
                                    <kosikLink />
                                    <nazoryZakaznikov />
                              </div>
                        </div>
                  </div>
            </template>

      </BaseLayout>

</template>
