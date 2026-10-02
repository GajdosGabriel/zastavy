<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import axiosInstance from '../../axiosInstance';
import useErrors from '../../store/StoreErrors';
import { eventLabel } from '../../systemLogLabels';

type Row = {
      id: number; createdAt: string | null; level: 'info' | 'warning' | 'error'; channel: string; event: string;
      status: string | null; message: string; recipient: string | null;
      user: { id: number; email: string | null } | null; ip: string | null; context: Record<string, any> | null;
};

const page = ref<{ data: Row[]; meta: any; summary: Record<string, number>; channels: string[]; retention: { days: number } } | null>(null);
const loading = ref(false);
const currentPage = ref(1);
const filters = reactive({ channel: '', level: '', status: '', date_from: '', date_to: '', search: '', recipient: '' });

const channelLabels: Record<string, string> = { mail: 'E-maily', auth: 'Prihlásenia', queue: 'Fronta', scheduler: 'Plánovač' };
const statusLabels: Record<string, string> = { sent: 'odoslané', failed: 'zlyhalo', ok: 'v poriadku' };
const levelClass: Record<string, string> = {
      info: 'bg-slate-100 text-slate-700', warning: 'bg-amber-100 text-amber-800', error: 'bg-red-100 text-red-700',
};
const statusClass: Record<string, string> = {
      sent: 'bg-green-100 text-green-800', ok: 'bg-green-100 text-green-800', failed: 'bg-red-100 text-red-700',
};

const load = async () => {
      loading.value = true;
      try {
            const params: Record<string, any> = { page: currentPage.value };
            for (const [key, value] of Object.entries(filters)) if (value) params[key] = value;
            const { data } = await axiosInstance.get('/admin/system-logs', { params });
            page.value = data;
      } catch (e) {
            useErrors().setErrors(e);
      } finally {
            loading.value = false;
      }
};

const apply = () => { currentPage.value = 1; load(); };
const reset = () => { Object.keys(filters).forEach((key) => ((filters as any)[key] = '')); apply(); };
const filterRecipient = (email: string) => { filters.recipient = email; apply(); };
const go = (n: number) => { currentPage.value = n; load(); };
const anyFilter = computed(() => Object.values(filters).some(Boolean));

const formatDate = (value: string | null) => value
      ? new Date(value).toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
      : '—';

const cards = computed(() => {
      const s = page.value?.summary;
      if (!s) return [];
      return [
            { label: 'Odoslané e-maily', value: s.sentDay, note: `${s.sentWeek} za 7 dní`, alert: false },
            { label: 'Neodoslané e-maily', value: s.failedDay, note: `${s.failedWeek} za 7 dní`, alert: s.failedDay > 0 },
            { label: 'Prihlásenia', value: s.loginsDay, note: 'za 24 hodín', alert: false },
            { label: 'Zlyhané prihlásenia', value: s.authFailedDay, note: 'za 24 hodín', alert: false },
      ];
});

onMounted(() => { document.title = 'Denník udalostí'; load(); });
</script>

