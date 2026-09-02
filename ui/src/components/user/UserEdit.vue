<script setup>
import { onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonRouterLink from "../layout/page/ButtonLink.vue";
import buttonSubmitComponent from "../layout/page/ButtonSubmit.vue";
import { storeToRefs } from "pinia";
import { useAdminUsers } from "../../store/StoreAdminUsers";
import useErrors from "../../store/StoreErrors";
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";
import TitleDatalists from "../forms/TitleDatalists.vue";
import useUnsavedChanges from "../../models/useUnsavedChanges";

// store.user je reaktívny aj mutovateľný (Pinia proxy); gettery cez storeToRefs.
const store = useAdminUsers();
const {
    getRoles,
    getStatuses,
    getLocales,
    getPortalPermissions,
    canManageRoles,
    canManagePermissions,
    isPortalUser,
} = storeToRefs(store);
const { fetchUser, updateUser } = store;

const router = useRouter();
const { params: { userId } } = useRoute();
const { getFieldErrors } = storeToRefs(useErrors());

const fe = (field) => {
    const e = getFieldErrors.value[field];
    return Array.isArray(e) ? e[0] : (e ?? '');
};

const { setOriginalData, markAsSaved } = useUnsavedChanges(() => store.user);

onMounted(async () => {
    await fetchUser(userId);
    setOriginalData();
});

const saveUser = async () => {
    const saved = await updateUser();
    if (saved) {
        markAsSaved();
        router.push({ name: "users.show", params: { userId: store.user.id } });
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

                <form v-if="store.user?.id" @submit.prevent="saveUser" class="rounded bg-white px-8 pt-6 pb-8 shadow-md">


                    <TitleDatalists />

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Meno vrátane titulov pred a za menom -->
                        <div class="grid grid-cols-2 gap-4 md:col-span-2 md:grid-cols-12">
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-bold text-gray-700">Titul pred</label>
                                <FormInput
                                    v-model="store.user.prefix"
                                    placeholder="Ing."
                                    list="title-prefix-options"
                                    :error="fe('prefix')"
                                    field-key="prefix"
                                />
                            </div>

                            <div class="md:col-span-4">
                                <label class="mb-2 block text-sm font-bold text-gray-700">Meno <RequiredMark /></label>
                                <FormInput v-model="store.user.firstName" placeholder="Meno" required :error="fe('firstName')" field-key="firstName" />
                            </div>

                            <div class="md:col-span-4">
                                <label class="mb-2 block text-sm font-bold text-gray-700">Priezvisko</label>
                                <FormInput v-model="store.user.lastName" placeholder="Priezvisko" :error="fe('lastName')" field-key="lastName" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-bold text-gray-700">Titul za</label>
                                <FormInput
                                    v-model="store.user.postfix"
                                    placeholder="PhD."
                                    list="title-postfix-options"
                                    :error="fe('postfix')"
                                    field-key="postfix"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Používateľské meno</label>
                            <FormInput v-model="store.user.username" placeholder="Používateľské meno" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Email <RequiredMark /></label>
                            <FormInput v-model="store.user.email" type="email" placeholder="Email" required :error="fe('email')" field-key="email" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Telefón</label>
                            <FormInput v-model="store.user.phone" placeholder="Telefón" :error="fe('phone')" field-key="phone" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Funkcia</label>
                            <FormInput
                                v-model="store.user.position"
                                placeholder="napr. konateľ, referent nákupu"
                                :error="fe('position')"
                                field-key="position"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Jazyk komunikácie</label>
                            <select
                                v-model="store.user.locale"
                                class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                            >
                                <option v-for="locale in getLocales" :key="locale.value" :value="locale.value">
                                    {{ locale.label }}
                                </option>
                            </select>
                            <p v-if="fe('locale')" class="mt-1 text-xs font-semibold text-red-600">{{ fe('locale') }}</p>
                            <p v-else class="mt-1 text-xs text-gray-500">Jazyk e-mailov a notifikácií.</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Status <RequiredMark /></label>
                            <select
                                v-model="store.user.status.value"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                            >
                                <option v-for="status in getStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                            <p v-if="fe('status')" class="mt-1 text-xs font-semibold text-red-600">{{ fe('status') }}</p>
                        </div>

                        <!-- Aktívny účet — samostatný vypínač prihlásenia -->
                        <div>
                            <label class="mb-2 block text-sm font-bold text-gray-700">Prístup</label>
                            <label class="inline-flex w-full items-center gap-2 rounded border border-gray-300 px-3 py-2.5 text-sm">
                                <input v-model="store.user.active" type="checkbox" class="rounded" />
                                Aktívny účet (môže sa prihlásiť)
                            </label>
                        </div>

                        <!-- Role — len super-admin -->
                        <div v-if="canManageRoles && getRoles.length">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Role</label>
                            <div class="flex flex-wrap gap-2 rounded border p-3">
                                <label v-for="role in getRoles" :key="role.value" class="inline-flex items-center gap-2 text-sm">
                                    <input v-model="store.user.roles" type="checkbox" :value="role.value" />
                                    {{ role.label }}
                                </label>
                            </div>
                        </div>
                        <div v-else-if="store.user.roles?.length">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Role</label>
                            <div class="flex min-h-11 flex-wrap items-center gap-2 rounded border bg-gray-50 p-3">
                                <span
                                    v-for="role in store.user.roles"
                                    :key="role"
                                    class="rounded bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-700"
                                >
                                    {{ role }}
                                </span>
                            </div>
                        </div>

                        <!-- Práva — admin/super-admin pre portálových userov -->
                        <div v-if="canManagePermissions && isPortalUser && getPortalPermissions.length" class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Práva na objednávky</label>
                            <div class="rounded border p-3">
                                <p class="mb-3 text-xs text-gray-500">
                                    Bez pridelených práv má používateľ základný prístup (zobrazenie a vytváranie objednávok).
                                    Po pridelení aspoň jedného práva sa uplatnia len zvolené oprávnenia.
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="perm in getPortalPermissions"
                                        :key="perm.value"
                                        class="inline-flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="store.user.permissions"
                                            type="checkbox"
                                            :value="perm.value"
                                            class="rounded"
                                        />
                                        {{ perm.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Interná poznámka — používateľ ju nikdy nevidí -->
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-bold text-gray-700">Interná poznámka</label>
                            <textarea
                                v-model="store.user.note"
                                rows="3"
                                maxlength="2000"
                                placeholder="Poznámka pre kolegov — používateľovi sa neposiela."
                                class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                            ></textarea>
                            <p v-if="fe('note')" class="mt-1 text-xs font-semibold text-red-600">{{ fe('note') }}</p>
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
