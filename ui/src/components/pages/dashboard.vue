<script setup lang="ts">
import { onMounted, computed } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import OrderStatistics from '../order/OrderStatistics.vue';
import useOrders from '../../store/StoreOrders';
import useUser from '../../store/StoreUsers';

const { fetchOrderStatistics } = useOrders();
const { getUser } = useUser();

const isSuperAdmin = computed(() => getUser.value?.roles?.includes('super-admin'));

onMounted(() => {
      fetchOrderStatistics();
});
</script>

<template>
      <BaseLayout>
            <template #main>
                  <section class="col-span-12 px-4 pb-10 sm:px-7">
                        <div class="rounded-md border border-slate-200 bg-white p-6 shadow-sm">
                              <p class="text-sm font-semibold uppercase text-blue-800">
                                    Administrácia
                              </p>
                              <h1 class="mt-2 text-3xl font-semibold text-slate-900">
                                    Dashboard
                              </h1>
                              <p class="mt-3 max-w-3xl leading-7 text-slate-600">
                                    Vitaj v administrácii obchodu. Tu bude rýchly prehľad objednávok,
                                    produktov, skladu a zákazníkov.
                              </p>
                        </div>

                        <OrderStatistics class="mt-6" />

                        <div class="mt-6 grid gap-5 md:grid-cols-3">
                              <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-sm text-slate-500">Dnes</p>
                                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                          Nové objednávky
                                    </h2>
                                    <p class="mt-3 text-slate-600">
                                          Demo panel pre počet nových objednávok a rýchly vstup do expedície.
                                    </p>
                              </article>

                              <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-sm text-slate-500">Sklad</p>
                                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                          Stav zásob
                                    </h2>
                                    <p class="mt-3 text-slate-600">
                                          Demo panel pre produkty, ktoré treba doplniť alebo skontrolovať.
                                    </p>
                              </article>

                              <article class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-sm text-slate-500">Zákazníci</p>
                                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                          Aktivita
                                    </h2>
                                    <p class="mt-3 text-slate-600">
                                          Demo panel pre posledné nákupy, kontakty a otvorené požiadavky.
                                    </p>
                              </article>

                              <router-link
                                    :to="{ name: 'announcements.index' }"
                                    class="rounded-md border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:bg-blue-50"
                              >
                                    <p class="text-sm text-slate-500">Obsah webu</p>
                                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                          Oznamy a bannery
                                    </h2>
                                    <p class="mt-3 text-slate-600">
                                          Upravit horny akcny banner, dolny oznam, publikovanie a datumy zobrazenia.
                                    </p>
                              </router-link>

                              <template v-if="isSuperAdmin">
                                    <router-link
                                          :to="{ name: 'shipping-methods.index' }"
                                          class="rounded-md border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:bg-blue-50"
                                    >
                                          <p class="text-sm text-slate-500">Super admin</p>
                                          <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                                Spôsoby dopravy
                                          </h2>
                                          <p class="mt-3 text-slate-600">
                                                Spravovať spôsoby dopravy, ceny, prahy bezplatnej dopravy a poradie.
                                          </p>
                                    </router-link>

                                    <router-link
                                          :to="{ name: 'payment-methods.index' }"
                                          class="rounded-md border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:bg-blue-50"
                                    >
                                          <p class="text-sm text-slate-500">Super admin</p>
                                          <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                                Spôsoby platby
                                          </h2>
                                          <p class="mt-3 text-slate-600">
                                                Spravovať spôsoby platby, poplatky, typy a poradie zobrazenia.
                                          </p>
                                    </router-link>

                                    <router-link
                                          :to="{ name: 'coupons.index' }"
                                          class="rounded-md border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:bg-blue-50"
                                    >
                                          <p class="text-sm text-slate-500">Super admin</p>
                                          <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                                                Kupóny
                                          </h2>
                                          <p class="mt-3 text-slate-600">
                                                Vytvárať a spravovať zľavové kupóny, limity a platnosti.
                                          </p>
                                    </router-link>
                              </template>
                        </div>
                  </section>
            </template>
      </BaseLayout>
</template>
