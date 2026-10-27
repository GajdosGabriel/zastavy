<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import axiosInstance from '../../axiosInstance';
import { useUsers as useUser } from '../../store/StoreUsers';
import { PAGE_ORDER } from '../../constants';
import { plural } from '../../models/functions';

const REFRESH_MS = 2 * 60 * 1000;
const STAFF_ROLES = ['super-admin', 'admin', 'manager', 'sales', 'warehouse'];

const { getUser } = storeToRefs(useUser());
const route = useRoute();

const root = ref<HTMLElement>();
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const counts = ref<Record<string, number | null> | null>(null);

type Alert = { id: string; kind: string; title: string; body: string | null; url: string | null; read_at: string | null; created_at: string };
const tab = ref<'todo' | 'history'>('todo');
const history = ref<Alert[] | null>(null);
const historyFailed = ref(false);
const keepDays = ref(30);
const alertsUnread = computed(() => Number(counts.value?.alerts_unread || 0));

// Rovnaká podmienka ako DashboardMiddleware — ostatným by endpoint vrátil 403.
const enabled = computed(() => Boolean(getUser.value?.isAuth)
      && (getUser.value.customer_id != null || getUser.value.roles?.some((role: string) => STAFF_ROLES.includes(role))));

const orders = (query: Record<string, any>) => ({ name: PAGE_ORDER.ROUTE, query });

const items = computed(() => {
      const c = counts.value || {};
      const n = (key: string) => Number(c[key] || 0);
      return [
            {
                  key: 'overdue', tone: 'bg-red-500', to: { name: 'dashboard.index' },
                  text: `${n('overdue')} ${plural(n('overdue'), 'objednávka čaká', 'objednávky čakajú', 'objednávok čaká')} viac ako týždeň`,
            },
            {
                  key: 'unopened', tone: 'bg-blue-500', to: orders({ filter: 'isOpened' }),
                  text: `${n('unopened')} ${plural(n('unopened'), 'nepotvrdená objednávka', 'nepotvrdené objednávky', 'nepotvrdených objednávok')}`,
            },
            {
                  key: 'not_notified', tone: 'bg-rose-500', to: orders({ filter: 'isNotificated' }),
                  text: `${n('not_notified')} ${plural(n('not_notified'), 'expedícia', 'expedície', 'expedícií')} bez správy zákazníkovi`,
            },
            {
                  key: 'quotes_requested', tone: 'bg-amber-500', to: { name: 'sales.quotes.index', query: { status: 'requested' } },
                  text: `${n('quotes_requested')} ${plural(n('quotes_requested'), 'cenový dopyt čaká', 'cenové dopyty čakajú', 'cenových dopytov čaká')} na nacenenie`,
            },
            {
                  key: 'quotes_unread', tone: 'bg-amber-500', to: { name: 'sales.quotes.index', query: { status: 'unread' } },
                  text: `${n('quotes_unread')} ${plural(n('quotes_unread'), 'ponuka má', 'ponuky majú', 'ponúk má')} novú odpoveď zákazníka`,
            },
            {
                  key: 'messages_unread', tone: 'bg-blue-500', to: { name: 'sales.index' },
                  text: `${n('messages_unread')} ${plural(n('messages_unread'), 'neprečítaná správa', 'neprečítané správy', 'neprečítaných správ')} od zákazníkov`,
            },
            {
                  key: 'production_overdue', tone: 'bg-red-500', to: { name: 'sales.index' },
                  text: `${n('production_overdue')} ${plural(n('production_overdue'), 'zákazka', 'zákazky', 'zákaziek')} vo výrobe po termíne`,
            },
      ].filter((item) => n(item.key) > 0);
});

const total = computed(() => items.value.reduce((sum, item) => sum + Number(counts.value?.[item.key] || 0), 0));
// Ponuky a výrobu posiela API len obsluhe; pre ostatných je hodnota null.
const hasSales = computed(() => counts.value?.production_overdue != null);

const load = async () => {
      if (!enabled.value || loading.value) return;
      loading.value = true;
      try {
            counts.value = (await axiosInstance.get('/dashboard/notifications')).data.data;
            failed.value = false;
      } catch {
            // Zvonček je doplnok — pri výpadku ostane ticho a ponúkne skúsiť znova.
            failed.value = true;
      } finally {
            loading.value = false;
      }
};

const loadHistory = async () => {
      try {
            const { data } = await axiosInstance.get('/dashboard/alerts');
            history.value = data.data;
            keepDays.value = data.keep_days || keepDays.value;
            if (counts.value) counts.value = { ...counts.value, alerts_unread: data.unread };
            historyFailed.value = false;
      } catch {
            historyFailed.value = true;
      }
};

const showTab = (value: 'todo' | 'history') => {
      tab.value = value;
      if (value === 'history') loadHistory();
};

const setUnread = (value: number) => {
      if (counts.value) counts.value = { ...counts.value, alerts_unread: Math.max(0, value) };
};

// Kliknutie označí upozornenie ako prečítané; záznam ostáva v histórii.
const openAlert = (alert: Alert) => {
      open.value = false;
      if (alert.read_at) return;
      alert.read_at = new Date().toISOString();
      setUnread(alertsUnread.value - 1);
      axiosInstance.post(`/dashboard/alerts/${alert.id}/read`).catch(() => {});
};

const readAll = async () => {
      try {
            await axiosInstance.post('/dashboard/alerts/read-all');
            const now = new Date().toISOString();
            history.value?.forEach((alert) => { alert.read_at ??= now; });
            setUnread(0);
      } catch {
            historyFailed.value = true;
      }
};

const formatTime = (value: string) => new Date(value).toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' });

