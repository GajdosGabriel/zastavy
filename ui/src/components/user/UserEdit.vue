<script setup>
import { onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import validationBar from "../bars/ValidationBar.vue";
import useAdminUsers from "../../store/StoreAdminUsers";
import RequiredMark from "../forms/RequiredMark.vue";

const { state, fetchUser, updateUser, getRoles, getStatuses } = useAdminUsers();
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
                            <input v-model="state.user.firstName" type="text" required class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Priezvisko</label>
                            <input v-model="state.user.lastName" type="text" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Používateľské meno</label>
                            <input v-model="state.user.username" type="text" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Email <RequiredMark /></label>
                            <input v-model="state.user.email" type="email" required class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Telefón</label>
                            <input v-model="state.user.phone" type="text" class="form-control rounded border px-3 py-2" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Customer ID</label>
                            <input v-model="state.user.customer_id" type="number" class="form-control rounded border px-3 py-2" />
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
                            <div class="flex flex-wrap gap-2 rounded border p-3">
                                <label v-for="role in getRoles" :key="role.value" class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="state.user.roles" type="checkbox" :value="role.value" />
                                    {{ role.label }}
                                </label>
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
