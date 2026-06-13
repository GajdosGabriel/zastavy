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

            <section class="podmienky-page px-4 pb-10 sm:px-7">
                <div class="podmienky-shell overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">

                    <div class="bg-blue-900 px-5 py-8 text-white sm:px-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                            Zástavy a vlajky
                        </p>
                        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">
                            Obchodné podmienky
                        </h1>
                        <p class="mt-3 max-w-3xl text-base leading-7 text-blue-50">
                            Pre nákup prostredníctvom obchodného systému
                            <a href="http://www.zastavy-vlajky.sk" class="underline underline-offset-4">
                                www.zastavy-vlajky.sk
                            </a>
                        </p>
                    </div>

                    <div class="space-y-8 p-5 text-slate-700 leading-7 sm:p-8">

                        <section>
                            <p>
                                Tieto všeobecné obchodné podmienky upravujú hlavné zásady a princípy obchodných
                                vzťahov medzi predávajúcim, ktorý je prevádzkovateľom obchodného systému
                                www.zastavy-vlajky.sk a zákazníkmi. Všetky ustanovenia tu uvedené sú platné,
                                pokiaľ nie je písomnou zmluvnou formou s kupujúcim uvedené inak. Kontrolným
                                orgánom pre internetový predaj je Slovenská obchodná inšpekcia.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Definícia pojmov</h2>
                            <p>
                                Pokiaľ nie je z kontextu zrejmé niečo iné, majú v týchto všeobecných obchodných
                                podmienkach a ich prílohách nasledovné výrazy tento význam:
                            </p>
                            <ul class="mt-3 space-y-2 pl-5 list-disc">
                                <li>
                                    <strong class="text-slate-900">Predávajúci</strong> – Gajdoš Gabriel - Reprezent,
                                    Sekčovská 19, 086 41 Raslavice, IČO: 14 287 315. Živnostenský list vydaný dňa
                                    19.01.1998 pod sp. č. Žo – 98/00061/000, reg. č. 36/98.
                                </li>
                                <li>
                                    <strong class="text-slate-900">Kupujúci</strong> – fyzická alebo právnická osoba,
                                    ktorá vstúpila do obchodného vzťahu s predávajúcim.
                                </li>
                                <li>
                                    <strong class="text-slate-900">Tovar</strong> – produkty, ktoré sa nachádzajú
                                    v ponuke obchodného systému www.zastavy-vlajky.sk.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Objednávka</h2>
                            <div class="space-y-4">
                                <p>
                                    Kupujúci objednáva výrobky a služby predávajúceho prostredníctvom obchodného
                                    systému www.zastavy-vlajky.sk. Kúpna zmluva sa uzatvára na základe objednávky
                                    kupujúceho. Každá objednávka musí obsahovať: meno kupujúceho, kontaktnú osobu,
                                    poštovú a fakturačnú adresu, telefónne číslo, IČO, DIČ, objednávací kód tovaru,
                                    presný popis tovaru, počet kusov, dátum vystavenia objednávky, dobu platnosti
                                    objednávky, spôsob úhrady a spôsob prevzatia tovaru. Predávajúci objednávky
                                    vybavuje v poradí, v akom prichádzajú. Vystavením objednávky sa kupujúci zaväzuje
                                    za objednaný tovar zaplatiť kúpnu cenu.
                                </p>
                                <p>
                                    Nesplnenie záväzkov vyplývajúcich z objednávky najneskôr do 30 dní od jej
                                    vystavenia sa považuje za porušenie týchto obchodných podmienok. Predávajúci
                                    potvrdí objednávku telefonicky alebo e-mailom. Bežná dodacia doba je od 3 do
                                    14 dní. Ak predávajúci nie je schopný vybaviť objednávku do 30 dní, upozorní
                                    na to kupujúceho a dohodne ďalší postup.
                                </p>
                                <p>
                                    Kupujúci má právo stornovať objednávku bez poplatku iba v deň jej vystavenia
                                    a v prípade, že produkty ešte neboli fakturované. Stornovanie je možné
                                    telefonicky, e-mailom alebo prostredníctvom obchodného systému. Kupujúci môže
                                    objednávku meniť do 24 hodín od jej vystavenia, ak produkty ešte neboli
                                    fakturované. Predávajúci má právo odstúpiť od kúpnej zmluvy, ak objednaný
                                    tovar nie je možné dodať z dôvodu nedostatku na sklade.
                                </p>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Povinnosti predávajúceho</h2>
                            <p class="mb-3">Zákazníkom sa zaväzujeme dodať:</p>
                            <ul class="space-y-2 pl-5 list-disc">
                                <li>Druh a množstvo tovaru v kúpnej cene platnej v deň odoslania objednávky.</li>
                                <li>Tovar, ktorý vyhovuje normám, predpisom a nariadeniam platným na území SR.</li>
                                <li>Tovar vhodne zabalený, aby nedošlo k jeho poškodeniu počas prepravy.</li>
                            </ul>
                            <p class="mt-3">Nezodpovedáme za:</p>
                            <ul class="mt-2 space-y-2 pl-5 list-disc">
                                <li>Oneskorené dodanie tovaru zavinené poštou alebo nesprávne udanou adresou prijímateľa.</li>
                                <li>Prípadné nedodanie tovaru z dôvodu obmedzenia distribučných práv výrobcu.</li>
                                <li>Výber tovaru zákazníkom (napr. veľkosť, materiál).</li>
                            </ul>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Povinnosti kupujúceho</h2>
                            <p class="mb-3">Kupujúci sa zaväzuje:</p>
                            <ul class="space-y-2 pl-5 list-disc">
                                <li>
                                    Objednaný tovar prevziať, skontrolovať neporušenosť obalov a prípadné závady
                                    bezodkladne oznámiť predávajúcemu.
                                </li>
                                <li>
                                    Zaplatiť za tovar kúpnu cenu v zmysle platobných podmienok platných v deň
                                    odoslania objednávky.
                                </li>
                            </ul>
                            <p class="mt-4">
                                V prípade neprevzatia tovaru si predávajúci vyhradzuje právo uplatniť náhradu
                                vzniknutej škody. Pri opakovanom neprevzatí zásielky na dobierku môže predávajúci
                                požadovať platbu vopred.
                            </p>

                            <h3 class="mb-2 mt-6 text-lg font-semibold text-slate-900">Platobné podmienky</h3>
                            <p class="mb-3">Kupujúci vykonáva platbu v EUR niektorým z nasledujúcich spôsobov:</p>

                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                    <h4 class="font-semibold text-slate-900">Pred prevzatím tovaru</h4>
                                    <p class="mt-2 text-sm">
                                        Prevodom alebo vkladom na účet. Platba musí byť preukázateľne pripísaná
                                        na bankový účet predávajúceho.
                                    </p>
                                </div>
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                                    <h4 class="font-semibold text-slate-900">Pri prevzatí tovaru</h4>
                                    <p class="mt-2 text-sm">
                                        Dobierka (max. do 1 000 EUR) alebo v hotovosti pri osobnom odbere.
                                        Doba splatnosti faktúry je zvyčajne 14 dní.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Ceny</h2>
                            <p>
                                Ceny uvedené na stránkach www.zastavy-vlajky.sk platia výhradne pre nákup
                                prostredníctvom obchodného systému. Súčasťou ceny nie sú prepravné náklady ani
                                žiadne iné náklady, pokiaľ to nie je uvedené v popise produktu alebo v objednávke.
                                Predávajúci si vyhradzuje právo zmeny cien tovaru.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Odber a dodávka tovaru</h2>
                            <div class="space-y-4">
                                <p>
                                    Pri preberaní tovaru je kupujúci povinný skontrolovať fyzickú neporušenosť
                                    a kompletnosť zásielky. Pokiaľ je zásielka viditeľne poškodená, kupujúci je
                                    povinný bez prevzatia zásielky ihneď kontaktovať predávajúceho. Akékoľvek
                                    neskoršie reklamácie na množstvo a fyzické porušenie tovaru nebudú akceptované.
                                </p>
                                <p>
                                    Objednávky sa realizujú okamžite, ak je tovar na sklade. Prepravné náklady nie
                                    sú zahrnuté v cene tovaru. Kupujúci nadobúda vlastnícke práva k tovaru až
                                    uhradením celej kúpnej ceny. Nebezpečenstvo škody na výrobkoch prechádza na
                                    kupujúceho momentom prevzatia tovaru.
                                </p>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Záruka a vrátenie tovaru</h2>
                            <div class="space-y-4">
                                <p>
                                    Dokladom o predaji je faktúra priložená ku každej zásielke, ktorá slúži zároveň
                                    ako dodací a záručný list. Záručná doba pre spotrebiteľské zmluvy je 24 mesiacov.
                                </p>
                                <p>
                                    Zákazník má právo odstúpiť od zmluvy do 7 pracovných dní odo dňa doručenia
                                    tovaru bez udania dôvodu, za predpokladu, že tovar vráti nepoužitý, čistý,
                                    s originálnou visačkou a v pôvodnom balení. Pri vrátení tovaru bude zákazníkovi
                                    vrátená iba suma zaplatená za tovar; poštovné vrátené nebude.
                                </p>
                                <p>
                                    Oprávnená záručná reklamácia je bezplatná. Pri neoprávnenej reklamácii je
                                    kupujúci povinný uhradiť náklady vzniknuté jej riešením. Oprávnenie na záruku
                                    zaniká pri bežnom opotrebení, nesprávnom použití alebo nesprávnom skladovaní.
                                </p>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Ochrana osobných údajov</h2>
                            <p>
                                Bezpečnosť poskytnutých informácií je pre predávajúceho prvoradá. Všetky dáta
                                poskytnuté kupujúcim budú použité výlučne na účely uskutočneného predaja produktov.
                                Informácie o komunikácii medzi predávajúcim a zákazníkom nebudú nikdy poskytnuté
                                tretej strane. Používaním tohto internetového obchodu súhlasí kupujúci so
                                zhromažďovaním a používaním informácií za vyššie stanovených podmienok.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">
                                Záverečné ustanovenia
                            </h2>
                            <p>
                                Kupujúci prehlasuje, že sa pred vyplnením objednávky oboznámil s týmito všeobecnými
                                obchodnými podmienkami a súhlasí s nimi. Predávajúci nezodpovedá kupujúcemu za ušlý
                                zisk, stratu príležitosti ani iné nepriame straty. Právne vzťahy tu výslovne
                                neupravené sa riadia príslušnými ustanoveniami Obchodného alebo Občianskeho
                                zákonníka. Predávajúci a kupujúci sa dohodli, že plne uznávajú elektronickú formu
                                komunikácie ako platnú a záväznú pre obe zmluvné strany.
                            </p>
                        </section>

                    </div>
                </div>
            </section>

        </template>
    </BaseLayout>
</template>

<style scoped>
.podmienky-page {
    grid-column: 1 / -1;
    width: 100%;
    min-width: 0;
}

.podmienky-shell {
    width: 100%;
}
</style>
