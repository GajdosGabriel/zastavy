<script setup>
import headerImage from "../../assets/images/header.jpg";
import gabrielImage from "../../assets/images/gabriel.jpg";
</script>

<template>
    <section class="bg-slate-200 px-3">
        <div class="container mx-auto border-x border-b border-slate-300 bg-white shadow-sm">
            <div class="grid items-center gap-3 px-3 py-3 md:grid-cols-12 md:px-6">
                <div class="md:col-span-8">
                    <img
                        :src="headerImage"
                        alt="Zástavy a vlajky"
                        class="h-36 w-full rounded-md object-cover object-center md:h-48"
                    />
                </div>

                <aside class="hidden md:col-span-4 md:block">
                    <div class="flex items-center gap-5 rounded-md border border-slate-200 bg-slate-50 px-6 py-5">
                        <img
                            :src="gabrielImage"
                            alt="Kontakt"
                            class="h-32 w-32 rounded-md object-cover shadow-sm"
                        />
                        <div>
                            <div class="text-sm font-semibold uppercase text-slate-500">Kontakt</div>
                            <strong class="mt-1 block text-2xl text-slate-900">Tel.: 0905 320 616</strong>
                            <div class="mt-1 text-base text-slate-600">Zástavy, vlajky a symboly</div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</template>
