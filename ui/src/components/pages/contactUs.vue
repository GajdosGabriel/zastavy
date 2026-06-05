<script setup lang="ts">
import BaseLayout from '../layout/BaseLayout.vue';

</script>

<template>

      <BaseLayout>
            <!-- Content for the default slot -->
            <template #main>
                  <h1 class="page-heading">Kontakt</h1>

                  <div class="page-body col-span-9">
                        <div class="">

                              <div class="md:flex justify-between">
                                    <div>
                                          <h4 class="font-semibold text-lg underline">Prevádzka</h4>
                                          <p class="font-semibold">Gajdoš Gabriel-Reprezent</p>
                                          <p>Sslatinská 14, Bratislava 921 07</p>
                                          <p>email: obchod@zastavy-vlajky.sk</p>
                                          <p>Mob. 0905 320 616</p>
                                    </div>
                                    <div>
                                          <h4 class="font-semibold text-lg underline">
                                                Sídlo podnikania:
                                          </h4>
                                          <p class="font-semibold">Gajdoš Gabriel-Reprezent</p>
                                          <p>Sekčovska 19, 086 41 Raslavice</p>
                                          <p>IČO: 14 287 315</p>
                                          <p>DIC: SK1020747398</p>
                                    </div>
                              </div>

                              <p>
                                    Živnostenský list vydaný dňa 19.01.1998 pod. sp.č.: Žo
                                    98/00061/000 reg. č. 36/98
                              </p>

                              <div class="mt-6">
                                    <h2 class="text-center font-semibold text-2xl">
                                          Doprava a platba
                                    </h2>
                                    <p>
                                          Dopravu tovaru k vám, volíme najlacnejším spôsobom.
                                          Najlepšou variantou je Slovenská pošta, ktorá balík doručuje
                                          do 2-3 pracovných dní priamo k vám. Do balíka prikladáme
                                          faktúru, so platnosťou 14 dní.
                                    </p>
                                    <p>
                                          Tovar môže byť odoslaný aj na dobierku, ale tento spôsob
                                          zbytočne zvyšuje náklady. Doposiaľ nám každý odberateľ
                                          zaplatil, a preto obecným úradom a školám posielame tovar
                                          automaticky na faktúru. Objednávky prijaté do 10 hod. sa
                                          expedujú v ten istý deň. Celý sortiment je k dispozícií na
                                          sklade.
                                    </p>

                                    <h4>Vrátenie tovaru</h4>

                                    <strong>Tovar môžete vrátiť na adresu: </strong><br />

                                    Gajdoš Gabriel - Reprezent<br />

                                    Slatinská 14<br />

                                    921 07 Bratislava
                              </div>
                        </div>
                  </div>
            </template>

      </BaseLayout>

</template>
