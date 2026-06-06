<script setup>
import { reactive } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useUser from "../../store/StoreUsers";
import RequiredMark from "../forms/RequiredMark.vue";


const { register } = useUser();

const form = reactive({
    firstName: "",
    lastName: "",
    email: "",
    password: "",
    device_name: "browser",
});

const onClickForm = () => {
    register(form);
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="col-span-12 flex min-h-[calc(100vh-14rem)] items-center justify-center px-4 py-10">
                <section class="w-full max-w-xl rounded bg-white shadow-lg ring-1 ring-slate-200">
                    <div class="border-b border-slate-200 px-7 py-6">
                        <h1 class="text-2xl font-semibold text-slate-900">Registrácia</h1>
                        <p class="mt-1 text-sm text-slate-500">Vytvorenie účtu pre objednávky a správu údajov.</p>
                    </div>

                    <form @submit.prevent="onClickForm" class="space-y-5 px-7 py-6">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="firstName" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Meno <RequiredMark />
                                </label>
                                <input
                                    id="firstName"
                                    v-model="form.firstName"
                                    class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    type="text"
                                    required
                                    autocomplete="given-name"
                                />
                            </div>

                            <div>
                                <label for="lastName" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Priezvisko <RequiredMark />
                                </label>
                                <input
                                    id="lastName"
                                    v-model="form.lastName"
                                    class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    type="text"
                                    required
                                    autocomplete="family-name"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">
                                Email <RequiredMark />
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                type="email"
                                required
                                autocomplete="email"
                            />
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">
                                Heslo <RequiredMark />
                            </label>
                            <input
                                id="password"
                                v-model="form.password"
                                class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </div>

                        <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input
                                id="remember_me"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                name="remember"
                                checked
                            />
                            Uložiť prihlásenie
                        </label>

                        <button
                            class="flex w-full items-center justify-center rounded bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            type="submit"
                        >
                            Registrovať
                        </button>

                        <div class="border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
                            Už máte účet?
                            <router-link :to="{ name: 'public.login.index' }" class="font-semibold text-blue-700 hover:text-blue-900">
                                Prihlásenie
                            </router-link>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </BaseLayout>
</template>
