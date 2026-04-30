<?php

namespace Tests\Unit;

use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestEmailIdentityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_source_reuses_existing_guest_by_email(): void
    {
        $existing = Guest::create([
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
            'country' => 'Philippines',
            'region' => null,
            'province' => null,
            'municipality' => null,
            'barangay' => null,
        ]);

        $resolved = Guest::store([
            'booking_source' => 'online',
            'first_name' => 'Updated',
            'middle_name' => null,
            'last_name' => 'Guest',
            'email' => 'shared@example.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Guest::count());
        $this->assertSame('Bryan', $resolved->first_name);
        $this->assertSame('Dacera', $resolved->last_name);
        $this->assertSame('09123456789', $resolved->contact_num);
    }

    public function test_manual_source_does_not_auto_reuse_by_email(): void
    {
        Guest::create([
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
            'country' => 'Philippines',
            'region' => null,
            'province' => null,
            'municipality' => null,
            'barangay' => null,
        ]);

        $resolved = Guest::store([
            'booking_source' => 'manual',
            'first_name' => 'Jonathan',
            'middle_name' => null,
            'last_name' => 'Perez',
            'email' => 'shared@example.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame('Jonathan', $resolved->first_name);
        $this->assertSame(2, Guest::count());
    }

    public function test_manual_source_can_opt_in_to_email_match(): void
    {
        $existing = Guest::create([
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
            'country' => 'Philippines',
            'region' => null,
            'province' => null,
            'municipality' => null,
            'barangay' => null,
        ]);

        $resolved = Guest::store([
            'booking_source' => 'manual',
            'allow_manual_email_match' => true,
            'first_name' => 'Merged',
            'middle_name' => null,
            'last_name' => 'Guest',
            'email' => 'shared@example.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Guest::count());
    }
}

