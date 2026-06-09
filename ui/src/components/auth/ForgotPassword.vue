<script setup>
import { ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useUser from "../../store/StoreUsers";
import SpinnerButton from "../icons/spinnerButton.vue";
import loadingStore from "../../store/StoreLoading";

const { forgotPassword } = useUser();

const email = ref("");
const successMessage = ref("");
const sent = ref(false);

const onSubmit = async () => {
    const result = await forgotPassword(email.value);
    if (result.success) {
        successMessage.value = result.message;
        sent.value = true;
    }
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="col-span-12 flex min-h-[calc(100vh-14rem)] items-center justify-center px-4 py-10">
                <section class="w-full max-w-md rounded bg-white shadow-lg ring-1 ring-slate-200">
                    <div class="border-b border-slate-200 px-7 py-6">
                        <h1 class="text-2xl font-semibold text-slate-900">Zabudnuté heslo</h1>
                        <p class="mt-1 text-sm text-slate-500">Zadajte Váš email a pošleme Vám odkaz na obnovenie hesla.</p>
                    </div>

                    <div class="px-7 py-6">
                        <div v-if="sent" class="rounded bg-green-50 border border-green-200 px-4 py-4 text-sm text-green-800">
                            {{ successMessage }}
                        </div>

                        <form v-else @submit.prevent="onSubmit" class="space-y-5">
                            <div>
                                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                                <input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="loadingStore.isLoading"
                                class="flex w-full items-center justify-center gap-2 rounded bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:bg-blue-500 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <SpinnerButton v-if="loadingStore.isLoading" />
                                Odoslať odkaz
                            </button>

                            <div class="border-t border-slate-200 pt-4 text-center text-sm text-slate-600">
                                <router-link :to="{ name: 'public.login.index' }" class="font-semibold text-blue-700 hover:text-blue-900">
                                    Späť na prihlásenie
                                </router-link>
                            </div>
                        </form>

                        <div v-if="sent" class="mt-5 text-center text-sm text-slate-600">
                            <router-link :to="{ name: 'public.login.index' }" class="font-semibold text-blue-700 hover:text-blue-900">
                                Späť na prihlásenie
                            </router-link>
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </BaseLayout>
</template>
