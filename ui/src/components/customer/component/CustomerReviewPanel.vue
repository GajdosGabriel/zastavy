<script setup lang="ts">
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import useCustomers from "../../../store/StoreCustomers";

/**
 * Výsledok post-kontroly údajov zákazníka.
 *
 * Panel má dve časti a je dôležité, aby ich admin nepomiešal:
 *
 *   „Opravené automaticky"  — oznam. Toto sa už stalo, pôvodná hodnota je
 *                             vidieť, aby sa dalo vrátiť.
 *   „Na pozretie"           — otázka. Návrh, ktorý by zmenil význam údaja,
 *                             takže čaká na klik.
 *
 * Prijíma sa poradové číslo výhrady, nie hodnota — server zapíše to, čo sám
 * navrhol, takže z prehliadača sa nedá poslať vlastný text.
 */
const props = defineProps<{ customerId: number | string }>();

const customersStore = useCustomers();
const { getReview, isReviewLoading } = storeToRefs(customersStore);
const { runReview, applyReviewSuggestions, revertReviewChanges, resolveReview } = customersStore;

const selected = ref<number[]>([]);
const message = ref("");
const open = ref(true);

const issues = computed(() => getReview.value?.issues ?? []);
const applied = computed(() => getReview.value?.applied ?? []);
const applicable = computed(() => issues.value.filter((i) => i.applicable));

const score = computed(() => getReview.value?.score ?? null);

const tone = computed(() => {
    const severity = getReview.value?.severity;
    if (severity === "error") return { ring: "border-red-200", bg: "bg-red-50", text: "text-red-800", dot: "bg-red-500" };
    if (severity === "warning") return { ring: "border-amber-200", bg: "bg-amber-50", text: "text-amber-800", dot: "bg-amber-500" };
    return { ring: "border-blue-100", bg: "bg-blue-50", text: "text-blue-800", dot: "bg-blue-400" };
});

const severityLabel = (severity: string) =>
    ({ error: "Chyba", warning: "Upozornenie", notice: "Drobnosť" }[severity] ?? severity);

const severityClass = (severity: string) =>
    ({
        error: "bg-red-100 text-red-700",
        warning: "bg-amber-100 text-amber-700",
        notice: "bg-gray-100 text-gray-600",
    }[severity] ?? "bg-gray-100 text-gray-600");

const sourceLabel = (source: string) =>
    ({ rule: "pravidlo", registry: "register", ai: "AI" }[source] ?? source);

const toggle = (index: number) => {
    const at = selected.value.indexOf(index);
    at === -1 ? selected.value.push(index) : selected.value.splice(at, 1);
};

const selectAll = () => {
    selected.value = applicable.value.map((i) => i.index);
};

const onRun = async () => {
    message.value = "";
    const error = await runReview(props.customerId);
    message.value = error || "Kontrola prebehla.";
    selected.value = [];
};

const onApply = async () => {
    if (!selected.value.length) return;

    message.value = "";
    const error = await applyReviewSuggestions(props.customerId, [...selected.value]);
    message.value = error || "Zmeny sú zapísané.";
    selected.value = [];
};

const onRevert = async (index: number) => {
    message.value = "";
    const error = await revertReviewChanges(props.customerId, [index]);
    message.value = error || "Pôvodná hodnota je späť.";
};

const onResolve = async () => {
    await resolveReview(props.customerId);
    message.value = "Posudok je odbavený.";
};
</script>

