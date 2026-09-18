<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import BaseLayout from '../layout/BaseLayout.vue';
import DailyChart from '../dashboard/DailyChart.vue';
import axiosInstance from '../../axiosInstance';
import useErrors from '../../store/StoreErrors';
import { useUsers as useUser } from '../../store/StoreUsers';
import { PAGE_ORDER } from '../../constants';

const REFRESH_MS = 2 * 60 * 1000;

const { getUser } = storeToRefs(useUser());

const data = ref<Record<string, any> | null>(null);
const isLoading = ref(false);
const loadedAt = ref<Date | null>(null);
const now = ref(new Date());

const isSuperAdmin = computed(() => getUser.value?.roles?.includes('super-admin'));
const canCreateOrder = computed(() => Boolean(getUser.value?.can?.['orders.create']));

const fetchDashboard = async () => {
      isLoading.value = true;
      try {
            const response = await axiosInstance.get('/dashboard');
            data.value = response.data.data;
            loadedAt.value = new Date();
      } catch (e) {
            useErrors().setErrors(e);
      } finally {
            isLoading.value = false;
      }
};

let refreshTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
      document.title = 'Dashboard';
      fetchDashboard();
      // Dashboard býva otvorený celý deň — obnovíme ho, len keď je karta viditeľná.
      refreshTimer = setInterval(() => {
            if (document.visibilityState === 'visible') fetchDashboard();
      }, REFRESH_MS);
      clockTimer = setInterval(() => (now.value = new Date()), 30 * 1000);
});

onUnmounted(() => {
      clearInterval(refreshTimer);
      clearInterval(clockTimer);
});

// ── Formátovanie ─────────────────────────────────────────────
const number = (value: any) => Number(value || 0).toLocaleString('sk-SK');
const money = (value: any, digits = 0) => `${Number(value || 0).toLocaleString('sk-SK', {
      minimumFractionDigits: digits,
      maximumFractionDigits: digits,
})} €`;

const greeting = computed(() => {
      const hour = now.value.getHours();
      if (hour < 10) return 'Dobré ráno';
      if (hour < 18) return 'Dobrý deň';
      return 'Dobrý večer';
});

const todayLabel = computed(() => now.value.toLocaleDateString('sk-SK', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
}));

const updatedLabel = computed(() => {
      if (!loadedAt.value) return '';
      const minutes = Math.floor((now.value.getTime() - loadedAt.value.getTime()) / 60000);
      return minutes < 1 ? 'aktualizované práve teraz' : `aktualizované pred ${minutes} min`;
});

const ordersLink = (query: Record<string, any>) => ({ name: PAGE_ORDER.ROUTE, query });

// ── Fronta: čo treba riešiť ──────────────────────────────────
const queueTiles = [
      {
            key: 'unopened', label: 'Nepotvrdené', hint: 'nové, nikto ich ešte neotvoril',
            query: { filter: 'isOpened' },
            accent: 'border-l-blue-500', value: 'text-blue-700', ring: 'hover:ring-blue-200',
      },
      {
            key: 'active', label: 'Čakajú na expedíciu', hint: 'zatiaľ bez jediného dodaného kusu',
            query: { filter: 'isActive' },
            accent: 'border-l-amber-500', value: 'text-amber-700', ring: 'hover:ring-amber-200',
      },
      {
            key: 'partially_shipped', label: 'Čiastočne expedované', hint: 'niečo odišlo, zvyšok chýba',
            query: { status: 'partially_shipped' },
            accent: 'border-l-orange-500', value: 'text-orange-700', ring: 'hover:ring-orange-200',
      },
      {
            key: 'ready_to_ship', label: 'Pripravené na odoslanie', hint: 'čakajú na kuriéra',
            query: { status: 'ready_to_ship' },
            accent: 'border-l-indigo-500', value: 'text-indigo-700', ring: 'hover:ring-indigo-200',
      },
      {
            key: 'not_notified', label: 'Neoznámené', hint: 'expedované bez správy zákazníkovi',
            query: { filter: 'isNotificated' },
            accent: 'border-l-rose-500', value: 'text-rose-700', ring: 'hover:ring-rose-200',
      },
      {
            key: 'marked', label: 'Označené hviezdičkou', hint: 'tvoje poznačené objednávky',
            query: { marked: 1 },
            accent: 'border-l-yellow-400', value: 'text-yellow-700', ring: 'hover:ring-yellow-200',
      },
];

const queue = computed(() => data.value?.queue || {});
const allClear = computed(() => data.value && Number(queue.value.open || 0) === 0 && Number(queue.value.unopened || 0) === 0);

