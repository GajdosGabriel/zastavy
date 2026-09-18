<script setup lang="ts">
import { computed, ref } from 'vue';

interface Day {
      date: string;
      order_count: number;
      value: number;
      shipped_value: number;
}

const props = defineProps<{ days: Day[] }>();

const metrics = [
      { key: 'value', label: 'Objednané €', bar: 'bg-blue-500', barHover: 'group-hover:bg-blue-600' },
      { key: 'shipped_value', label: 'Expedované €', bar: 'bg-emerald-500', barHover: 'group-hover:bg-emerald-600' },
      { key: 'order_count', label: 'Počet objednávok', bar: 'bg-violet-500', barHover: 'group-hover:bg-violet-600' },
] as const;

type MetricKey = typeof metrics[number]['key'];

const metric = ref<MetricKey>('value');
const hovered = ref<number | null>(null);

const current = computed(() => metrics.find((item) => item.key === metric.value)!);
const values = computed(() => props.days.map((day) => Number(day[metric.value] || 0)));
const max = computed(() => Math.max(...values.value, 0));
const total = computed(() => values.value.reduce((sum, value) => sum + value, 0));
const average = computed(() => (values.value.length ? total.value / values.value.length : 0));

const isMoney = computed(() => metric.value !== 'order_count');
const format = (value: number) => isMoney.value
      ? `${value.toLocaleString('sk-SK', { maximumFractionDigits: 0 })} €`
      : value.toLocaleString('sk-SK');

// Posledný deň série je „dnes" podľa servera — nezávisí od časového pásma prehliadača.
const today = computed(() => props.days[props.days.length - 1]?.date);

const dayLabel = (date: string) => new Date(date + 'T00:00:00').toLocaleDateString('sk-SK', {
      weekday: 'short', day: 'numeric', month: 'numeric',
});
const isWeekend = (date: string) => [0, 6].includes(new Date(date + 'T00:00:00').getDay());

const active = computed(() => hovered.value === null ? null : props.days[hovered.value]);
</script>

<template>
      <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                        <h2 class="text-base font-semibold text-slate-900">Posledných 30 dní</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                              Spolu <span class="font-semibold text-slate-800">{{ format(total) }}</span>
                              · priemer {{ format(average) }} / deň
                        </p>
                  </div>
                  <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium">
                        <button v-for="item in metrics" :key="item.key" type="button"
                              class="rounded px-2.5 py-1 transition"
                              :class="metric === item.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                              @click="metric = item.key">
                              {{ item.label }}
                        </button>
                  </div>
            </div>

            <div class="relative mt-5">
                  <!-- Tooltip -->
                  <div class="pointer-events-none absolute -top-2 right-0 h-10 text-right text-sm">
                        <template v-if="active">
                              <div class="font-semibold text-slate-900">{{ format(Number(active[metric] || 0)) }}</div>
                              <div class="text-xs text-slate-500">
                                    {{ dayLabel(active.date) }}
                                    <template v-if="metric !== 'order_count'"> · {{ active.order_count }} obj.</template>
                              </div>
                        </template>
                  </div>

                  <div class="flex h-44 items-end gap-[3px] pt-10" @mouseleave="hovered = null">
                        <div v-for="(day, index) in days" :key="day.date"
                              class="group relative flex h-full flex-1 cursor-default items-end rounded-sm"
                              :class="isWeekend(day.date) ? 'bg-slate-50' : ''"
                              @mouseenter="hovered = index">
                              <div class="w-full rounded-t-sm transition-all duration-300"
                                    :class="[
                                          values[index] > 0 ? [current.bar, current.barHover] : 'bg-slate-200',
                                          hovered !== null && hovered !== index ? 'opacity-40' : '',
                                          day.date === today ? 'ring-2 ring-offset-1 ring-slate-400' : '',
                                    ]"
                                    :style="{ height: values[index] > 0 && max > 0 ? `${Math.max(4, (values[index] / max) * 100)}%` : '2px' }" />
                        </div>
                  </div>

                  <div class="mt-2 flex justify-between text-[11px] text-slate-400">
                        <span>{{ days.length ? dayLabel(days[0].date) : '' }}</span>
                        <span>dnes</span>
                  </div>
            </div>
      </div>
</template>
