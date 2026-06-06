<script setup>
import { onMounted } from "vue";
import { useRoute } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import useAdminUsers from "../../store/StoreAdminUsers";

const { fetchUser, getUser } = useAdminUsers();
const {
    params: { userId },
} = useRoute();

onMounted(() => {
    fetchUser(userId);
});

const buttonBack = { name: "Späť", link: "/users", icon: "arrow-left" };
const buttonEdit = () => ({
    name: "Upraviť",
    link: `/users/${getUser.value.id}/edit`,
    icon: "plus",
});

const fullName = () => {
    const user = getUser.value || {};
    return [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || "-";
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <div class="flex items-center justify-between">
                    <h1 class="page-heading px-0 my-4">Používateľ</h1>
                    <div class="flex gap-2">
                        <buttonRouterLink :item="buttonBack" class="text-sm" />
                        <buttonRouterLink v-if="getUser?.id" :item="buttonEdit()" class="text-sm" />
                    </div>
                </div>

                <div class="rounded-md bg-white p-6 shadow">
                    <dl class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Meno</dt>
                            <dd class="mt-1 text-gray-900">{{ fullName() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Používateľské meno</dt>
                            <dd class="mt-1 text-gray-900">{{ getUser.username || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Email</dt>
                            <dd class="mt-1 text-gray-900">{{ getUser.email || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Telefón</dt>
                            <dd class="mt-1 text-gray-900">{{ getUser.phone || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Zákazník</dt>
                            <dd class="mt-1 text-gray-900">
                                {{ getUser.customer?.company || "-" }}
                                <span v-if="getUser.customer?.city" class="text-gray-500">({{ getUser.customer.city }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Status</dt>
                            <dd class="mt-1 text-gray-900">{{ getUser.status?.label || "-" }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Objednávky</dt>
                            <dd class="mt-1 text-gray-900">{{ getUser.orders_count ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold text-gray-500">Role</dt>
                            <dd class="mt-1 flex flex-wrap gap-2">
                                <span
                                    v-for="role in getUser.roles"
                                    :key="role"
                                    class="inline-flex rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200"
                                >
                                    {{ role }}
                                </span>
                                <span v-if="!getUser.roles?.length">-</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
