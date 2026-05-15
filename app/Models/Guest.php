<?php

namespace App\Models;

use App\Support\GuestIdentity;
use App\Support\PsgcApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\RequiredIf;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    /* ================= GENDER ================= */
    const GENDER_MALE = 'male';

    const GENDER_FEMALE = 'female';

    const GENDER_OTHER = 'other';

    public static function genderOptions(): array
    {
        return [
            self::GENDER_MALE => 'Male',
            self::GENDER_FEMALE => 'Female',
            self::GENDER_OTHER => 'Other',
        ];
    }

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'contact_num',
        'gender',

        'is_international',
        'country',
        'region',
        'province',
        'municipality',
        'barangay',

    ];

    // Cast fields
    protected $casts = [
        'is_international' => 'boolean',
    ];

    /**
     * Get the full name of the guest
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? " {$this->middle_name} " : ' ';

        return "{$this->first_name}{$middle}{$this->last_name}";
    }

    /**
     * Relationships
     */

    /**
     * Scope for international guests
     */
    public function scopeInternational($query)
    {
        return $query->where('is_international', true);
    }

    /**
     * Scope for local guests
     */
    public function scopeLocal($query)
    {
        return $query->where('is_international', false);
    }

    // Link to the ID photo in the images table
    public function identification()
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'identification');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public static function store($request)
    {
        $source = $request instanceof Request ? $request->all() : (array) $request;
        $normalized = self::normalizeGuestPayload($source);

        $validated = validator($normalized, [
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email',
            'contact_num' => [
                'nullable',
                'string',
                'max:20',
                new RequiredIf(fn () => ! ((bool) ($normalized['is_international'] ?? false))),
            ],
            'gender' => 'nullable|in:'.implode(',', [Guest::GENDER_MALE, Guest::GENDER_FEMALE, Guest::GENDER_OTHER]),
            'is_international' => 'required|boolean',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'barangay' => 'nullable|string|max:100',
        ])->validate();

        if (! $validated['is_international']) {
            $validated['country'] = 'Philippines';
        } elseif (isset($validated['country']) && strcasecmp((string) $validated['country'], 'Philippines') === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'country' => ['Foreign guests cannot use Philippines as country.'],
            ]);
        } elseif (($validated['contact_num'] ?? null) === null) {
            // DB column is non-nullable; foreign guests may legitimately skip phone.
            $validated['contact_num'] = '';
        }

        $normalizedEmail = strtolower(trim((string) $validated['email']));
        if ($normalizedEmail !== '') {
            $validated['email'] = $normalizedEmail;
        }

        $existingGuest = null;

        if (self::shouldAttemptGuestIdentityReuse($source, $normalizedEmail)) {
            $existingGuest = self::findReusableGuestByEmailAndName($normalizedEmail, $validated);
        }

        if ($existingGuest instanceof self) {
            if ($existingGuest->trashed()) {
                $existingGuest->restore();
            }

            return $existingGuest->fresh();
        }

        return self::create($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function findReusableGuestByEmailAndName(string $normalizedEmail, array $validated): ?self
    {
        if ($normalizedEmail === '') {
            return null;
        }

        $candidates = self::withTrashed()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->get();

        foreach ($candidates as $candidate) {
            if (GuestIdentity::guestMatchesNameIdentity($candidate, $validated)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build per-booking snapshot fields from a booking/guest payload (form or API).
     *
     * @param  \Illuminate\Http\Request|array<string, mixed>  $source
     * @return array{guest_name_snapshot: string, guest_email_snapshot: string, guest_contact_snapshot: string|null, guest_address_snapshot: string|null}
     */
    public static function bookingSnapshotAttributesFromSource($source): array
    {
        $payload = self::normalizeGuestPayload($source instanceof Request ? $source->all() : (array) $source);

        return GuestIdentity::bookingSnapshotAttributes($payload);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function shouldAttemptGuestIdentityReuse(array $source, string $normalizedEmail): bool
    {
        if ($normalizedEmail === '') {
            return false;
        }

        $forceManual = self::toBoolean($source['is_manual_booking'] ?? false)
            || self::toBoolean($source['is_walk_in'] ?? false)
            || self::toBoolean($source['email_is_shared'] ?? false);
        if ($forceManual) {
            return false;
        }

        if (self::matchesSharedEmailPattern($normalizedEmail)) {
            return false;
        }

        $bookingSourceRaw = trim((string) ($source['booking_source'] ?? $source['source'] ?? 'online'));
        $bookingSource = strtolower($bookingSourceRaw);
        $manualSources = ['manual', 'walk_in', 'walk-in', 'walkin', 'staff', 'admin'];
        $isManualSource = in_array($bookingSource, $manualSources, true);

        if ($isManualSource) {
            return self::toBoolean($source['allow_manual_email_match'] ?? false);
        }

        return true;
    }

    private static function matchesSharedEmailPattern(string $normalizedEmail): bool
    {
        $patternsRaw = trim((string) env('GUEST_SHARED_EMAIL_PATTERNS', ''));
        if ($patternsRaw === '') {
            return false;
        }

        $patterns = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $patternsRaw)
        )));

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            // Pattern formats:
            // - exact email: frontdesk@example.com
            // - domain suffix: @example.com
            if (str_starts_with($pattern, '@')) {
                if (str_ends_with($normalizedEmail, $pattern)) {
                    return true;
                }

                continue;
            }

            if ($normalizedEmail === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function normalizeGuestPayload(array $input): array
    {
        $nested = [];
        foreach (['guest', 'guest_details', 'guestDetail', 'guest_info'] as $key) {
            if (isset($input[$key]) && is_array($input[$key])) {
                $nested = $input[$key];
                break;
            }
        }

        $data = array_merge($input, $nested);
        $isInternational = self::toBoolean($data['is_international'] ?? $data['international'] ?? false);

        $regionCode = self::readString($data, ['ph_region_code', 'region_code']);
        $provinceCode = self::readString($data, ['ph_province_code', 'province_code']);
        $municipalityCode = self::readString($data, ['ph_municipality_code', 'municipality_code', 'city_code']);
        $barangayCode = self::readString($data, ['ph_barangay_code', 'barangay_code', 'baranggay_code']);

        $region = self::readString($data, ['region']);
        if ($region === null && $regionCode !== null) {
            $region = PsgcApi::regionOptions()[$regionCode] ?? null;
        }

        $province = self::readString($data, ['province']);
        if ($province === null && $provinceCode !== null && $regionCode !== null) {
            $province = PsgcApi::provinceOptions($regionCode)[$provinceCode] ?? null;
        }

        $municipality = self::readString($data, ['municipality', 'city', 'municipality_city']);
        if ($municipality === null && $municipalityCode !== null) {
            $municipality = PsgcApi::municipalityOptions($regionCode, $provinceCode)[$municipalityCode] ?? null;
        }

        $barangay = self::readString($data, ['barangay', 'baranggay']);
        if ($barangay === null && $barangayCode !== null && $municipalityCode !== null) {
            $barangay = PsgcApi::barangayOptions($municipalityCode)[$barangayCode] ?? null;
        }

        return [
            'first_name' => self::readString($data, ['first_name']),
            'middle_name' => self::readString($data, ['middle_name']),
            'last_name' => self::readString($data, ['last_name']),
            'email' => self::readString($data, ['email']),
            'contact_num' => self::readString($data, ['contact_num', 'contact_number', 'phone']),
            'gender' => self::readString($data, ['gender']),
            'is_international' => $isInternational,
            'country' => self::readString($data, ['country']),
            'region' => $isInternational ? null : $region,
            'province' => $isInternational ? null : $province,
            'municipality' => $isInternational ? null : $municipality,
            'barangay' => $isInternational ? null : $barangay,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private static function readString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($data[$key] === null) {
                return null;
            }

            $value = trim((string) $data[$key]);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private static function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }
}
