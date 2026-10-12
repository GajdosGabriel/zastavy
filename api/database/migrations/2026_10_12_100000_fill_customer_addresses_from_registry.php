<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Doplnenie neúplných adries starších zákazníkov z registra (api.orsf.sk, podľa IČO) —
// ten istý zdroj, z ktorého sa berie DIČ. Zoznam je pevný a prešiel ručnou kontrolou
// 12. 10. 2026, migrácia sama do registra nechodí:
//   - ulica bez čísla domu, samotné číslo alebo prázdna ulica → sídlo z registra,
//   - neplatné PSČ → PSČ z registra (platné päťmiestne ostáva, býva to P.O.Box pošty),
//   - prázdne mesto, text namiesto mesta alebo pošta namiesto obce → obec z registra,
//   - zástupný názov „Nezistená organizácia — e-mail" → názov z registra bez adresy.
// Škola, ktorá objednávala na IČO obce či mesta (64, 75, 90, 460, 2046, 2103), dostane ako
// fakturačný názov aj adresu úrad z registra; jej pôvodný názov a adresa sa presunú do
// doručovacích adries (DELIVERY) — vznikli v čase, keď systém doručovaciu adresu nepoznal.
// Kde škola ulicu nemala vôbec (2046, 2103), nie je čo presúvať.
// Vynechaní sú zákazníci, kde register pod tým IČO vedie iný subjekt alebo si adresy
// odporujú. Pole sa prepíše len vtedy, keď v ňom je stále pôvodná hodnota a sedí IČO —
// čo medzitým niekto opravil ručne, ostáva.
return new class extends Migration
{
    /** [id => [IČO, [pole => [pôvodná, nová]]]] */
    private const CHANGES = [
        5 => ['42034442', ['street' => ['Jarná', 'Jarná ulica 3168/13']]],
        37 => ['46130101', ['street' => ['7', 'Oreské 7'], 'city' => ['oreské', 'Oreské']]],
        53 => ['36080870', ['street' => ['J. Nižnanského', 'J. Nižnanského 1']]],
        64 => ['00691305', ['street' => ['1', 'Vtáčkovce 15'], 'company' => ['Základná škola', 'Obec Vtáčkovce']]],
        73 => ['37860682', ['street' => ['Nitrianska Blatnica', 'Nitrianska Blatnica 3']]],
        75 => ['00319058', ['street' => ['84', 'Rimavské Janovce 301'], 'company' => ['ZŠ s VJM', 'Obec Rimavské Janovce']]],
        90 => ['00682152', ['street' => ['81', 'Horné Trhovište 73'], 'company' => ['Obecný úrad - Materská škola', 'Obec Horné Trhovište']]],
        154 => ['37876091', ['street' => ['258', 'Jarabina 258']]],
        158 => ['34006613', ['street' => ['93', 'Holiare 93']]],
        162 => ['00315770', ['street' => ['78', 'Štiavnička 78'], 'city' => ['Ružomberok', 'Štiavnička']]],
        204 => ['00311430', ['postcode' => ['911307', '91307']]],
        213 => ['37838423', ['street' => ['Sídlisko SNP', 'Štvrť SNP 1415/49']]],
        216 => ['37810308', ['street' => ['141', 'Hlavná 141/189']]],
        244 => ['00328880', ['postcode' => ['04918Lubeník', '04918']]],
        245 => ['00307319', ['street' => ['Nový Tekov', 'Nový Tekov 226']]],
        252 => ['00323195', ['street' => ['Ladomirov', 'Ladomirov 181']]],
        292 => ['00649571', ['postcode' => ['98004p.Hostice', '98004']]],
        306 => ['00323217', ['street' => ['Lukačovce', 'Lukačovce 101'], 'city' => ['Lukačovce okr.Humenne', 'Lukačovce']]],
        359 => ['36126667', ['street' => ['35', 'Čavoj 35']]],
        388 => ['37864211', ['street' => ['332', 'Ipeľský Sokolec 332']]],
        396 => ['37914707', ['street' => ['Horná Poruba', 'Horná Poruba 84']]],
        414 => ['00326011', ['street' => ['V. Nemecké', 'Vyšné Nemecké 55']]],
        441 => ['00690201', ['street' => ['Bunetice', 'Bunetice 5']]],
        458 => ['37840631', ['street' => ['382', 'Veľký Grob 382']]],
        460 => ['00332631', ['street' => ['Ondavské Matiašovce', 'Ondavské Matiašovce 201'], 'company' => ['ZŠ Ondavské Matiašovce', 'Obec Ondavské Matiašovce']]],
        468 => ['00686964', ['street' => ['1253', 'Ulica SNP 1253']]],
        490 => ['00014478', ['street' => ['7', 'Čakany 7']]],
        547 => ['37831496', ['street' => ['111', 'Vyhne 111']]],
        719 => ['00329991', ['street' => ['Legnava', 'Legnava 77'], 'city' => ['77', 'Legnava']]],
        796 => ['37836498', ['street' => ['Komenského', 'Komenského 3']]],
        903 => ['42214254', ['city' => ['Lokca', 'Vasiľov'], 'postcode' => ['0295', '02951']]],
        930 => ['00330841', ['street' => ['Nižný Orlík', 'Nižný Orlík 58']]],
        942 => ['00315991', ['street' => ['206', 'Breznička 206'], 'city' => ['BREZNIČKA okr. Poltár', 'Breznička']]],
        968 => ['00332763', ['street' => ['Rudlov', 'Rudlov 37']]],
        1009 => ['31810284', ['street' => ['ZS Rohožník', 'Rohožník 399']]],
        1025 => ['31926754', ['street' => ['Nábr. K. Petroviča', 'Petrovičovo nábrežie 1571/18']]],
        1040 => ['36126942', ['street' => ['Ulica energetikov', 'Ulica energetikov 242/39']]],
        1070 => ['00329061', ['postcode' => ['5601', '05601']]],
        1071 => ['00327174', ['postcode' => ['8256', '08256']]],
        1106 => ['37812289', ['street' => ['Ústredie', 'Ústredie 976']]],
        1178 => ['00311243', ['street' => ['Veľké Dvorany', 'Veľké Dvorany 2']]],
        1280 => ['37831712', ['street' => ['Hrušov', 'Hrušov 497']]],
        1292 => ['37891723', ['street' => ['Belina', 'Belina 185']]],
        1295 => ['37888561', ['street' => ['Veľká Lehota', 'Veľká Lehota 433']]],
        1328 => ['00606804', ['street' => ['Sládkovičova', 'Sládkovičova 36']]],
        1333 => ['37873407', ['street' => ['220', 'Vyšný Žipov 220'], 'city' => ['VyšnýŽipov', 'Vyšný Žipov']]],
        1335 => ['00311723', ['street' => ['Krivosúd-Bodovka', 'Krivosúd - Bodovka 88']]],
        1401 => ['00311031', ['street' => ['1', 'Ruskovce 1']]],
        1426 => ['00310409', ['street' => ['Haláčovce', 'Haláčovce 48']]],
        1481 => ['37876694', ['street' => ['Bajerov', 'Bajerov 96']]],
        1512 => ['36081019', ['street' => ['Školská', 'Školská ulica 936/1']]],
        1516 => ['37812726', ['street' => ['Ústredie', 'Ústredie 533'], 'postcode' => ['00533', '02321']]],
        1620 => ['00332585', ['street' => ['Nižná Sitnica', 'Nižná Sitnica 105']]],
        1624 => ['00696315', ['street' => ['Zálesie', 'Zálesie 64']]],
        1638 => ['00315991', ['street' => ['Breznička', 'Breznička 206']]],
        1640 => ['00611298', ['street' => ['Obecný úrad', 'Bodzianske Lúky 1']]],
        1653 => ['00311723', ['street' => ['Krivosúd-Bodovka', 'Krivosúd - Bodovka 88']]],
        1681 => ['00328812', ['street' => ['Sirk', 'Sirk 71']]],
        1717 => ['00314706', ['street' => ['132', 'Oravský Biely Potok 132'], 'city' => ['Podbiel', 'Oravský Biely Potok']]],
        1726 => ['00329401', ['street' => ['Nemešany', 'Nemešany 62']]],
        1798 => ['00330540', ['street' => ['87', 'Kečkovce 87'], 'city' => ['Vyšný Orlík', 'Kečkovce']]],
        1892 => ['00647845', ['street' => ['Mlynská', 'Mlynská 636/45']]],
        1964 => ['00329851', ['street' => ['Forbasy', 'Forbasy 40']]],
        1980 => ['00651117', ['street' => [null, 'Rosinská 3126/2'], 'company' => ['Nezistená organizácia — vrabcova@doaza.edu.sk', 'Stredná odborná škola dopravná']]],
        1982 => ['00312703', ['street' => [null, 'Hlohovská cesta 1818/2A']]],
        1983 => ['35590017', ['street' => [null, 'Petrova Lehota 51'], 'city' => ['Motešice', 'Petrova Lehota']]],
        1985 => ['00160482', ['street' => [null, 'Slov.národ.povstania 3'], 'company' => ['Nezistená organizácia — gymzmjk@stonline.sk', 'Gymnázium Janka Kráľa']]],
        1987 => ['00305936', ['street' => [null, 'Mierové námestie 940/1']]],
        1989 => ['35677813', ['street' => [null, 'Tajovského 2']]],
        1991 => ['37879413', ['street' => [null, 'Predmestie 26'], 'company' => ['Nezistená organizácia — mspredmestie26@gmail.com', 'Materská škola']]],
        1994 => ['00329746', ['street' => [null, 'Vítkovce 99']]],
        2002 => ['00311073', ['street' => [null, 'Slatina nad Bebravou 17'], 'city' => ['', 'Slatina nad Bebravou']]],
        2003 => ['00321265', ['street' => [null, 'Fačkov 69'], 'city' => ['', 'Fačkov']]],
        2009 => ['37888528', ['city' => ['', 'Zvolen']]],
        2012 => ['00162167', ['street' => [null, 'Murgašova 94']]],
        2014 => ['42434858', ['street' => [null, 'Okoličianska 404/8C']]],
        2015 => ['00650048', ['street' => [null, 'Hrlica 9']]],
        2017 => ['42107148', ['street' => [null, 'Požiarnická 1'], 'company' => ['Nezistená organizácia — irenaadam@centrum.sk', 'Súkromné hudobné a dramatické konzervatórium']]],
        2027 => ['42447402', ['street' => [null, 'Osloboditeľská 1']]],
        2028 => ['37812149', ['street' => [null, 'Radôstka 168'], 'city' => ['', 'Radôstka']]],
        2030 => ['42026644', ['street' => [null, 'Jablonov 209']]],
        2035 => ['42089808', ['street' => [null, 'M. R. Štefánika 140']]],
        2037 => ['37812483', ['street' => [null, 'Rudinská 115'], 'city' => ['Rudina', 'Rudinská'], 'company' => ['Nezistená organizácia — zsrudinska@centrum.sk', 'Základná škola s materskou školou']]],
        2038 => ['31934617', ['city' => ['', 'Ružomberok'], 'postcode' => ['', '03401']]],
        2039 => ['42104980', ['street' => [null, 'Mierová 727'], 'company' => ['Nezistená organizácia — strazskesos@gmail.com', 'Stredná odborná škola dopravy a služieb']]],
        2041 => ['00321583', ['street' => [null, 'Rajecká Lesná 82'], 'company' => ['Nezistená organizácia — maria.ganova@rajec.net', 'Obec Rajecká Lesná']]],
        2044 => ['00322113', ['street' => [null, 'Kľušov 175']]],
        2046 => ['00316075', ['street' => [null, 'Radničná 562/25'], 'company' => ['Nezistená organizácia — mssturova@filakovo.sk', 'Mesto Fiľakovo']]],
        2047 => ['36125661', ['street' => [null, 'Nádražná 31/955']]],
        2049 => ['36076252', ['city' => ['', 'Bratislava']]],
        2050 => ['00315311', ['street' => [null, 'Komjatná 292'], 'city' => ['', 'Komjatná'], 'company' => ['Nezistená organizácia — starosta@komjatna.sk', 'Obec Komjatná']]],
        2053 => ['00316644', ['street' => [null, 'Ďanová 22'], 'city' => ['', 'Ďanová']]],
        2056 => ['37810944', ['street' => [null, 'M. R. Štefánika 432']]],
        2057 => ['00162574', ['street' => [null, 'Námestie mladých poľnohospodárov 2'], 'company' => ['Nezistená organizácia — slovakova@sosvet.sk', 'Stredná odborná škola veterinárna']]],
        2059 => ['17050561', ['city' => ['', 'Považská Bystrica']]],
        2061 => ['37876040', ['city' => ['', 'Štrba']]],
        2067 => ['37864505', ['street' => [null, 'Školská 10']]],
        2068 => ['00325759', ['street' => [null, 'Sejkov 80'], 'city' => ['Meno objednávajúceho: Peter Tokár', 'Sejkov']]],
        2069 => ['00493767', ['city' => ['', 'Banská Bystrica']]],
        2070 => ['00323659', ['street' => [null, 'Topoľovka 194']]],
        2079 => ['00689742', ['city' => ['', 'Budince']]],
        2081 => ['36128473', ['street' => [null, 'J. A. Komenského 1290/1']]],
        2086 => ['37785834', ['street' => [null, 'Gašpara Haina 37']]],
        2091 => ['00692379', ['street' => [null, 'Vydrná 51'], 'city' => ['Lúky', 'Vydrná'], 'company' => ['Nezistená organizácia — obecvydrna@centrum.sk', 'Obec Vydrná']]],
        2094 => ['00323454', ['street' => [null, 'Radvaň nad Laborcom 76'], 'city' => ['Meno objednávajúceho: Jana Svičinová', 'Radvaň nad Laborcom'], 'company' => ['Nezistená organizácia — starosta@obecradvan.sk', 'Obec Radvaň nad Laborcom']]],
        2096 => ['00648884', ['street' => [null, 'Stránske 168'], 'city' => ['pošta Rajecké Teplice', 'Stránske'], 'company' => ['Nezistená organizácia — jaro.mitas@gmail.com', 'Obec Stránske']]],
        2097 => ['42227496', ['street' => [null, 'Námestie legionárov 3'], 'company' => ['Nezistená organizácia — simova@esspo.sk', 'Evanjelická spojená škola']]],
        2098 => ['37842781', ['street' => [null, 'Clementisove Sady 902/3']]],
        2103 => ['00315737', ['street' => [null, 'Nám. A. Hlinku 1098/1'], 'company' => ['Základná škola', 'Mesto Ružomberok']]],
        2105 => ['37860747', ['street' => [null, 'Školská 5']]],
        2107 => ['35630108', ['street' => [null, 'Štúrova 573'], 'company' => ['Nezistená organizácia — szssastin@gmail.com', 'Špeciálna základná škola']]],
        2110 => ['00318868', ['company' => ['Nezistená organizácia — info@obeckonradovce.sk', 'Obec Konrádovce']]],
        2117 => ['00314277', ['street' => [null, 'Rudinská 125'], 'city' => ['', 'Rudinská'], 'company' => ['Nezistená organizácia — strbakova@rudinska.sk', 'Obec Rudinská']]],
        2119 => ['36158381', ['city' => ['', 'Krajná Poľana']]],
        2122 => ['00649805', ['street' => [null, 'Ratkovská Lehota 21'], 'city' => ['Ratková', 'Ratkovská Lehota']]],
    ];

    /** Pôvodná adresa školy ako doručovacia: [id zákazníka => [názov, ulica, PSČ, mesto]] */
    private const DELIVERY = [
        64 => ['Základná škola', 'Vtáčkovce 1', '04447', 'Vtáčkovce'],
        75 => ['ZŠ s VJM', 'Rimavské Janovce 84', '98001', 'Rimavské Janovce'],
        90 => ['Materská škola', 'Horné Trhovište 81', '92066', 'Horné Trhovište'],
        460 => ['ZŠ Ondavské Matiašovce', 'Ondavské Matiašovce', '09401', 'Ondavské Matiašovce'],
    ];

    public function up(): void
    {
        $touched = $this->apply(0, 1);

        if (! Schema::hasTable('customer_addresses')) {
            return;
        }

        foreach (self::DELIVERY as $id => [$company, $street, $postcode, $city]) {
            // Len keď sa fakturačná adresa práve prepísala a zákazník ešte doručovaciu nemá.
            if (! in_array($id, $touched, true) || DB::table('customer_addresses')->where('customer_id', $id)->exists()) {
                continue;
            }

            DB::table('customer_addresses')->insert([
                'customer_id' => $id,
                'company' => $company,
                'street' => $street,
                'postcode' => $postcode,
                'city' => $city,
                'country' => 'SK',
                'phone' => DB::table('customers')->where('id', $id)->value('phone'),
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $this->apply(1, 0);

        if (! Schema::hasTable('customer_addresses')) {
            return;
        }

        foreach (self::DELIVERY as $id => [$company, $street, $postcode, $city]) {
            DB::table('customer_addresses')
                ->where(['customer_id' => $id, 'company' => $company, 'street' => $street, 'postcode' => $postcode, 'city' => $city])
                ->whereNotIn('id', DB::table('orders')->whereNotNull('customer_address_id')->select('customer_address_id'))
                ->delete();
        }
    }

    /** @return array<int, int>  id zákazníkov, ktorým sa niečo zmenilo */
    private function apply(int $from, int $to): array
    {
        if (! Schema::hasTable('customers')) {
            return [];
        }

        $touched = [];

        foreach (self::CHANGES as $id => [$ico, $fields]) {
            $row = DB::table('customers')->where('id', $id)->where('ico', $ico)->first();

            if ($row === null) {
                continue;
            }

            $update = [];

            foreach ($fields as $field => $pair) {
                // NULL a prázdny reťazec sú v týchto stĺpcoch to isté „nevyplnené".
                if (trim((string) $row->{$field}) === trim((string) $pair[$from])) {
                    $update[$field] = $pair[$to];
                }
            }

            if ($update !== []) {
                DB::table('customers')->where('id', $id)->update($update);
                $touched[] = $id;
            }
        }

        // Posudok kontroly kvality platí pre staré údaje — nech sa prepočíta v najbližšom behu.
        if ($touched !== [] && Schema::hasTable('customer_reviews')) {
            DB::table('customer_reviews')->whereIn('customer_id', $touched)->update([
                'due_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ]);
        }

        return $touched;
    }
};
