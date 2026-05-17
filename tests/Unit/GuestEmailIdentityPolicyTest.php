<?php

namespace Tests\Unit;

use App\Models\Guest;
use App\Support\GuestIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestEmailIdentityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_source_reuses_guest_when_email_name_and_contact_match(): void
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
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Guest::count());
        $this->assertSame('Bryan', $resolved->first_name);
        $this->assertSame('Dacera', $resolved->last_name);
        $this->assertSame('09123456789', $resolved->contact_num);
    }

    public function test_online_source_creates_new_guest_when_email_and_name_match_but_contact_differs(): void
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
            'booking_source' => 'online',
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame('09999999999', $resolved->contact_num);
        $this->assertSame(2, Guest::count());
    }

    public function test_online_source_creates_new_guest_when_email_matches_but_name_differs(): void
    {
        Guest::create([
            'first_name' => 'Kobe',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'kingvcuts@gmail.com',
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
            'first_name' => 'LK',
            'middle_name' => null,
            'last_name' => 'ROA',
            'email' => 'kingvcuts@gmail.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame('LK', $resolved->first_name);
        $this->assertSame('ROA', $resolved->last_name);
        $this->assertSame(2, Guest::count());
    }

    public function test_typo_in_name_creates_separate_guest(): void
    {
        Guest::create([
            'first_name' => 'LK',
            'middle_name' => null,
            'last_name' => 'ROA',
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
            'first_name' => 'LKS',
            'middle_name' => null,
            'last_name' => 'ROA',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame(2, Guest::count());
        $this->assertSame('LKS', $resolved->first_name);
    }

    public function test_name_normalization_treats_case_and_spacing_as_same_identity(): void
    {
        $existing = Guest::create([
            'first_name' => 'LK',
            'middle_name' => null,
            'last_name' => 'ROA',
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
            'first_name' => '  lk  ',
            'middle_name' => null,
            'last_name' => 'roa',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Guest::count());
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

    public function test_manual_source_reuses_only_when_email_name_and_contact_match(): void
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
            'first_name' => 'Bryan',
            'middle_name' => null,
            'last_name' => 'Dacera',
            'email' => 'shared@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Guest::count());
    }

    public function test_manual_opt_in_does_not_merge_when_name_differs(): void
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
            'allow_manual_email_match' => true,
            'first_name' => 'Merged',
            'middle_name' => null,
            'last_name' => 'Guest',
            'email' => 'shared@example.com',
            'contact_num' => '09999999999',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
        ]);

        $this->assertSame('Merged', $resolved->first_name);
        $this->assertSame(2, Guest::count());
    }

    public function test_booking_snapshot_attributes_from_request_payload(): void
    {
        $snapshots = Guest::bookingSnapshotAttributesFromSource([
            'first_name' => 'LK',
            'middle_name' => null,
            'last_name' => 'ROA',
            'email' => 'KingVCuts@gmail.com',
            'contact_num' => '09171234567',
            'barangay' => 'Poblacion',
            'municipality' => 'Tagum',
            'province' => 'Davao del Norte',
            'region' => 'Region XI',
            'country' => 'Philippines',
        ]);

        $this->assertSame('LK ROA', $snapshots['guest_name_snapshot']);
        $this->assertSame('kingvcuts@gmail.com', $snapshots['guest_email_snapshot']);
        $this->assertStringContainsString('Tagum', (string) $snapshots['guest_address_snapshot']);
    }

    public function test_guest_identity_name_key(): void
    {
        $this->assertTrue(GuestIdentity::guestMatchesFullIdentity(
            new Guest(['first_name' => 'LK', 'middle_name' => null, 'last_name' => 'ROA', 'contact_num' => '09171234567']),
            ['first_name' => 'lk', 'middle_name' => null, 'last_name' => 'roa', 'contact_num' => '0917-123-4567'],
        ));

        $this->assertFalse(GuestIdentity::guestMatchesFullIdentity(
            new Guest(['first_name' => 'LK', 'middle_name' => null, 'last_name' => 'ROA', 'contact_num' => '09171234567']),
            ['first_name' => 'LKS', 'middle_name' => null, 'last_name' => 'ROA', 'contact_num' => '09171234567'],
        ));

        $this->assertFalse(GuestIdentity::guestMatchesFullIdentity(
            new Guest(['first_name' => 'LK', 'middle_name' => null, 'last_name' => 'ROA', 'contact_num' => '09171234567']),
            ['first_name' => 'lk', 'middle_name' => null, 'last_name' => 'roa', 'contact_num' => '09999999999'],
        ));
    }
}