<template>
      <BaseLayout>
            <template #main>
                  <section class="col-span-12 space-y-5 px-4 pb-10 sm:px-7">
                        <div>
                              <h1 class="text-2xl font-semibold text-slate-900">Denník udalostí</h1>
                              <p class="text-sm text-slate-600">
                                    Čo komu odišlo, čo zlyhalo a kto sa prihlásil.
                                    <template v-if="page">Záznamy sa držia {{ page.retention.days }} dní.</template>
                              </p>
                        </div>

                        <div v-if="page" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                              <div v-for="card in cards" :key="card.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                                    <p class="mt-1 text-2xl font-semibold" :class="card.alert ? 'text-red-600' : 'text-slate-900'">{{ card.value }}</p>
                                    <p class="text-xs text-slate-500">{{ card.note }}</p>
                              </div>
                        </div>

                        <form class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm" @submit.prevent="apply">
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Oblasť</span>
                                    <select v-model="filters.channel" class="rounded border border-slate-300 px-2 py-1.5" @change="apply">
                                          <option value="">Všetko</option>
                                          <option v-for="c in page?.channels ?? []" :key="c" :value="c">{{ channelLabels[c] ?? c }}</option>
                                    </select>
                              </label>
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Úroveň</span>
                                    <select v-model="filters.level" class="rounded border border-slate-300 px-2 py-1.5" @change="apply">
                                          <option value="">Všetko</option>
                                          <option value="info">info</option>
                                          <option value="warning">varovanie</option>
                                          <option value="error">chyba</option>
                                    </select>
                              </label>
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Stav</span>
                                    <select v-model="filters.status" class="rounded border border-slate-300 px-2 py-1.5" @change="apply">
                                          <option value="">Všetko</option>
                                          <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                              </label>
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Od</span>
                                    <input v-model="filters.date_from" type="date" class="rounded border border-slate-300 px-2 py-1.5" @change="apply">
                              </label>
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Do</span>
                                    <input v-model="filters.date_to" type="date" class="rounded border border-slate-300 px-2 py-1.5" @change="apply">
                              </label>
                              <label class="grid gap-1">
                                    <span class="text-xs text-slate-500">Hľadať (e-mail, predmet)</span>
                                    <input v-model="filters.search" type="search" class="rounded border border-slate-300 px-2 py-1.5" placeholder="napr. objednávku">
                              </label>
                              <button type="submit" class="rounded bg-blue-700 px-4 py-1.5 font-semibold text-white hover:bg-blue-800">Filtrovať</button>
                              <button v-if="filters.recipient" type="button" class="rounded bg-slate-100 px-3 py-1.5" @click="filters.recipient = ''; apply()">
                                    {{ filters.recipient }} ✕
                              </button>
                              <button v-if="anyFilter" type="button" class="text-xs text-slate-500 underline" @click="reset">Zrušiť filtre</button>
                        </form>

                        <p v-if="loading && !page" class="text-slate-600">Načítavam…</p>

                        <div v-else-if="page" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                              <div class="mb-3 flex items-baseline justify-between">
                                    <h2 class="font-semibold text-slate-900">Udalosti</h2>
                                    <span class="text-xs text-slate-500">{{ page.meta.total }} záznamov</span>
                              </div>
                              <ul class="divide-y divide-slate-100" :class="{ 'opacity-60': loading }">
                                    <li v-for="row in page.data" :key="row.id" class="py-3">
                                          <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="rounded-full px-2 py-0.5 font-semibold" :class="levelClass[row.level]" :title="row.event">{{ eventLabel(row.event) }}</span>
                                                <span v-if="row.status" class="rounded-full px-2 py-0.5 font-medium" :class="statusClass[row.status]">{{ statusLabels[row.status] ?? row.status }}</span>
                                                <span class="text-slate-500">{{ formatDate(row.createdAt) }}</span>
                                          </div>
                                          <p class="mt-1 break-words text-sm text-slate-900">{{ row.message || '—' }}</p>
                                          <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                                <button v-if="row.recipient" type="button" class="break-all underline decoration-dotted hover:text-slate-900" title="Všetko pre tohto príjemcu" @click="filterRecipient(row.recipient)">✉ {{ row.recipient }}</button>
                                                <span v-if="row.ip">🌐 {{ row.ip }}</span>
                                          </div>
                                          <details v-if="row.context" class="mt-2 text-xs">
                                                <summary class="cursor-pointer text-slate-500 hover:text-slate-900">Podrobnosti</summary>
                                                <pre class="mt-1 max-h-72 overflow-auto whitespace-pre-wrap break-all rounded bg-slate-50 p-2 text-slate-700">{{ JSON.stringify(row.context, null, 2) }}</pre>
                                          </details>
                                    </li>
                                    <li v-if="!page.data.length" class="py-4 text-slate-500">Žiadne záznamy.</li>
                              </ul>
                              <div v-if="page.meta.lastPage > 1" class="mt-4 flex items-center justify-center gap-3 text-sm">
                                    <button type="button" class="rounded border px-3 py-1 disabled:opacity-40" :disabled="currentPage <= 1" @click="go(currentPage - 1)">← Novšie</button>
                                    <span>{{ page.meta.currentPage }} / {{ page.meta.lastPage }}</span>
                                    <button type="button" class="rounded border px-3 py-1 disabled:opacity-40" :disabled="currentPage >= page.meta.lastPage" @click="go(currentPage + 1)">Staršie →</button>
                              </div>
                        </div>
                  </section>
            </template>
      </BaseLayout>
</template>
