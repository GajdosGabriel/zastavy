<script setup>
import { onMounted } from "vue";
import { useRoute } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import useAdminUsers from "../../store/StoreAdminUsers";

const { fetchUser, getUser } = useAdminUsers();
const { params: { userId } } = useRoute();

onMounted(() => fetchUser(userId));

const buttonBack = { name: "Späť", link: "/users", icon: "arrow-left" };
const buttonEdit = () => ({ name: "Upraviť", link: `/users/${getUser.value.id}/edit`, icon: "plus" });

const fullName = () => {
    const user = getUser.value || {};
    return [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || "-";
};
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
                    </div>
                    <div class="flex gap-2">
                        <buttonRouterLink :item="buttonBack" class="text-sm" />
                        <buttonRouterLink v-if="getUser?.id" :item="buttonEdit()" class="text-sm" />
                    </div>
                </div>

                <!-- Detail karta -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="grid divide-y divide-gray-100 px-5 py-1 sm:grid-cols-3 sm:divide-x sm:divide-y-0">

                        <!-- Kontakt -->
                        <div class="py-3 sm:pr-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Kontakt</p>
                            <p class="text-sm font-semibold text-gray-900">{{ fullName() }}</p>
                            <p class="text-sm text-gray-500">{{ getUser.username || '—' }}</p>
                            <a v-if="getUser.phone" :href="`tel:${getUser.phone}`"
                               class="block text-sm text-blue-700 hover:underline">{{ getUser.phone }}</a>
                            <a v-if="getUser.email" :href="`mailto:${getUser.email}`"
                               class="block text-sm text-blue-700 hover:underline">{{ getUser.email }}</a>
                        </div>

                        <!-- Zákazník + Status -->
                        <div class="py-3 sm:px-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</p>
                            <p class="text-sm font-semibold text-gray-900">{{ getUser.customer?.company || '—' }}</p>
                            <p v-if="getUser.customer?.city" class="text-sm text-gray-500">{{ getUser.customer.city }}</p>
                            <div class="mt-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="getUser.status?.value === 'active'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-600'">
                                    {{ getUser.status?.label || '—' }}
                                </span>
                            </div>
                        </div>

                        <!-- Meta -->
                        <div class="py-3 sm:pl-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Info</p>
                            <div class="mb-2 text-sm text-gray-600">
                                <span class="text-gray-400">Objednávky:</span> {{ getUser.orders_count ?? 0 }}
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-gray-400">Role</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="role in getUser.roles" :key="role"
                                        class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                        {{ role }}
                                    </span>
                                    <span v-if="!getUser.roles?.length" class="text-sm text-gray-400">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </template>
    </BaseLayout>
</template>
