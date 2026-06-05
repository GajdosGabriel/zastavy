<script setup>
import { reactive } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useUser from "../../store/StoreUsers";
import router from "../../router";
import ErrorPanel from "../plugins/ErrorPanel.vue";


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
            <!-- <h1 class="page-heading">Zákazníci</h1> -->

            <div class="page-body col-span-12">

                <div class="min-h-screen md:w-1/3 mx-auto pt-6 sm:pt-0 bg-gray-100">
                    <!-- <ErrorPanel /> -->
                    <div class="text-center text-lg font-semibold">
                        Registrácia
                    </div>

                    <form @submit.prevent="onClickForm" class="bg-gray-300 p-6">

                        <!-- Meno -->
                        <div class="pb-4">
                            <label for="firstName" class="block font-medium text-sm text-gray-700">Meno</label>

                            <input id="firstName"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="text" v-model="form.firstName" required placeholder="meno" />
                        </div>

                        <!-- Priezvisko Name -->
                        <div class="pb-4">
                            <label for="lastName" class="block font-medium text-sm text-gray-700">Priezvisko</label>

                            <input id="lastName"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="text" v-model="form.lastName" required placeholder="priezvisko" />
                        </div>

                        <!-- Email Address -->
                        <div class="pb-4">
                            <label for="email" class="block font-medium text-sm text-gray-700">Email</label>

                            <input id="email"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="email" v-model="form.email" required placeholder="email" />
                        </div>

                        <!-- Password -->
                        <div class="pb-4">
                            <label for="password" class="block font-medium text-sm text-gray-700">Heslo</label>

                            <input id="password"
                                class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                type="password" v-model="form.password" required autocomplete="current-password"
                                placeholder="heslo" />
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
                            <router-link :to="{ name: 'public.login.index' }"
                                class="underline text-sm text-gray-600 hover:text-gray-900">
                                Prihlásenie
                            </router-link>

                            <button
                                class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Registrácia
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>
