<script setup>
import { reactive } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useUser from "../../store/StoreUsers";
import router from "../../router";
import RequiredMark from "../forms/RequiredMark.vue";


const { login } = useUser();

const form = reactive({
    email: "",
    password: "",
    // device_name: "browser",
});

const onClickForm = async () => {
    const isLoggedIn = await login(form);

    if (isLoggedIn) {
        router.push({ name: "dashboard.index" });
    }
};
</script>

<template>
    <BaseLayout>

        <template #main>
            <!-- <h1 class="page-heading">Zákazníci</h1> -->

            <div class="page-body col-span-12">

                <div class="min-h-screen md:w-1/3 mx-auto pt-6 sm:pt-0 bg-gray-100">
                    <div class="text-center text-lg font-semibold">
                        Prihlásenie
                    </div>

                    <!-- Session Status -->
                    <!-- <x-auth-session-status class="mb-4" :status="session('status')" /> -->

                    <!-- Validation Errors -->
                    <!-- <x-auth-validation-errors class="mb-4" :errors="$errors" /> -->

                    <form @submit.prevent="onClickForm" action="/login" class="bg-gray-300 p-6">
                        <!-- Email Address -->
                        <div>
                            <label for="email" class="block font-medium text-sm text-gray-700">Email <RequiredMark /></label>

                            <input id="email"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="email" v-model="form.email" required />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <label for="password" class="block font-medium text-sm text-gray-700">Heslo <RequiredMark /></label>

                            <input id="password"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="password" v-model="form.password" required autocomplete="current-password" />
                        </div>

                        <!-- Remember Me -->
                        <div class="block mt-4">
                            <label for="remember_me" class="inline-flex items-center">
                                <input id="remember_me" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    name="remember" checked />
                                <span class="ml-2 text-sm text-gray-600"> Uložiť</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end mt-4">

                            <router-link :to="{ name: 'public.register.index' }"
                                class="underline text-sm text-gray-600 hover:text-gray-900">
                                Registrácia
                            </router-link>

                            <button
                                class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Prihlásiť
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
