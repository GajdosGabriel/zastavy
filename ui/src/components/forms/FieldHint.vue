<script setup lang="ts">
/**
 * Rada ku konkrétnemu poľu z živej kontroly údajov.
 *
 * Zámerne nevyzerá ako chyba validácie — uloženie neblokuje. Je to to isté,
 * čo by po uložení našla post-kontrola, len povedané včas. Keď má rada
 * konkrétny tvar hodnoty, je pri nej tlačidlo, ktoré ho do poľa doplní.
 */
defineProps<{
    hint: { severity: string; message: string; suggested: string | null } | null;
}>();

defineEmits<{ (e: "apply"): void }>();
</script>

<template>
    <p
        v-if="hint"
        class="mt-1 flex flex-wrap items-baseline gap-x-1.5 text-xs"
        :class="hint.severity === 'error' ? 'text-red-600' : hint.severity === 'warning' ? 'text-amber-700' : 'text-gray-500'"
    >
        <span>{{ hint.message }}</span>
        <button
            v-if="hint.suggested"
            type="button"
            class="font-semibold underline decoration-dotted underline-offset-2 hover:no-underline"
            @click="$emit('apply')"
        >
            použiť „{{ hint.suggested }}"
        </button>
    </p>
</template>