// ── Tržby ────────────────────────────────────────────────────
const salesCards = computed(() => {
      const sales = data.value?.sales || {};
      return [
            { key: 'today', label: 'Dnes', compare: 'vs. včera do tejto hodiny' },
            { key: 'week', label: 'Tento týždeň', compare: 'vs. minulý týždeň k dnešku' },
            { key: 'month', label: 'Tento mesiac', compare: 'vs. minulý mesiac k dnešku' },
      ].map((card) => {
            const period = sales[card.key] || {};
            const current = Number(period.current?.value || 0);
            const previous = Number(period.previous?.value || 0);
            return {
                  ...card,
                  value: current,
                  orders: Number(period.current?.order_count || 0),
                  shipped: Number(period.shipped?.value || 0),
                  delta: previous > 0 ? Math.round(((current - previous) / previous) * 100) : null,
                  isNew: previous === 0 && current > 0,
            };
      });
});

// ── Zoznamy ──────────────────────────────────────────────────
const waiting = computed(() => data.value?.waiting || []);
const missingProducts = computed(() => data.value?.missing_products || []);
const topProducts = computed(() => data.value?.top_products || []);
const missingMax = computed(() => Math.max(1, ...missingProducts.value.map((item: any) => item.remaining_quantity)));
const topMax = computed(() => Math.max(1, ...topProducts.value.map((item: any) => item.value)));

const ageClass = (days: number) => {
      if (days >= 14) return 'bg-red-100 text-red-800';
      if (days >= 7) return 'bg-amber-100 text-amber-800';
      return 'bg-slate-100 text-slate-700';
};
const ageLabel = (days: number) => {
      if (days === 0) return 'dnes';
      if (days === 1) return 'včera';
      return days < 5 ? `${days} dni` : `${days} dní`;
};
const progress = (order: any) => order.required_quantity > 0
      ? Math.round((order.shipped_quantity / order.required_quantity) * 100)
      : 0;

// ── Správa ───────────────────────────────────────────────────
const adminLinks = computed(() => [
      { route: 'announcements.index', label: 'Oznamy a bannery', hint: 'Horný banner, dolný oznam, termíny', show: true },
      { route: 'coupons.index', label: 'Kupóny', hint: 'Zľavy, limity, platnosť', show: isSuperAdmin.value },
      { route: 'shipping-methods.index', label: 'Spôsoby dopravy', hint: 'Ceny a doprava zdarma', show: isSuperAdmin.value },
      { route: 'payment-methods.index', label: 'Spôsoby platby', hint: 'Poplatky a poradie', show: isSuperAdmin.value },
      { route: 'attributes.index', label: 'Vlastnosti produktov', hint: 'Rozmer, materiál, uchytenie', show: isSuperAdmin.value },
      { route: 'customers.export.index', label: 'Export zákazníkov', hint: 'CSV s vybranými stĺpcami', show: isSuperAdmin.value },
      { route: 'users.export.index', label: 'Export používateľov', hint: 'CSV s voliteľnými atribútmi', show: isSuperAdmin.value },
].filter((item) => item.show));
</script>

