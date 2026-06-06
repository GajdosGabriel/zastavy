<script setup lang="ts">
import BaseLayout from '../layout/BaseLayout.vue';
</script>

<template>
      <BaseLayout>
            <template #main>
                  <div class="col-span-12">
                        <div class="container mx-auto border-x border-b border-slate-300 bg-white shadow-sm">
                              <div class="grid items-center gap-5 px-3 py-4 md:grid-cols-12 md:px-6">
                                    <div class="md:col-span-8">
                                          <img
                                                src="/src/assets/images/header.jpg"
                                                alt="Zástavy a vlajky"
                                                class="h-36 w-full rounded-md object-cover object-center md:h-48"
                                          >
                                    </div>
                                    <aside class="hidden md:col-span-4 md:block">
                                          <div class="ml-auto flex max-w-sm items-center gap-4 rounded-md border border-slate-200 bg-slate-50 px-5 py-4">
                                                <img
                                                      src="/src/assets/images/gabriel.jpg"
                                                      alt="Kontakt"
                                                      class="h-24 w-24 rounded-md object-cover shadow-sm"
                                                >
                                                <div>
                                                      <div class="text-xs font-semibold uppercase text-slate-500">Kontakt</div>
                                                      <strong class="mt-1 block text-lg text-slate-900">Tel.: 0905 320 616</strong>
                                                      <div class="mt-1 text-sm text-slate-600">Zástavy, vlajky a symboly</div>
                                                </div>
                                          </div>
                                    </aside>
                              </div>
                        </div>
                  </div>

                  <section class="contact-page px-4 pb-10 sm:px-7">
                        <div class="contact-shell overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                              <div class="bg-blue-900 px-5 py-8 text-white sm:px-8">
                                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                                          Kontaktné údaje
                                    </p>
                                    <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">
                                          Kontakt
                                    </h1>
                                    <p class="mt-3 max-w-3xl text-base leading-7 text-blue-50">
                                          Sme tu pre obce, školy aj firmy. Poradíme s výberom vlajok,
                                          zástav, štátnych symbolov a vybavíme objednávku od skladových
                                          zásob po odoslanie.
                                    </p>
                              </div>

                              <div class="space-y-6 p-5 sm:p-8">
                                    <div>
                                          <div class="grid gap-5 lg:grid-cols-2">
                                                <article class="rounded-md border border-slate-200 bg-slate-50 p-5">
                                                      <p class="text-sm font-semibold uppercase text-blue-800">
                                                            Prevádzka
                                                      </p>
                                                      <h2 class="mt-3 text-xl font-semibold text-slate-900">
                                                            Gajdoš Gabriel-Reprezent
                                                      </h2>
                                                      <div class="mt-4 space-y-2 text-slate-700">
                                                            <p>Slatinská 14, 921 07 Bratislava</p>
                                                            <p>
                                                                  <span class="font-semibold text-slate-900">Email:</span>
                                                                  <a class="text-blue-800 underline-offset-4 hover:underline"
                                                                        href="mailto:obchod@zastavy-vlajky.sk">
                                                                        obchod@zastavy-vlajky.sk
                                                                  </a>
                                                            </p>
                                                            <p>
                                                                  <span class="font-semibold text-slate-900">Mobil:</span>
                                                                  <a class="text-blue-800 underline-offset-4 hover:underline"
                                                                        href="tel:+421905320616">
                                                                        0905 320 616
                                                                  </a>
                                                            </p>
                                                      </div>
                                                </article>

                                                <article class="rounded-md border border-slate-200 bg-white p-5">
                                                      <p class="text-sm font-semibold uppercase text-blue-800">
                                                            Sídlo podnikania
                                                      </p>
                                                      <h2 class="mt-3 text-xl font-semibold text-slate-900">
                                                            Gajdoš Gabriel-Reprezent
                                                      </h2>
                                                      <div class="mt-4 space-y-2 text-slate-700">
                                                            <p>Sekčovská 19, 086 41 Raslavice</p>
                                                            <p>
                                                                  <span class="font-semibold text-slate-900">IČO:</span>
                                                                  14 287 315
                                                            </p>
                                                            <p>
                                                                  <span class="font-semibold text-slate-900">DIČ:</span>
                                                                  SK1020747398
                                                            </p>
                                                      </div>
                                                </article>
                                          </div>

                                          <div class="mt-6 rounded-md border border-slate-200 bg-white p-5">
                                                <h2 class="text-xl font-semibold text-slate-900">
                                                      Doprava a platba
                                                </h2>
                                                <div class="mt-4 space-y-4 leading-7 text-slate-700">
                                                      <p>
                                                            Dopravu tovaru k vám volíme najlacnejším spôsobom.
                                                            Najlepšou variantou je Slovenská pošta, ktorá balík
                                                            doručuje do 2-3 pracovných dní priamo k vám. Do balíka
                                                            prikladáme faktúru so splatnosťou 14 dní.
                                                      </p>
                                                      <p>
                                                            Tovar môže byť odoslaný aj na dobierku, ale tento spôsob
                                                            zbytočne zvyšuje náklady. Obecným úradom a školám posielame
                                                            tovar automaticky na faktúru. Objednávky prijaté do 10 hod.
                                                            expedujeme v ten istý deň, ak je sortiment skladom.
                                                      </p>
                                                </div>
                                          </div>
                                    </div>

                                    <div class="grid gap-5 lg:grid-cols-2">
                                          <div class="rounded-md border border-slate-200 bg-white p-5">
                                                <h2 class="text-xl font-semibold text-slate-900">
                                                      Vrátenie tovaru
                                                </h2>
                                                <p class="mt-3 text-slate-700">
                                                      Tovar môžete vrátiť na adresu:
                                                </p>
                                                <address class="mt-4 not-italic leading-7 text-slate-700">
                                                      <strong class="block text-slate-900">
                                                            Gajdoš Gabriel - Reprezent
                                                      </strong>
                                                      Slatinská 14<br />
                                                      921 07 Bratislava
                                                </address>
                                          </div>

                                          <div class="rounded-md border border-blue-100 bg-blue-50 p-5 text-blue-950">
                                                <h2 class="text-lg font-semibold">
                                                      Živnostenské oprávnenie
                                                </h2>
                                                <p class="mt-3 leading-7">
                                                      Živnostenský list vydaný dňa 19.01.1998 pod sp. č.
                                                      Žo 98/00061/000, reg. č. 36/98.
                                                </p>
                                          </div>
                                    </div>
                              </div>
                        </div>
                  </section>
            </template>
      </BaseLayout>
</template>

<style scoped>
.contact-page {
      grid-column: 1 / -1;
      width: 100%;
      min-width: 0;
}

.contact-shell {
      width: 100%;
}
</style>
