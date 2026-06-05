<script setup>
const reviews = [
    {
        text: 'Expresné rýchle dodanie tovaru.',
    },
    {
        text: 'Veľmi rýchly nákup bez problémov.',
    },
    {
        text: 'Nevyjadrujem sa...',
    },
    {
        text: 'Veľmi dobrá spolupráca, profesionalita a ochota. Doporučujem ako obchodného partnera.',
    },
    {
        text: 'Som spokojná, objednávka bola vybavená okamžite a taktiež tovar bol doručený ihneď a v poriadku.',
    },
    {
        text: 'Ďakujem za spoluprácu, maximálna spokojnosť a ústretovosť pri vybavení objednávky. Odporúčame.',
    },
    {
        text: 'Pri vlajke EU som nevedela zadať, či chcem vlajku alebo zástavu.',
        response: 'Pri EU sa nerozlišuje zástava ani vlajka. Vlajka EU sa používa ako zástava aj vlajka.',
    },
];
</script>

<template>
    <aside class="hidden md:block rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 border-b border-slate-200 pb-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Referencie</p>
            <h4 class="mt-1 text-lg font-semibold text-slate-900">Názory zákazníkov</h4>
        </div>

        <div class="space-y-3">
            <article v-for="(review, index) in reviews" :key="index"
                class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm leading-relaxed text-slate-700">
                <div class="flex gap-2">
                    <span class="mt-0.5 text-lg leading-none text-blue-700">“</span>
                    <p>{{ review.text }}</p>
                </div>

                <div v-if="review.response" class="mt-3 rounded-md border border-blue-100 bg-blue-50 p-3 text-blue-900">
                    <div class="text-xs font-semibold uppercase text-blue-800">Odpoveď obchodu</div>
                    <p class="mt-1">{{ review.response }}</p>
                </div>
            </article>
        </div>
    </aside>
</template>
