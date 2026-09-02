<script setup>
import { computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import CopyButton from "../layout/page/CopyButton.vue";
import { storeToRefs } from "pinia";
import { useAdminUsers } from "../../store/StoreAdminUsers";

const adminUsersStore = useAdminUsers();
const { getUser } = storeToRefs(adminUsersStore);
const { fetchUser } = adminUsersStore;
const { params: { userId } } = useRoute();

onMounted(() => fetchUser(userId));

const buttonBack = { name: "Späť", link: "/users", icon: "arrow-left" };
const buttonEdit = () => ({ name: "Upraviť", link: `/users/${getUser.value.id}/edit`, icon: "plus" });

const user = computed(() => getUser.value || {});

const fullName = () => {
    const u = user.value;
    return u.fullName || [u.firstName, u.lastName].filter(Boolean).join(" ") || u.username || "-";
};

const localeLabels = { sk: 'Slovenčina', cs: 'Čeština', en: 'Angličtina' };
const localeLabel = () => localeLabels[user.value.locale] || user.value.locale || '—';

// Nikdy neprihlásený = účet dostal pozvánku, ale portál ešte nepoužil.
const neverLoggedIn = computed(() => !user.value.last_login_at);
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Používateľ</p>
                        <h1 class="text-xl font-bold text-gray-900">{{ fullName() }}</h1>
                        <p v-if="user.position" class="text-sm text-gray-500">{{ user.position }}</p>
                    </div>
                    <div class="flex gap-2">
                        <buttonRouterLink :item="buttonBack" class="text-sm" />
                        <buttonRouterLink v-if="user.id" :item="buttonEdit()" class="text-sm" />
                    </div>
                </div>

                <!-- Detail karta -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="grid divide-y divide-gray-100 px-5 py-1 sm:grid-cols-3 sm:divide-x sm:divide-y-0">

                        <!-- Kontakt -->
                        <div class="py-3 sm:pr-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Kontakt</p>
                            <p class="text-sm font-semibold text-gray-900">{{ fullName() }}</p>
                            <p v-if="user.position" class="text-sm text-gray-600">{{ user.position }}</p>
                            <p class="text-sm text-gray-500">{{ user.username || '—' }}</p>

                            <div v-if="user.phone" class="mt-1 flex items-center gap-1.5">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                                </svg>
                                <a :href="`tel:${user.phone}`" class="truncate text-sm text-blue-700 hover:underline">{{ user.phone }}</a>
                                <CopyButton :value="user.phone" label="Kopírovať telefón" size="h-3.5 w-3.5" />
                            </div>

                            <div v-if="user.email" class="mt-1 flex items-center gap-1.5">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                </svg>
                                <a :href="`mailto:${user.email}`" class="truncate text-sm text-blue-700 hover:underline">{{ user.email }}</a>
                                <CopyButton :value="user.email" label="Kopírovať email" size="h-3.5 w-3.5" />
                            </div>

                            <p class="mt-2 text-xs text-gray-400">
                                Email overený:
                                <span :class="user.email_verified_at ? 'text-gray-600' : 'text-amber-600'">
                                    {{ user.email_verified_at || 'neoverený' }}
                                </span>
                            </p>
                        </div>

                        <!-- Zákazník + Status -->
                        <div class="py-3 sm:px-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</p>
                            <router-link v-if="user.customer?.id"
                                :to="{ name: 'customers.show', params: { customerId: user.customer.id } }"
                                class="text-sm font-semibold text-blue-700 hover:underline">
                                {{ user.customer.company || '—' }}
                            </router-link>
                            <p v-else class="text-sm font-semibold text-gray-900">—</p>
                            <p v-if="user.customer?.city" class="text-sm text-gray-500">{{ user.customer.city }}</p>

                            <div class="mt-2 flex flex-wrap gap-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="user.status?.value === 'active'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-600'">
                                    {{ user.status?.label || '—' }}
                                </span>
                                <span v-if="user.active === false"
                                    class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                    Neaktívny
                                </span>
                            </div>

                            <div class="mt-3">
                                <p class="mb-1 text-xs text-gray-400">Role</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in user.roles" :key="role"
                                        class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                        {{ role }}
                                    </span>
                                    <span v-if="!user.roles?.length" class="text-sm text-gray-400">—</span>
                                </div>
                            </div>

                            <div v-if="user.permissions?.length" class="mt-3">
                                <p class="mb-1 text-xs text-gray-400">Práva</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="perm in user.permissions" :key="perm"
                                        class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ perm }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Prihlásenie + účet -->
                        <div class="py-3 sm:pl-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Prihlásenie</p>

                            <div v-if="neverLoggedIn"
                                class="mb-3 flex items-start gap-1.5 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs text-amber-800">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                                <span>Do portálu sa ešte nikdy neprihlásil.</span>
                            </div>

                            <div v-else class="mb-3">
                                <div class="flex items-center gap-1.5 text-sm text-gray-700">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="font-semibold">{{ user.last_login_at }}</span>
                                    <CopyButton :value="user.last_login_at" label="Kopírovať dátum prihlásenia" size="h-3.5 w-3.5" />
                                </div>
                                <p v-if="user.last_login_human" class="ml-5 text-xs text-gray-400">{{ user.last_login_human }}</p>
                            </div>

                            <dl class="space-y-1 text-sm text-gray-600">
                                <div v-if="user.last_activity_at" class="flex items-center gap-1.5">
                                    <span class="text-gray-400">Posledná aktivita:</span>
                                    <span>{{ user.last_activity_at }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-400">Počet prihlásení:</span>
                                    <span>{{ user.login_count ?? 0 }}</span>
                                </div>
                                <div v-if="user.last_login_ip" class="flex items-center gap-1.5">
                                    <span class="text-gray-400">IP:</span>
                                    <span class="font-mono text-xs">{{ user.last_login_ip }}</span>
                                    <CopyButton :value="user.last_login_ip" label="Kopírovať IP" size="h-3.5 w-3.5" />
                                </div>
                            </dl>

                            <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Účet</p>
                            <dl class="space-y-1 text-sm text-gray-600">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-gray-400">Objednávky:</span>
                                    <span>{{ user.orders_count ?? 0 }}</span>
                                    <router-link v-if="user.last_order?.id"
                                        :to="{ name: 'orders.show', params: { orderId: user.last_order.id } }"
                                        class="text-xs text-blue-700 hover:underline">
                                        posledná {{ user.last_order.serial_number }} ({{ user.last_order.created_at }})
                                    </router-link>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-400">Vytvorený:</span>
                                    <span>{{ user.created_at || '—' }}</span>
                                    <span v-if="user.created_human" class="text-xs text-gray-400">· {{ user.created_human }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-400">Upravený:</span>
                                    <span>{{ user.updated_at || '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-400">Jazyk:</span>
                                    <span>{{ localeLabel() }}</span>
                                </div>
                                <div v-if="user.uuid" class="flex items-center gap-1.5">
                                    <span class="text-gray-400">UUID:</span>
                                    <span class="truncate font-mono text-xs">{{ user.uuid }}</span>
                                    <CopyButton :value="user.uuid" label="Kopírovať UUID" size="h-3.5 w-3.5" />
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Interná poznámka — vidí ju len správa účtov -->
                <div v-if="user.note" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Interná poznámka</p>
                    <p class="whitespace-pre-line text-sm text-amber-900">{{ user.note }}</p>
                </div>

            </div>
        </template>
    </BaseLayout>
</template>
