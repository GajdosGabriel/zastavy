<script setup lang="ts">
import { onMounted, watch } from 'vue';
import BaseLayout from './layout/BaseLayout.vue';
import cart from './checkout/cart.vue';
import nazoryZakaznikov from './pages/nazoryZakaznikov.vue';
import kosikLink from './checkout/kosikLink.vue';
import CatalogFilter from './product/CatalogFilter.vue';
import { storeToRefs } from "pinia";
import { useHome } from "../store/StoreHome";
import useQuery from "../store/StoreQuery";
import templateProduct from '../models/templateProduct';
import { setJsonLd, organizationJsonLd, websiteJsonLd, absoluteUrl } from '../models/seo';

const homeStore = useHome();
const { getProducts } = storeToRefs(homeStore);
const { fetchProducts } = homeStore;

onMounted(() => {
      // Filtre z predchádzajúcej stránky by inak zúžili výpis bez zaškrtnutého políčka.
      useQuery().resetQuery();
      fetchProducts();

      setJsonLd('organization', organizationJsonLd());
      setJsonLd('website', websiteJsonLd());
});

// Zoznam tovaru dáva robotovi odkazy na detaily aj mimo sitemap.
watch(getProducts, (products) => {
      if (!products.length) return;

      setJsonLd('itemList', {
            '@context': 'https://schema.org',
            '@type': 'ItemList',
            name: 'Vlajky a zástavy',
            itemListElement: products.map((product, index) => ({
                  '@type': 'ListItem',
                  position: index + 1,
                  name: product.name,
                  url: absoluteUrl(`/product/${product.id}/show/${product.slug}`),
            })),
      });
});

</script>

<template>

      <BaseLayout>

            <template #main>
                  <section class="col-span-12 bg-slate-100 px-3 pb-8 md:px-6">
                        <div class="grid gap-6 lg:grid-cols-12">

                              <div class="lg:col-span-9">
                                    <div v-if="getProducts.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                          <cart v-for="card in getProducts" :item="templateProduct(card)"
                                                :key="card.id" />
                                    </div>
                                    <p v-else class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-16 text-center text-slate-500">
                                          Zvoleným filtrom nezodpovedá žiadny tovar.
                                    </p>
                              </div>

                              <aside class="space-y-5 lg:col-span-3">
                                    <div class="sticky top-4 space-y-5">
                                          <CatalogFilter />
                                          <kosikLink />
                                          <nazoryZakaznikov />
                                    </div>
                              </aside>
                        </div>
                  </section>
            </template>

      </BaseLayout>

</template>
