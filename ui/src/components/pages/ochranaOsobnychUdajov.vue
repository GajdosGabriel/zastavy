<script setup>
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

            <section class="gdpr-page px-4 pb-10 sm:px-7">
                <div class="gdpr-shell overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">

                    <div class="bg-blue-900 px-5 py-8 text-white sm:px-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-100">
                            Zástavy a vlajky
                        </p>
                        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">
                            Ochrana osobných údajov
                        </h1>
                        <p class="mt-3 max-w-3xl text-base leading-7 text-blue-50">
                            Podmienky spracúvania osobných údajov na eshope www.zastavy-vlajky.sk.
                            Ak ste mojim zákazníkom, odberateľom noviniek alebo návštevníkom webu,
                            zverujete mi svoje osobné údaje. Zodpovedám za ich ochranu a zabezpečenie.
                        </p>
                    </div>

                    <div class="space-y-8 p-5 text-slate-700 leading-7 sm:p-8">

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Kto je správca?</h2>
                            <p>
                                Správcom osobných údajov je firma Gajdoš Gabriel – Reprezent, Slatinská 14,
                                921 07 Bratislava, IČO: 14 287 315 (ďalej len „prevádzkovateľ"), ktorá spracúva
                                vami poskytnuté osobné údaje v súlade so zákonom č. 122/2013 Z. z. o ochrane
                                osobných údajov v platnom znení. Vaše osobné údaje spracovávam ako správca –
                                určujem, ako budú spracované, za akým účelom a na ako dlho.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Kontaktné údaje</h2>
                            <p>
                                Správcu osobných údajov môžete kontaktovať z tejto webovej stránky
                                v záložke <router-link to="/kontakt" class="text-blue-800 underline underline-offset-4 hover:text-blue-600">Kontakt</router-link>.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Prehlásenie</h2>
                            <p class="mb-4">
                                Prehlasujem, že ako správca vašich osobných údajov spĺňam všetky zákonné
                                povinnosti vyžadované platnou legislatívou, najmä zákonom o ochrane osobných
                                údajov a GDPR (platné od 25. 5. 2018):
                            </p>
                            <ul class="space-y-2 pl-5 list-disc">
                                <li>Budem spracovávať vaše osobné údaje len na základe platného právneho dôvodu (oprávnený záujem, plnenie zmluvy, zákonná povinnosť alebo udelený súhlas).</li>
                                <li>Splním informačnú povinnosť podľa článku 13 GDPR pred zahájením spracovania.</li>
                                <li>Umožním vám uplatňovať vaše práva podľa zákona o ochrane osobných údajov a GDPR.</li>
                            </ul>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Rozsah osobných údajov a účely spracovania</h2>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="mb-1 font-semibold text-slate-900">Poskytovanie služieb a plnenie zmluvy</h3>
                                    <p>
                                        Vaše osobné údaje v rozsahu e-mail, meno, adresa a telefón nevyhnutne
                                        potrebujem k plneniu zmluvy – zaslanie tovaru, vystavenie faktúry,
                                        informovanie o stave objednávky.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="mb-1 font-semibold text-slate-900">Vedenie účtovníctva</h3>
                                    <p>
                                        Fakturačné údaje potrebujem zo zákonnej povinnosti pre vystavovanie
                                        a evidenciu daňových dokladov.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="mb-1 font-semibold text-slate-900">Marketing – zasielanie informačných mailov</h3>
                                    <p>
                                        Ak ste mojím zákazníkom, vaše osobné údaje (e-mail a meno) môžem využívať
                                        na priamy marketing po dobu 5 rokov od poslednej objednávky na základe
                                        oprávneného záujmu. Ak zákazníkom nie ste, informačné maily zasielam len
                                        na základe vášho súhlasu (5 rokov od jeho udelenia). V oboch prípadoch
                                        môžete súhlas odvolať kliknutím na odhlasovací odkaz v každom e-maile.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="mb-1 font-semibold text-slate-900">Cookies</h3>
                                    <p>
                                        Pri prechádzaní webu zaznamenávam IP adresu, dobu návštevy a zdrojovú
                                        stránku. Cookies pre meranie návštevnosti považujem za oprávnený záujem
                                        správcu. Cookies pre cielenie reklamy spracúvam len na základe vášho
                                        súhlasu. Web je možné prechádzať aj v režime bez zberu osobných údajov;
                                        cookies môžete vo svojom prehliadači zakázať.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Zabezpečenie a ochrana osobných údajov</h2>
                            <p>
                                V maximálnej možnej miere chránim osobné údaje pomocou moderných technológií
                                zodpovedajúcich stupňu technického rozvoja. Prijal som a udržiavam všetky
                                dostupné technické a organizačné opatrenia, ktoré zamedzujú zneužitiu,
                                poškodeniu alebo zničeniu vašich osobných údajov.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Poskytovanie osobných údajov tretím osobám</h2>
                            <p>
                                K vašim osobným údajom môžu mať prístup moji zamestnanci alebo spolupracovníci.
                                Pre zabezpečenie konkrétnych spracovateľských operácií využívam služby
                                spracovateľov (napr. Websupport s.r.o.). Na každého spracovateľa kladiem
                                minimálne rovnaké nároky na zabezpečenie ako na seba. Akékoľvek spracovanie
                                osobných údajov bude vykonávané na území EÚ.
                            </p>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Vaše práva</h2>
                            <div class="space-y-3">
                                <p><strong class="text-slate-900">Právo na informácie</strong> – napĺňa sa touto stránkou.</p>
                                <p><strong class="text-slate-900">Právo na prístup</strong> – môžete ma kedykoľvek vyzvať a do 10 dní vám doložím, aké vaše osobné údaje spracovávam a prečo.</p>
                                <p><strong class="text-slate-900">Právo na opravu</strong> – ak sa niečo zmení alebo sú vaše údaje neaktuálne, máte právo na ich doplnenie a zmenu.</p>
                                <p><strong class="text-slate-900">Právo na obmedzenie spracovania</strong> – môžete obmedziť rozsah alebo účely spracovania (napr. odhlásením sa z newslettera).</p>
                                <p><strong class="text-slate-900">Právo na prenositeľnosť</strong> – na požiadanie vám dodám vaše údaje v strojovo čitateľnej podobe do 20 dní.</p>
                                <p><strong class="text-slate-900">Právo na výmaz</strong> – ak si to budete priať, vymažem všetky vaše osobné údaje zo systému do 10 dní, s výnimkou údajov viazaných zákonnou povinnosťou (napr. daňové doklady).</p>
                                <p><strong class="text-slate-900">Sťažnosť na Úrad</strong> – ak máte pocit, že s vašimi údajmi nezachádzam v súlade so zákonom, môžete sa obrátiť na Úrad pre ochranu osobných údajov SR.</p>
                            </div>
                        </section>

                        <section>
                            <h2 class="mb-3 text-xl font-semibold text-slate-900">Mlčanlivosť</h2>
                            <p>
                                Moji zamestnanci a spolupracovníci sú povinní zachovávať mlčanlivosť o osobných
                                údajoch a bezpečnostných opatreniach aj po skončení zmluvných záväzkov. Bez vášho
                                súhlasu nebudú vaše osobné údaje vydané žiadnej tretej strane.
                            </p>
                            <p class="mt-4 text-sm text-slate-500">
                                Tieto zásady spracovania osobných údajov platia od 25. 5. 2018.
                            </p>
                        </section>

                    </div>
                </div>
            </section>

        </template>
    </BaseLayout>
</template>

<style scoped>
.gdpr-page {
    grid-column: 1 / -1;
    width: 100%;
    min-width: 0;
}

.gdpr-shell {
    width: 100%;
}
</style>