const toggle = () => {
      open.value = !open.value;
      if (open.value) {
            load();
            if (tab.value === 'history') loadHistory();
      }
};
const outside = (event: Event) => { if (!root.value?.contains(event.target as Node)) open.value = false; };
const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') open.value = false; };

let timer: ReturnType<typeof setInterval> | undefined;

watch(() => route.fullPath, () => (open.value = false));
watch(enabled, (value) => {
      counts.value = null;
      history.value = null;
      if (value) load();
}, { immediate: true });

onMounted(() => {
      document.addEventListener('pointerdown', outside);
      document.addEventListener('keyup', onKey);
      timer = setInterval(() => { if (document.visibilityState === 'visible') load(); }, REFRESH_MS);
});
onBeforeUnmount(() => {
      clearInterval(timer);
      document.removeEventListener('pointerdown', outside);
      document.removeEventListener('keyup', onKey);
});
</script>

<template>
      <div v-if="enabled" ref="root" class="relative mr-2 shrink-0">
            <button type="button"
                  class="relative flex h-9 w-9 items-center justify-center rounded-md transition hover:bg-gray-200 focus:outline-none"
                  :class="total ? 'text-red-600' : 'text-gray-600'"
                  :aria-label="total ? `Upozornenia: ${total}` : 'Upozornenia'" :aria-expanded="open" @click="toggle">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                  </svg>
                  <span v-if="total"
                        class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white">
                        {{ total > 99 ? '99+' : total }}
                  </span>
                  <span v-else-if="alertsUnread" class="absolute right-1 top-1 h-2.5 w-2.5 rounded-full bg-blue-600" aria-hidden="true" />
            </button>

            <section v-if="open" aria-label="Upozornenia"
                  class="fixed inset-x-3 top-16 z-50 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96">
                  <div class="flex items-center gap-1 border-b border-slate-100 px-2 pt-2" role="tablist">
                        <button v-for="t in ([['todo', 'Na vybavenie', total], ['history', 'História', alertsUnread]] as const)" :key="t[0]"
                              type="button" role="tab" :aria-selected="tab === t[0]" @click="showTab(t[0])"
                              class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-bold transition"
                              :class="tab === t[0] ? 'border-blue-600 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'">
                              {{ t[1] }}
                              <span v-if="t[2]" class="rounded-full px-1.5 text-xs leading-5 text-white"
                                    :class="t[0] === 'todo' ? 'bg-red-600' : 'bg-blue-600'">{{ t[2] > 99 ? '99+' : t[2] }}</span>
                        </button>
                  </div>

                  <template v-if="tab === 'history'">
                        <p v-if="historyFailed" role="alert" class="px-4 py-3 text-sm text-red-600">
                              Históriu sa nepodarilo načítať.
                              <button type="button" class="underline" @click="loadHistory">Skúsiť znova</button>
                        </p>
                        <p v-else-if="!history" role="status" class="px-4 py-8 text-center text-sm text-slate-500">Načítavam…</p>
                        <p v-else-if="!history.length" class="px-4 py-8 text-center text-sm text-slate-500">Zatiaľ žiadne upozornenia.</p>

                        <ul v-if="history?.length" class="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto">
                              <li v-for="alert in history" :key="alert.id">
                                    <component :is="alert.url ? 'router-link' : 'div'" :to="alert.url || undefined" v-bind="alert.url ? { activeClass: '', exactActiveClass: '' } : {}" @click="openAlert(alert)"
                                          class="flex gap-3 px-4 py-3 text-sm transition hover:bg-slate-50"
                                          :class="alert.read_at ? 'text-slate-500' : 'bg-blue-50/40 text-slate-800'">
                                          <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="alert.read_at ? 'bg-transparent' : 'bg-blue-600'" />
                                          <span class="min-w-0 flex-1">
                                                <span class="block" :class="alert.read_at ? 'font-medium' : 'font-semibold'">{{ alert.title }}</span>
                                                <span v-if="alert.body" class="block truncate text-xs text-slate-500">{{ alert.body }}</span>
                                                <span class="block text-xs text-slate-400">{{ formatTime(alert.created_at) }}</span>
                                          </span>
                                    </component>
                              </li>
                        </ul>

                        <div v-if="history?.length" class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
                              <span>Prečítané sa mažú po {{ keepDays }} dňoch.</span>
                              <button v-if="alertsUnread" type="button" class="font-semibold text-blue-700 hover:underline" @click="readAll">
                                    Označiť všetko ako prečítané
                              </button>
                        </div>
                  </template>

                  <template v-else>
                  <p v-if="failed" role="alert" class="px-4 py-3 text-sm text-red-600">
                        Upozornenia sa nepodarilo načítať.
                        <button type="button" class="underline" :disabled="loading" @click="load">Skúsiť znova</button>
                  </p>
                  <p v-else-if="!counts" role="status" class="px-4 py-8 text-center text-sm text-slate-500">Načítavam…</p>
                  <p v-else-if="!items.length" class="px-4 py-8 text-center text-sm text-slate-500">Nič nečaká na vybavenie.</p>

                  <ul v-if="items.length" class="divide-y divide-slate-100">
                        <li v-for="item in items" :key="item.key">
                              <router-link :to="item.to" @click="open = false"
                                    class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition hover:bg-slate-50 hover:text-blue-700">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="item.tone" />
                                    <span class="min-w-0 flex-1">{{ item.text }}</span>
                                    <span class="text-slate-500" aria-hidden="true">→</span>
                              </router-link>
                        </li>
                  </ul>

                  <router-link v-if="hasSales" :to="{ name: 'sales.index' }" @click="open = false"
                        class="flex items-center justify-between border-t border-slate-100 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800 transition hover:bg-blue-100">
                        Ponuky a plán výroby
                        <span>→</span>
                  </router-link>
                  </template>
            </section>
      </div>
</template>