<template>
      <BaseLayout>
            <template #main>
                  <section class="col-span-12 space-y-6 px-4 pb-10 sm:px-7">

                        <!-- Hlavička -->
                        <header class="flex flex-wrap items-end justify-between gap-4">
                              <div>
                                    <p class="text-sm capitalize text-slate-500">{{ todayLabel }}</p>
                                    <h1 class="mt-1 text-3xl font-semibold text-slate-900">
                                          {{ greeting }}<template v-if="getUser?.firstName">, {{ getUser.firstName }}</template>
                                    </h1>
                                    <p v-if="data" class="mt-1 text-slate-600">
                                          <template v-if="allClear">Všetko je vybavené. Pekná práca! 🎉</template>
                                          <template v-else>
                                                Otvorených objednávok: <strong class="text-slate-900">{{ number(queue.open) }}</strong>
                                                <template v-if="queue.overdue">
                                                      · <span class="font-semibold text-red-700">{{ number(queue.overdue) }} čaká viac ako týždeň</span>
                                                </template>
                                          </template>
                                    </p>
                              </div>
                              <div class="flex items-center gap-3">
                                    <button type="button"
                                          class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm transition hover:bg-slate-50"
                                          :disabled="isLoading" @click="fetchDashboard">
                                          <svg class="h-4 w-4" :class="isLoading && 'animate-spin'" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 12a9 9 0 1 1-3-6.7L21 8" />
                                                <path d="M21 3v5h-5" />
                                          </svg>
                                          <span class="hidden sm:inline">{{ updatedLabel || 'Načítavam…' }}</span>
                                    </button>
                                    <router-link v-if="canCreateOrder" :to="{ name: 'orders.create' }"
                                          class="inline-flex items-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800">
                                          + Nová objednávka
                                    </router-link>
                              </div>
                        </header>

                        <!-- Čo treba riešiť -->
                        <div>
                              <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Čo treba riešiť</h2>
                              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                                    <router-link v-for="tile in queueTiles" :key="tile.key" :to="ordersLink(tile.query)"
                                          class="group relative rounded-lg border border-l-4 border-slate-200 bg-white p-4 shadow-sm ring-2 ring-transparent transition hover:-translate-y-0.5 hover:shadow-md"
                                          :class="[tile.accent, tile.ring, data && !queue[tile.key] ? 'opacity-60' : '']">
                                          <div class="flex items-start justify-between gap-2">
                                                <span class="text-sm font-semibold text-slate-700">{{ tile.label }}</span>
                                                <span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500">→</span>
                                          </div>
                                          <div class="mt-2 text-3xl font-bold tabular-nums"
                                                :class="queue[tile.key] ? tile.value : 'text-slate-400'">
                                                <span v-if="!data" class="inline-block h-8 w-10 animate-pulse rounded bg-slate-100" />
                                                <template v-else-if="queue[tile.key]">{{ number(queue[tile.key]) }}</template>
                                                <template v-else>✓</template>
                                          </div>
                                          <p class="mt-1 text-xs text-slate-500">{{ tile.hint }}</p>
                                    </router-link>
                              </div>
                        </div>

                        <!-- Tržby -->
                        <div class="grid gap-3 md:grid-cols-3">
                              <div v-for="card in salesCards" :key="card.key"
                                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-center justify-between">
                                          <span class="text-sm font-semibold text-slate-500">{{ card.label }}</span>
                                          <span v-if="card.delta !== null" :title="card.compare"
                                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                :class="card.delta >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                                                {{ card.delta >= 0 ? '▲' : '▼' }} {{ Math.abs(card.delta) }} %
                                          </span>
                                          <span v-else-if="card.isNew" :title="card.compare"
                                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">nové</span>
                                    </div>
                                    <div class="mt-2 text-3xl font-bold tabular-nums text-slate-900">
                                          <span v-if="!data" class="inline-block h-8 w-32 animate-pulse rounded bg-slate-100" />
                                          <template v-else>{{ money(card.value) }}</template>
                                    </div>
                                    <div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-sm text-slate-500">
                                          <span><strong class="text-slate-800">{{ number(card.orders) }}</strong> objednávok</span>
                                          <span>expedované <strong class="text-emerald-700">{{ money(card.shipped) }}</strong></span>
                                    </div>
                              </div>
                        </div>

                        <DailyChart v-if="data" :days="data.daily" />

                        <div class="grid gap-6 xl:grid-cols-5">
                              <!-- Najdlhšie čakajúce -->
                              <div class="rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-3">
                                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                                          <h2 class="text-base font-semibold text-slate-900">Najdlhšie čakajú</h2>
                                          <router-link :to="{ name: PAGE_ORDER.ROUTE }" class="text-sm font-medium text-blue-700 hover:underline">
                                                Všetky objednávky →
                                          </router-link>
                                    </div>
                                    <ul class="divide-y divide-slate-100">
                                          <li v-for="order in waiting" :key="order.id">
                                                <router-link :to="{ name: 'orders.show', params: { orderId: order.id } }"
                                                      class="flex items-center gap-4 px-5 py-3 transition hover:bg-slate-50">
                                                      <span class="w-16 shrink-0 rounded-full px-2 py-0.5 text-center text-xs font-semibold"
                                                            :class="ageClass(order.days_waiting)">
                                                            {{ ageLabel(order.days_waiting) }}
                                                      </span>
                                                      <div class="min-w-0 flex-1">
                                                            <div class="flex items-center gap-2">
                                                                  <span class="font-semibold text-slate-900">{{ order.serial_number }}</span>
                                                                  <span v-if="!order.is_opened"
                                                                        class="rounded bg-blue-100 px-1.5 text-[11px] font-semibold uppercase text-blue-800">nová</span>
                                                            </div>
                                                            <div class="truncate text-sm text-slate-500">
                                                                  {{ order.customer || 'Bez zákazníka' }}<template v-if="order.city"> · {{ order.city }}</template>
                                                            </div>
                                                      </div>
                                                      <div class="hidden w-32 shrink-0 sm:block">
                                                            <div class="flex justify-between text-xs text-slate-500">
                                                                  <span>{{ order.shipped_quantity }} / {{ order.required_quantity }} ks</span>
                                                                  <span>{{ progress(order) }} %</span>
                                                            </div>
                                                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                                  <div class="h-full rounded-full bg-emerald-500" :style="{ width: progress(order) + '%' }" />
                                                            </div>
                                                      </div>
                                                </router-link>
                                          </li>
                                          <li v-if="data && !waiting.length" class="px-5 py-10 text-center text-sm text-slate-500">
                                                Nič nečaká — všetky objednávky sú vybavené.
                                          </li>
                                    </ul>
                              </div>

                              <!-- Čo chýba dodať -->
                              <div class="rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-2">
                                    <div class="border-b border-slate-200 px-5 py-3">
                                          <h2 class="text-base font-semibold text-slate-900">Čo treba dodať</h2>
                                          <p class="text-xs text-slate-500">Tovar chýbajúci v otvorených objednávkach — klik ukáže objednávky</p>
                                    </div>
                                    <ul class="space-y-1 p-3">
                                          <li v-for="product in missingProducts" :key="product.product_id || product.name">
                                                <router-link :to="ordersLink({ product: product.name })"
                                                      class="block rounded-md px-2 py-2 transition hover:bg-slate-50">
                                                      <div class="flex items-baseline justify-between gap-3 text-sm">
                                                            <span class="truncate font-medium text-slate-800">{{ product.name }}</span>
                                                            <span class="shrink-0 font-bold tabular-nums text-red-700">
                                                                  {{ number(product.remaining_quantity) }} {{ product.unit_value }}
                                                            </span>
                                                      </div>
                                                      <div class="mt-1 flex items-center gap-2">
                                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                                  <div class="h-full rounded-full bg-red-400"
                                                                        :style="{ width: (product.remaining_quantity / missingMax) * 100 + '%' }" />
                                                            </div>
                                                            <span class="w-14 shrink-0 text-right text-xs text-slate-500">
                                                                  {{ product.order_count }} obj.
                                                            </span>
                                                      </div>
                                                </router-link>
                                          </li>
                                          <li v-if="data && !missingProducts.length" class="px-2 py-8 text-center text-sm text-slate-500">
                                                Všetko je dodané ✓
                                          </li>
                                    </ul>
                              </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-5">
                              <!-- Top produkty -->
                              <div class="rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-2">
                                    <div class="border-b border-slate-200 px-5 py-3">
                                          <h2 class="text-base font-semibold text-slate-900">Najpredávanejšie tento mesiac</h2>
                                    </div>
                                    <ol class="space-y-3 p-5">
                                          <li v-for="(product, index) in topProducts" :key="product.product_id || product.name"
                                                class="flex items-center gap-3">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                                      :class="index === 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-slate-100 text-slate-600'">
                                                      {{ index + 1 }}
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                      <div class="flex items-baseline justify-between gap-3 text-sm">
                                                            <span class="truncate font-medium text-slate-800">{{ product.name }}</span>
                                                            <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ money(product.value) }}</span>
                                                      </div>
                                                      <div class="mt-1 flex items-center gap-2">
                                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                                  <div class="h-full rounded-full bg-blue-500"
                                                                        :style="{ width: (product.value / topMax) * 100 + '%' }" />
                                                            </div>
                                                            <span class="w-16 shrink-0 text-right text-xs text-slate-500">
                                                                  {{ number(product.quantity) }} {{ product.unit_value }}
                                                            </span>
                                                      </div>
                                                </div>
                                          </li>
                                          <li v-if="data && !topProducts.length" class="py-6 text-center text-sm text-slate-500">
                                                Tento mesiac zatiaľ bez objednávok.
                                          </li>
                                    </ol>
                              </div>

                              <!-- Správa -->
                              <div class="xl:col-span-3">
                                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Správa obchodu</h2>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                          <router-link v-for="link in adminLinks" :key="link.route" :to="{ name: link.route }"
                                                class="group rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm transition hover:border-blue-300 hover:bg-blue-50">
                                                <div class="flex items-center justify-between font-semibold text-slate-800">
                                                      {{ link.label }}
                                                      <span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-blue-600">→</span>
                                                </div>
                                                <p class="text-sm text-slate-500">{{ link.hint }}</p>
                                          </router-link>
                                    </div>
                              </div>
                        </div>
                  </section>
            </template>
      </BaseLayout>
</template>
