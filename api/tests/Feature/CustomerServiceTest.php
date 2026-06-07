<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_checkout_reuses_existing_user_email_without_creating_or_updating_user(): void
    {
        $email = 'codex-existing-user@example.test';
        $userCount = User::count();

        $existingCustomer = Customer::create([
            'name' => 'Existing Company',
            'company' => 'Existing Company',
            'email' => $email,
            'phone' => '0900000000',
            'postcode' => '01001',
            'city' => 'Zilina',
        ]);

        $existingUser = User::factory()->create([
            'email' => $email,
            'customer_id' => $existingCustomer->id,
            'phone' => '0900000000',
        ]);

        [$customer, $user] = (new CustomerService())->handleCheckout([
            'company' => 'New Company',
            'name' => 'New Contact',
            'email' => $email,
            'phone' => '0911111111',
            'street' => 'New Street 1',
            'postcode' => '02001',
            'city' => 'Trencin',
            'ico' => '12345678',
        ]);

        $this->assertSame($existingUser->id, $user->id);
        $this->assertSame($userCount + 1, User::count());
        $this->assertSame('New Company', $customer->company);

        $existingUser->refresh();

        $this->assertSame($existingCustomer->id, $existingUser->customer_id);
        $this->assertSame('0900000000', $existingUser->phone);
    }
}