<template>
    <div v-if="getReview" class="mb-6 overflow-hidden rounded-lg border" :class="[tone.ring, tone.bg]">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            @click="open = !open"
        >
            <span class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="tone.dot"></span>
                <span class="text-sm font-semibold" :class="tone.text">
                    Kontrola údajov
                    <span v-if="score !== null" class="font-normal">— skóre {{ score }}/100</span>
                </span>
            </span>
            <span class="text-xs" :class="tone.text">
                {{ issues.length }} na pozretie · {{ applied.length }} opravených
            </span>
        </button>

        <div v-if="open" class="border-t border-white/60 bg-white/70 px-4 py-4">
            <p v-if="getReview.summary" class="mb-3 text-sm text-gray-700">{{ getReview.summary }}</p>

            <p v-if="getReview.pending" class="mb-3 text-xs text-gray-500">
                Kontrola je naplánovaná a dobehne na pozadí.
            </p>

            <p v-if="getReview.last_error" class="mb-3 text-xs text-red-600">
                Posledný pokus zlyhal: {{ getReview.last_error }}
            </p>

            <!-- Oznam: čo sa už zmenilo -->
            <div v-if="applied.length" class="mb-4">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Opravené automaticky</h3>
                <ul class="space-y-1">
                    <li v-for="change in applied" :key="'a' + change.index" class="flex items-baseline gap-2 text-sm text-gray-700">
                        <span class="min-w-0 flex-1">
                            <span class="font-semibold">{{ change.label }}:</span>
                            <span class="text-gray-400 line-through">{{ change.from || "prázdne" }}</span>
                            <span class="mx-1">→</span>
                            <span class="font-medium text-green-700">{{ change.to || "prázdne" }}</span>
                            <span class="ml-1 text-xs text-gray-400">({{ sourceLabel(change.source) }})</span>
                        </span>
                        <button
                            type="button"
                            :disabled="isReviewLoading"
                            class="shrink-0 text-xs font-semibold text-gray-500 hover:text-red-600 disabled:opacity-50"
                            title="Vrátiť pôvodnú hodnotu"
                            @click="onRevert(change.index)"
                        >
                            vrátiť
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Otázka: čo čaká na potvrdenie -->
            <div v-if="issues.length">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Na pozretie</h3>
                    <button
                        v-if="applicable.length > 1"
                        type="button"
                        class="text-xs font-semibold text-blue-600 hover:underline"
                        @click="selectAll"
                    >
                        Označiť všetky s návrhom
                    </button>
                </div>

                <ul class="space-y-2">
                    <li
                        v-for="issue in issues"
                        :key="issue.index"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2"
                    >
                        <div class="flex items-start gap-3">
                            <input
                                v-if="issue.applicable"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-gray-300"
                                :checked="selected.includes(issue.index)"
                                @change="toggle(issue.index)"
                            />
                            <span v-else class="mt-1 h-4 w-4"></span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-800">{{ issue.label }}</span>
                                    <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold" :class="severityClass(issue.severity)">
                                        {{ severityLabel(issue.severity) }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">{{ sourceLabel(issue.source) }}</span>
                                </div>

                                <p class="mt-0.5 text-sm text-gray-600">{{ issue.message }}</p>

                                <p v-if="issue.applicable" class="mt-1 text-sm">
                                    <span class="text-gray-400 line-through">{{ issue.current || "prázdne" }}</span>
                                    <span class="mx-1 text-gray-400">→</span>
                                    <span class="font-medium text-gray-900">{{ issue.suggested }}</span>
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <p v-else-if="!applied.length" class="text-sm text-gray-600">Údaje sú v poriadku.</p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :disabled="!selected.length || isReviewLoading"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                    @click="onApply"
                >
                    Použiť označené ({{ selected.length }})
                </button>
                <button
                    type="button"
                    :disabled="isReviewLoading"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50"
                    @click="onRun"
                >
                    {{ isReviewLoading ? "Kontrolujem..." : "Skontrolovať znova" }}
                </button>
                <button
                    v-if="issues.length && !getReview.resolved_at"
                    type="button"
                    :disabled="isReviewLoading"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-700 disabled:opacity-50"
                    @click="onResolve"
                >
                    Nechať tak
                </button>
                <span v-if="message" class="text-xs text-gray-600">{{ message }}</span>
            </div>
        </div>
    </div>
</template>
