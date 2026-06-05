<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);


        public function run()
        {
            // $this->call(UsersTableSeeder::class);
    
            $role_admin = new Role();
            $role_admin->name = 'Administrátor';
            $role_admin->description = 'Popis admin';
            $role_admin->save();
    
            $role_ucitel = new Role();
            $role_ucitel->name = 'Učiteľ';
            $role_ucitel->description = 'Popis učiteľa';
            $role_ucitel->save();
    
            $role_rodic = new Role();
            $role_rodic->name = 'Rodič';
            $role_rodic->description = 'Popis rodiča';
            $role_rodic->save();
    
            $role_student = new Role();
            $role_student->name = 'Žiak';
            $role_student->description = 'Popis žiaka';
            $role_student->save();
    
    
            ////////////////////////////////////////
    
    
            $agreemend_first = new Agreement();
            $agreemend_first->title = 'Súhlas na spracovanie osobných údajov';
            $agreemend_first->body = 'Popis prvého súhlasu';
            $agreemend_first->save();
    
            $agreemend_second = new Agreement();
            $agreemend_second->title = 'Súhlas zverejnenia mena na nástenke';
            $agreemend_second->body = 'Popis druhého súhlasu';
            $agreemend_second->save();
    
            $agreemend_three = new Agreement();
            $agreemend_three->title = 'Súhlas zverejnenia mena na webe školy';
            $agreemend_three->body = 'Popis tretieho súhlasu';
            $agreemend_three->save();
    
    
            ////////////////////
            $tutorial_1 = new Tutorial();
            $tutorial_1->title = 'setup-profile';
            $tutorial_1->description = 'Nastavenie profilu user, vyplnenie osobných údajov';
            $tutorial_1->save();
    
            $tutorial_2 = new Tutorial();
            $tutorial_2->title = 'create-class';
            $tutorial_2->description = 'Vytvorenie školskej triedy';
            $tutorial_2->save();
    
            $tutorial_3 = new Tutorial();
            $tutorial_3->title = 'create-student';
            $tutorial_3->description = 'Vytvorenie študenta';
            $tutorial_3->save();
    
            $tutorial_4 = new Tutorial();
            $tutorial_4->title = 'create-parent';
            $tutorial_4->description = 'Vytvorenie rodiča';
            $tutorial_4->save();
    
            $tutorial_5 = new Tutorial();
            $tutorial_5->title = 'iam-admin';
            $tutorial_5->description = 'Oznámenie že user, ako administrátor môže pridávať ďalších učiteľov';
            $tutorial_5->save();
    
    
    
            ////////////////////
            $tutorial_11 = new Tutorial();
            $tutorial_11->title = 'admin-can-add-workers';
            $tutorial_11->description = 'Ste administrátor, môžete pridať ďalších zamestnancov. Chcete pridať nových zamestnancov?';
            $tutorial_11->save();
    
    
        }
    }
}
