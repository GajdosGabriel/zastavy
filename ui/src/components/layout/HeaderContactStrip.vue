<script setup>
import headerImage from "../../assets/images/header.jpg";
import gabrielImage from "../../assets/images/gabriel.jpg";
</script>

<template>
    <section class="mb-6 bg-slate-200 px-3">
        <div class="container mx-auto border-x border-b border-slate-300 bg-white shadow-sm">
            <div class="grid items-center gap-6 px-3 py-6 md:px-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <img
                        :src="headerImage"
                        width="700" height="180"
                        alt="Zástavy-vlajky.sk"
                        class="aspect-[700/180] h-auto w-full rounded-md object-contain object-left"
                    />
                </div>

                <aside class="hidden md:block lg:col-span-4">
                    <div class="flex items-center gap-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-4 xl:gap-5 xl:px-6 xl:py-5">
                        <img
                            :src="gabrielImage"
                            width="128" height="128" loading="lazy"
                            alt="Gabriel Gajdoš, kontaktná osoba"
                            class="h-24 w-24 shrink-0 rounded-md xl:h-32 xl:w-32 object-cover shadow-sm"
                        />
                        <div class="min-w-0">
                            <div class="text-sm font-semibold uppercase text-slate-500">Kontakt</div>
                            <strong class="mt-1 block whitespace-nowrap text-xl text-slate-900 xl:text-2xl">Tel.: 0905 320 616</strong>
                            <div class="mt-1 text-base text-slate-600">Zástavy, vlajky a symboly</div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</template>
