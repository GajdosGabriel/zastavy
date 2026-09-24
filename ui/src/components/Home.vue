<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import usePaginator from '../store/StorePaginator';
import { catalogQuery, changeCatalogQuery } from '../models/catalogQuery';
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

const route=useRoute(), router=useRouter(), paginator=usePaginator();
watch(()=>route.query, query=>{
 const store=useQuery(); store.resetQuery();
 for(const [key,value] of Object.entries(catalogQuery(query))) store.setQuery({key:key+'=',value});
 homeStore.applyFilters();
},{immediate:true});
const page=(value:number)=>router.push({query:changeCatalogQuery(route.query,{page:String(value)})});
onMounted(() => {

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

                              <div class="order-last lg:order-first lg:col-span-9">
                                    <div v-if="getProducts.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                          <cart v-for="card in getProducts" :item="templateProduct(card)"
                                                :key="card.id" />
                                    </div>
                                    <p v-else class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-16 text-center text-slate-500">
                                          Zvoleným filtrom nezodpovedá žiadny tovar.
                                    </p>
                                    <nav v-if="paginator.meta && paginator.meta.last_page > 1" aria-label="Stránkovanie katalógu" class="flex justify-center items-center gap-4 mt-6">
                                          <button :disabled="paginator.meta.current_page <= 1" @click="page(paginator.meta.current_page-1)" class="rounded border p-2 disabled:opacity-40">Predchádzajúca</button>
                                          <span>Strana {{paginator.meta.current_page}} z {{paginator.meta.last_page}}</span>
                                          <button :disabled="paginator.meta.current_page >= paginator.meta.last_page" @click="page(paginator.meta.current_page+1)" class="rounded border p-2 disabled:opacity-40">Ďalšia</button>
                                    </nav>
                              </div>

                              <aside class="order-first lg:order-last space-y-5 lg:col-span-3">
                                    <div class="sticky top-4 space-y-5">
                                          <CatalogFilter />
                                          <kosikLink />
                                          <nazoryZakaznikov />
                                          <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                                                <router-link to="/dopyt" class="block font-semibold text-blue-700 underline hover:text-blue-900">Požiadať o cenovú ponuku</router-link>
                                          </div>
                                    </div>
                              </aside>
                        </div>
                  </section>
            </template>

      </BaseLayout>

</template>
