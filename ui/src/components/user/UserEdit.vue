<script setup>
import { computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import validationBar from "../bars/ValidationBar.vue";
import useAdminUsers from "../../store/StoreAdminUsers";
import useUsers from "../../store/StoreUsers";
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";

const { state, fetchUser, updateUser, getRoles, getStatuses } = useAdminUsers();
const { getUser: getAuthUser } = useUsers();
const router = useRouter();
const {
    params: { userId },
} = useRoute();

onMounted(() => {
    fetchUser(userId);
});

const saveUser = async () => {
    const saved = await updateUser();

    if (saved) {
        router.push({ name: "users.show", params: { userId: state.user.id } });
    }
};

const buttonBack = { name: "Späť", link: "/users", icon: "arrow-left" };
const buttonSubmit = { name: "Uložiť", spinner: true };
const canManageRoles = computed(() => getAuthUser.value?.roles?.some(role => ["admin", "super-admin"].includes(role)));
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <div class="flex items-center justify-between">
                    <h1 class="page-heading px-0 my-4">Upraviť používateľa</h1>
                    <buttonRouterLink :item="buttonBack" class="text-sm" />
                </div>

                <form v-if="state.user?.id" @submit.prevent="saveUser" class="rounded bg-white px-8 pt-6 pb-8 shadow-md">
                    <validationBar :validations="null" />

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
                            <label class="mb-2 block text-sm font-bold text-gray-700">Používateľské meno</label>
                            <FormInput v-model="state.user.username" placeholder="Používateľské meno" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Email <RequiredMark /></label>
                            <FormInput v-model="state.user.email" type="email" placeholder="Email" required />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Telefón</label>
                            <FormInput v-model="state.user.phone" placeholder="Telefón" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Status <RequiredMark /></label>
                            <select v-model="state.user.status.value" required class="form-control rounded border px-3 py-2">
                                <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Role</label>
                            <div v-if="canManageRoles" class="flex flex-wrap gap-2 rounded border p-3">
                                <label v-for="role in getRoles" :key="role.value" class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="state.user.roles" type="checkbox" :value="role.value" />
                                    {{ role.label }}
                                </label>
                            </div>
                            <div v-else class="flex min-h-11 flex-wrap items-center gap-2 rounded border bg-gray-50 p-3">
                                <span
                                    v-for="role in state.user.roles"
                                    :key="role"
                                    class="rounded bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-700"
                                >
                                    {{ role }}
                                </span>
                                <span v-if="!state.user.roles?.length" class="text-sm text-gray-500">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>
