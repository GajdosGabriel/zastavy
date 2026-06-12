<script setup>
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import useAdminUsers from "../../store/StoreAdminUsers";
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";

const {
    state,
    fetchCreateOptions,
    storeUser,
    getRoles,
    getStatuses,
    getPortalPermissions,
    getCustomers,
    canManageRoles,
    canManagePermissions,
} = useAdminUsers();

const router = useRouter();

onMounted(fetchCreateOptions);

const saveUser = async () => {
    const saved = await storeUser();
    if (saved) {
        router.push({ name: "users.index" });
    }
};

const buttonBack = { name: "Späť", link: "/users", icon: "arrow-left" };
const buttonSubmit = { name: "Vytvoriť a odoslať email", spinner: true };
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <div class="flex items-center justify-between">
                    <h1 class="page-heading px-0 my-4">Nový používateľ</h1>
                    <buttonRouterLink :item="buttonBack" class="text-sm" />
                </div>

                <form @submit.prevent="saveUser" class="rounded bg-white px-8 pt-6 pb-8 shadow-md">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Meno <RequiredMark /></label>
                            <FormInput v-model="state.user.firstName" placeholder="Meno" required />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Priezvisko</label>
                            <FormInput v-model="state.user.lastName" placeholder="Priezvisko" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Email <RequiredMark /></label>
                            <FormInput v-model="state.user.email" type="email" placeholder="Email" required />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Telefón</label>
                            <FormInput v-model="state.user.phone" placeholder="Telefón" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Zákazník <RequiredMark /></label>
                            <select v-model="state.user.customer_id" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400">
                                <option :value="undefined" disabled>— Vybrať zákazníka —</option>
                                <option v-for="c in getCustomers" :key="c.value" :value="c.value">
                                    {{ c.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Role — len super-admin -->
                        <div v-if="canManageRoles && getRoles.length" class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Role</label>
                            <div class="flex flex-wrap gap-3 rounded border p-3">
                                <label v-for="role in getRoles" :key="role.value" class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="state.user.roles" type="checkbox" :value="role.value" />
                                    {{ role.label }}
                                </label>
                            </div>
                        </div>

                        <!-- Práva — admin + super-admin -->
                        <div v-if="canManagePermissions && getPortalPermissions.length" class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Práva na objednávky</label>
                            <div class="rounded border p-3">
                                <p class="mb-3 text-xs text-gray-500">
                                    Bez pridelených práv má používateľ základný prístup (zobrazenie a vytváranie objednávok).
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="perm in getPortalPermissions" :key="perm.value" class="inline-flex items-center gap-2 text-sm">
                                        <input v-model="state.user.permissions" type="checkbox" :value="perm.value" class="rounded" />
                                        {{ perm.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-gray-500">
                        Po vytvorení bude používateľovi odoslané dočasné heslo na zadaný email.
                    </p>

                    <div class="mt-6 flex justify-between">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
