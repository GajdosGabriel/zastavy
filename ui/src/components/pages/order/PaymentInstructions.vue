<script setup>
import CopyButton from "../../layout/page/CopyButton.vue";
import { formatPrice } from "../../../models/functions";

// Údaje na úhradu prevodom z API (payment_instructions). Bez nich sa nič nezobrazí.
defineProps({
    payment: { type: Object, default: null },
});

const formatIban = (iban) => String(iban ?? "").replace(/\s+/g, "").replace(/(.{4})/g, "$1 ").trim();
</script>

<template>
    <section v-if="payment" class="rounded-md border border-blue-200 bg-blue-50 p-4 sm:p-5" aria-labelledby="payment-title">
        <h2 id="payment-title" class="text-base font-semibold text-blue-950">Platba prevodom na účet</h2>
        <p class="mt-1 text-sm text-blue-900">Objednávku vybavíme po prijatí platby. Pri úhrade uveďte variabilný symbol.</p>

        <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">IBAN</dt>
                <dd class="mt-0.5 flex items-center gap-2 font-mono text-base font-semibold text-gray-900">
                    <span class="break-all">{{ formatIban(payment.iban) }}</span>
                    <CopyButton :value="String(payment.iban).replace(/\s+/g, '')" label="Kopírovať IBAN" size="h-5 w-5" class="p-1.5" />
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Variabilný symbol</dt>
                <dd class="mt-0.5 flex items-center gap-2 font-mono text-base font-semibold text-gray-900">
                    {{ payment.variable_symbol }}
                    <CopyButton :value="payment.variable_symbol" label="Kopírovať variabilný symbol" size="h-5 w-5" class="p-1.5" />
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Suma</dt>
                <dd class="mt-0.5 text-base font-semibold text-gray-900">{{ formatPrice(payment.amount) }} €</dd>
            </div>
            <div v-if="payment.holder">
                <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Príjemca</dt>
                <dd class="mt-0.5 text-gray-900">{{ payment.holder }}</dd>
            </div>
            <div v-if="payment.swift">
                <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">SWIFT / BIC</dt>
                <dd class="mt-0.5 font-mono text-gray-900">{{ payment.swift }}</dd>
            </div>
        </dl>
    </section>
</template>
