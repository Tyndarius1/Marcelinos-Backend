<?php

namespace App\Models;

use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /**
     * Normalize legacy / mixed permission keys to our canonical snake_case keys.
     *
     * @param  array<int, mixed>  $permissions
     * @return array<int, string>
     */
    private static function normalizePermissionKeys(array $permissions): array
    {
        $allowed = array_keys(self::staffPrivilegeOptions());

        $normalized = [];
        foreach ($permissions as $key) {
            if (! is_string($key)) {
                continue;
            }

            $k = trim($key);
            if ($k === '') {
                continue;
            }

            // Handle legacy camelCase like "manageRooms" -> "manage_rooms"
            $k = Str::snake($k);

            if (in_array($k, $allowed, true)) {
                $normalized[] = $k;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function setPermissionsAttribute($value): void
    {
        // Normalize to a simple list of enabled permission keys.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            $this->attributes['permissions'] = json_encode([]);

            return;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        $selectedKeys = $isAssoc
            ? array_keys(array_filter($value, fn ($enabled) => (bool) $enabled))
            : array_values(array_filter($value, fn ($key) => is_string($key) && trim($key) !== ''));

        $this->attributes['permissions'] = json_encode(self::normalizePermissionKeys($selectedKeys));
    }

    public function getPermissionsAttribute($value): array
    {
        // Ensure anything loaded from DB (including legacy values) is canonical.
        $decoded = [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        }

        if (! is_array($decoded)) {
            return [];
        }

        return self::normalizePermissionKeys($decoded);
    }

    /**
     * Central list used by admin UI when assigning staff permissions.
     *
     * @return array<string, string>
     */
    public static function staffPrivilegeOptions(): array
    {
        return [
            // Operations
            'manage_bookings' => 'Operations · Bookings',
            'manage_blocked_dates' => 'Operations · Blocked dates',
            'manage_reviews' => 'Operations · Reviews',

            // People
            'manage_guests' => 'People · Guests',

            // Properties
            'manage_venues' => 'Properties · Venues',
            'manage_rooms' => 'Properties · Rooms',
            'manage_amenities' => 'Properties · Amenities',

            // Management
            'manage_contact_messages' => 'Management · Contact messages',

            // Content
            'manage_galleries' => 'Content · Gallery',
            'manage_blog_posts' => 'Content · Blog posts',
            'manage_bubble_chat_faqs' => 'Content · Bubble chat FAQs',

            // Reports
            'view_export_revenue' => 'Reports · Export revenue',
            'view_guest_demographics' => 'Reports · Guest demographics',
            'manage_activity_logs' => 'Reports · Activity history',
        ];
    }

    /**
     * Grouped privileges for nicer admin UI display.
     *
     * @return array<string, array<string, string>>
     */
    public static function staffPrivilegeOptionGroups(): array
    {
        return [
            'Operations' => [
                'manage_bookings' => 'Bookings',
                'manage_blocked_dates' => 'Blocked dates',
                'manage_reviews' => 'Reviews',
            ],
            'People' => [
                'manage_guests' => 'Guests',
            ],
            'Properties' => [
                'manage_venues' => 'Venues',
                'manage_rooms' => 'Rooms',
                'manage_amenities' => 'Amenities',
            ],
            'Management' => [
                'manage_contact_messages' => 'Contact messages',
            ],
            'Content' => [
                'manage_galleries' => 'Gallery',
                'manage_blog_posts' => 'Blog posts',
                'manage_bubble_chat_faqs' => 'Bubble chat FAQs',
            ],
            'Reports' => [
                'view_export_revenue' => 'Export revenue',
                'view_guest_demographics' => 'Guest demographics',
                'manage_activity_logs' => 'Activity history',
            ],
        ];
    }

    public function hasPrivilege(string $privilege): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if ($this->role !== 'staff') {
            return false;
        }

        $permissions = $this->permissions ?? [];

        if (! is_array($permissions)) {
            return false;
        }

        return in_array($privilege, $permissions, true);
    }

    protected static function booted()
    {
        static::updating(function ($user) {
            // Check if it WAS an admin before this change
            if ($user->getOriginal('role') === 'admin' && $user->role !== 'admin') {
                throw new Exception('Security Breach: You cannot change the Admin role!');
            }
        });

        static::deleting(function ($user) {
            if ($user->getOriginal('role') === 'admin') {
                throw new Exception('Security Breach: You cannot delete the Admin!');
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $role = strtolower(trim((string) ($this->role ?? '')));

        return match ($panel->getId()) {
            'admin' => in_array($role, ['admin', 'staff'], true),
            'staff' => in_array($role, ['admin', 'staff'], true), // admins can access staff panel too
            default => false,
        };
    }
}
