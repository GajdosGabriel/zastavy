<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import BaseLayout from '../layout/BaseLayout.vue';
import axiosInstance from '../../axiosInstance';
import useErrors from '../../store/StoreErrors';
import { eventLabel, mailTemplate } from '../../systemLogLabels';

type Row = {
      id: number; createdAt: string | null; level: 'info' | 'warning' | 'error'; channel: string; event: string;
      status: string | null; message: string; recipient: string | null;
      user: { id: number; email: string | null } | null; ip: string | null; context: Record<string, any> | null;
      hasBody: boolean; body?: string | null;
};

const page = ref<{ data: Row[]; meta: any; summary: Record<string, number>; channels: string[]; retention: { days: number } } | null>(null);
const loading = ref(false);
const currentPage = ref(1);
const filters = reactive({ channel: '', level: '', status: '', date_from: '', date_to: '', search: '', recipient: '' });

const channelLabels: Record<string, string> = { order: 'Objednávky', quote: 'Cenové ponuky', stock: 'Sklad', customer: 'Zákazníci', user: 'Používatelia', mail: 'E-maily', auth: 'Prihlásenia', queue: 'Fronta', scheduler: 'Plánovač' };
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

// Náhľad odoslaného e-mailu. Telo sa dotiahne až po kliknutí, zoznam ho nenesie.
const mail = ref<Row | null>(null);
const mailLoading = ref(false);
const openMail = async (row: Row) => {
      mail.value = row;
      if (!row.hasBody) return;
      mailLoading.value = true;
      try {
            const { data } = await axiosInstance.get(`/admin/system-logs/${row.id}`);
            if (mail.value?.id === row.id) mail.value = data.data;
      } catch (e) {
            useErrors().setErrors(e);
      } finally {
            mailLoading.value = false;
      }
};
const mailFields = computed(() => {
      const row = mail.value;
      if (!row) return [];
      const c = row.context ?? {};
      return [
            ['Predmet', row.message],
            ['Od', c.from],
            ['Komu', row.recipient],
            ['Kópia', c.cc],
            ['Skrytá kópia', c.bcc],
            ['Odpoveď na', c.reply_to],
            ['Prílohy', Array.isArray(c.attachments) ? c.attachments.join(', ') : c.attachments],
            ['Čas', formatDate(row.createdAt)],
            ['Chyba', c.error],
      ].filter(([, value]) => value);
});
const omittedNotes: Record<string, string> = {
      sensitive: 'Obsah tohto e-mailu sa neukladá, lebo obsahuje heslo alebo odkaz na zmenu hesla.',
      bulk: 'Obsah hromadnej kampane sa do denníka neukladá — nájdete ho v Emailingu.',
};
const mailNote = computed(() => omittedNotes[mail.value?.context?.body_omitted]
      ?? 'Obsah tohto e-mailu nie je uložený (starší záznam alebo e-mail zlyhal ešte pred vykreslením).');
// Odkazy v náhľade otvárame v novom okne, skripty sandbox nepustí.
const mailDoc = computed(() => `<base target="_blank">${mail.value?.body ?? ''}`);

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
                                    Vytvorené objednávky, čo komu odišlo, čo zlyhalo a kto sa prihlásil.
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
                                    <span class="text-xs text-slate-500">Hľadať (e-mail, predmet, objednávka)</span>
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
                                                <router-link v-if="row.context?.order_id" :to="{ name: 'orders.show', params: { orderId: row.context.order_id } }" class="underline decoration-dotted hover:text-slate-900">Objednávka {{ row.context.serial_number || '#' + row.context.order_id }}</router-link>
                                                <span v-if="row.user">Vykonal: {{ row.user.email || '#' + row.user.id }}</span>
                                                <span v-if="row.ip">🌐 {{ row.ip }}</span>
                                                <button v-if="row.channel === 'mail'" type="button" class="font-medium text-blue-700 hover:underline" @click="openMail(row)">Zobraziť e-mail</button>
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

                  <Teleport to="body">
                        <div v-if="mail" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-40 p-4" @click.self="mail = null">
                              <div class="flex max-h-full w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl" role="dialog" aria-modal="true">
                                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-4">
                                          <div>
                                                <h3 class="text-lg font-semibold text-slate-900">{{ mailTemplate(mail.context?.class).label }}</h3>
                                                <p v-if="mailTemplate(mail.context?.class).description" class="text-sm text-slate-600">{{ mailTemplate(mail.context?.class).description }}</p>
                                          </div>
                                          <button type="button" class="rounded bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-200" @click="mail = null">Zavrieť</button>
                                    </div>
                                    <div class="overflow-y-auto p-4">
                                          <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                                <template v-for="[label, value] in mailFields" :key="label">
                                                      <dt class="text-slate-500">{{ label }}</dt>
                                                      <dd class="break-words text-slate-900">{{ value }}</dd>
                                                </template>
                                                <dt class="text-slate-500">Stav</dt>
                                                <dd><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[mail.status ?? '']">{{ statusLabels[mail.status ?? ''] ?? mail.status }}</span></dd>
                                          </dl>
                                          <p v-if="mailLoading" class="mt-4 text-sm text-slate-600">Načítavam obsah…</p>
                                          <iframe v-else-if="mail.body" :srcdoc="mailDoc" sandbox="allow-popups allow-popups-to-escape-sandbox" title="Obsah e-mailu" class="mt-4 h-[60vh] w-full rounded border border-slate-200 bg-white"></iframe>
                                          <p v-else class="mt-4 rounded bg-slate-50 p-3 text-sm text-slate-600">{{ mailNote }}</p>
                                    </div>
                              </div>
                        </div>
                  </Teleport>
            </template>
      </BaseLayout>
</template>
