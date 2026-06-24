<script setup>
defineProps({
    customer: { type: Object, default: () => ({}) },
    user:     { type: Object, default: null },
    order:    { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">

        <!-- Firma / zákazník -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</div>

            <div class="flex gap-4">
                <!-- Ľavá strana: firma, adresa, IČO -->
                <div class="flex-1 min-w-0">
                    <div class="mb-1 text-base font-bold text-gray-900 truncate">
                        {{ customer.company || customer.name || '—' }}
                    </div>

                    <div v-if="customer.street || customer.city" class="mb-2 text-sm text-gray-600 leading-snug">
                        <div v-if="customer.street">{{ customer.street }}</div>
                        <div>{{ [customer.postcode, customer.city].filter(Boolean).join(' ') }}</div>
                    </div>

                    <div v-if="customer.ico || customer.dic || customer.ic_dic" class="border-t border-gray-100 pt-2 text-xs text-gray-500 space-y-0.5">
                        <div v-if="customer.ico">IČO: <strong class="text-gray-700">{{ customer.ico }}</strong></div>
                        <div v-if="customer.dic">DIČ: <strong class="text-gray-700">{{ customer.dic }}</strong></div>
                        <div v-if="customer.ic_dic">IČ DPH: <strong class="text-gray-700">{{ customer.ic_dic }}</strong></div>
                    </div>
                </div>

                <!-- Pravá strana: kontaktná osoba, tel, email -->
                <div class="shrink-0 border-l border-gray-100 pl-4 text-sm space-y-1.5">
                    <div v-if="customer.company && customer.name" class="font-medium text-gray-700">
                        {{ customer.name }}
                    </div>
                    <a v-if="user?.phone || customer.phone"
                        :href="`tel:${user?.phone || customer.phone}`"
                        class="flex items-center gap-1.5 font-medium text-blue-600 hover:text-blue-800">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                        </svg>
                        {{ user?.phone || customer.phone }}
                    </a>
                    <a v-if="user?.email || customer.email"
                        :href="`mailto:${user?.email || customer.email}`"
                        class="flex items-center gap-1.5 font-medium text-blue-600 hover:text-blue-800">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        {{ user?.email || customer.email }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Objednávka -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Objednávka</div>

            <div class="mb-3 flex items-center gap-2">
                <span class="text-xl font-bold text-gray-900">{{ order.serial_number }}</span>
                <span v-if="order.wants_coupon"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
                    title="Zákazník požaduje zľavový kupón">K</span>
            </div>

            <div class="space-y-1 text-sm text-gray-600">
                <div class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                    <span>{{ order.created_at }} <span class="text-gray-400">prijatá</span></span>
                </div>
                <div v-for="shipping in (order.shippings ?? [])" :key="shipping.id" class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-green-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                    </svg>
                    <span>{{ shipping.created_at }} <span class="text-gray-400">expedovaná</span></span>
                </div>
            </div>

            <div v-if="order.note" class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <span class="font-semibold">Poznámka: </span>{{ order.note }}
            </div>
        </div>

    </div>
</template>
