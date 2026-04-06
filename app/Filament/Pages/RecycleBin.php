<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Amenities\AmenityResource;
use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use App\Filament\Resources\BlockedDates\BlockedDateResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\Guests\GuestResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Venues\VenuesResource;
use App\Models\Amenity;
use App\Models\BedSpecification;
use App\Models\BlockedDate;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\ContactUs;
use App\Models\Gallery;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlockedDate;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Throwable;

class RecycleBin extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Recycle bin';

    protected static ?string $title = 'Recycle bin';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.recycle-bin';

    protected int $trashPerPage = 20;

    #[Url(as: 'tp')]
    public int $trashPage = 1;

    public ?string $purgeType = null;

    public int|string|null $purgeId = null;

    public string $purgeTypedConfirm = '';

    public static function canAccess(): bool
    {
        $role = strtolower(trim((string) (auth()->user()?->role ?? '')));

        return $role === 'admin';
    }

    public static function primaryTrashedTotal(): int
    {
        return (int) collect(self::primaryLinks())->sum('count');
    }

    public static function allTrashedTotal(): int
    {
        return self::primaryTrashedTotal()
            + Payment::onlyTrashed()->count()
            + RoomBlockedDate::onlyTrashed()->count()
            + VenueBlockedDate::onlyTrashed()->count();
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    public function getLinksProperty(): array
    {
        return self::primaryLinks();
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    public static function primaryLinks(string $panel = 'admin'): array
    {
        return [
            [
                'label' => 'Bookings',
                'count' => Booking::onlyTrashed()->count(),
                'url' => self::trashedListUrl(BookingResource::class, $panel),
            ],
            [
                'label' => 'Guests',
                'count' => Guest::onlyTrashed()->count(),
                'url' => self::trashedListUrl(GuestResource::class, $panel),
            ],
            [
                'label' => 'Rooms',
                'count' => Room::onlyTrashed()->count(),
                'url' => self::trashedListUrl(RoomResource::class, $panel),
            ],
            [
                'label' => 'Venues',
                'count' => Venue::onlyTrashed()->count(),
                'url' => self::trashedListUrl(VenuesResource::class, $panel),
            ],
            [
                'label' => 'Staff (users)',
                'count' => User::onlyTrashed()->count(),
                'url' => self::trashedListUrl(StaffResource::class, $panel),
            ],
            [
                'label' => 'Reviews',
                'count' => Review::onlyTrashed()->count(),
                'url' => self::trashedListUrl(ReviewResource::class, $panel),
            ],
            [
                'label' => 'Contact messages',
                'count' => ContactUs::onlyTrashed()->count(),
                'url' => self::trashedListUrl(ContactUsResource::class, $panel),
            ],
            [
                'label' => 'Gallery images',
                'count' => Gallery::onlyTrashed()->count(),
                'url' => self::trashedListUrl(GalleryResource::class, $panel),
            ],
            [
                'label' => 'Blog posts',
                'count' => BlogPost::onlyTrashed()->count(),
                'url' => self::trashedListUrl(BlogPostResource::class, $panel),
            ],
            [
                'label' => 'Amenities',
                'count' => Amenity::onlyTrashed()->count(),
                'url' => self::trashedListUrl(AmenityResource::class, $panel),
            ],
            [
                'label' => 'Bed specifications',
                'count' => BedSpecification::onlyTrashed()->count(),
                'url' => self::trashedListUrl(BedSpecificationResource::class, $panel),
            ],
            [
                'label' => 'Resort blocked dates',
                'count' => BlockedDate::onlyTrashed()->count(),
                'url' => self::trashedListUrl(BlockedDateResource::class, $panel),
            ],
        ];
    }

    public function getTotalTrashedProperty(): int
    {
        return self::allTrashedTotal();
    }

    public function getTrashedPaginatorProperty(): LengthAwarePaginator
    {
        $items = $this->collectTrashedItems();

        return new LengthAwarePaginator(
            $items->forPage($this->trashPage, $this->trashPerPage)->values(),
            $items->count(),
            $this->trashPerPage,
            $this->trashPage,
            [
                'path' => request()->url(),
                'pageName' => 'tp',
            ],
        );
    }

    public function updatedTrashPage(): void
    {
        $this->closePurgeModal();
    }

    public function restoreItem(string $type, int|string $id): void
    {
        $this->assertRecycleAccess();

        try {
            $model = $this->resolveTrashedModel($type, $id);
            Gate::authorize('restore', $model);
            $model->restore();

            Notification::make()
                ->title(__('Restored'))
                ->body(__('“:name” was put back where it belongs.', ['name' => $this->rowLabel($type, $model)]))
                ->success()
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->title(__('Not allowed'))
                ->body(__('You cannot restore this item.'))
                ->danger()
                ->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title(__('Could not restore'))
                ->body(__('Something went wrong. Try again or open the related list.'))
                ->danger()
                ->send();
        }
    }

    public function openPurgeModal(string $type, int|string $id): void
    {
        $this->assertRecycleAccess();
        $this->resolveTrashedModel($type, $id);
        $this->purgeType = $type;
        $this->purgeId = $id;
        $this->purgeTypedConfirm = '';
    }

    public function closePurgeModal(): void
    {
        $this->purgeType = null;
        $this->purgeId = null;
        $this->purgeTypedConfirm = '';
    }

    public function getPurgeItemNameProperty(): string
    {
        if ($this->purgeType === null || $this->purgeId === null) {
            return '';
        }

        try {
            $model = $this->resolveTrashedModel($this->purgeType, $this->purgeId);

            return $this->rowLabel($this->purgeType, $model);
        } catch (Throwable) {
            return '';
        }
    }

    public function confirmPermanentDelete(): void
    {
        $this->assertRecycleAccess();

        if (trim($this->purgeTypedConfirm) !== 'DELETE') {
            Notification::make()
                ->title(__('Type DELETE to confirm'))
                ->body(__('Permanent removal requires typing DELETE in all capital letters.'))
                ->warning()
                ->send();

            return;
        }

        if ($this->purgeType === null || $this->purgeId === null) {
            $this->closePurgeModal();

            return;
        }

        try {
            $model = $this->resolveTrashedModel($this->purgeType, $this->purgeId);
            $name = $this->rowLabel($this->purgeType, $model);
            Gate::authorize('forceDelete', $model);
            $model->forceDelete();

            Notification::make()
                ->title(__('Permanently deleted'))
                ->body(__('“:name” was removed from the database.', ['name' => $name]))
                ->success()
                ->send();
            $this->closePurgeModal();
        } catch (AuthorizationException) {
            Notification::make()
                ->title(__('Not allowed'))
                ->body(__('You cannot permanently delete this item.'))
                ->danger()
                ->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title(__('Could not delete'))
                ->body(__('Something went wrong. The item may be protected or still in use.'))
                ->danger()
                ->send();
        }
    }

    protected function buildEditUrl(string $type, Model $model): ?string
    {
        return match ($type) {
            'booking' => BookingResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'guest' => GuestResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'room' => RoomResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'venue' => VenuesResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'staff' => StaffResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'review' => ReviewResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'contact' => ContactUsResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'gallery' => GalleryResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'blog_post' => BlogPostResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'amenity' => AmenityResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'bed_spec' => BedSpecificationResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'blocked_date' => BlockedDateResource::getUrl('edit', ['record' => $model], panel: 'admin'),
            'payment' => $model instanceof Payment && $model->booking_id
                ? BookingResource::getUrl('edit', ['record' => $model->booking_id], panel: 'admin')
                : null,
            'room_blocked' => $model instanceof RoomBlockedDate && $model->room_id
                ? RoomResource::getUrl('edit', ['record' => $model->room_id], panel: 'admin')
                : null,
            'venue_blocked' => $model instanceof VenueBlockedDate && $model->venue_id
                ? VenuesResource::getUrl('edit', ['record' => $model->venue_id], panel: 'admin')
                : null,
            default => null,
        };
    }

    protected function assertRecycleAccess(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    /**
     * @return Collection<int, array{type: string, id: int|string, name: string, location: string, deleted_at: \Illuminate\Support\Carbon|null, edit_url: ?string}>
     */
    protected function collectTrashedItems(): Collection
    {
        $rows = collect();

        $push = function (string $type, Model $model) use ($rows): void {
            $rows->push([
                'type' => $type,
                'id' => $model->getKey(),
                'name' => $this->rowLabel($type, $model),
                'location' => $this->rowLocation($type, $model),
                'deleted_at' => $model->deleted_at,
                'edit_url' => $this->buildEditUrl($type, $model),
            ]);
        };

        Booking::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Booking $m) => $push('booking', $m));
        Guest::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Guest $m) => $push('guest', $m));
        Room::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Room $m) => $push('room', $m));
        Venue::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Venue $m) => $push('venue', $m));
        User::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (User $m) => $push('staff', $m));
        Review::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Review $m) => $push('review', $m));
        ContactUs::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (ContactUs $m) => $push('contact', $m));
        Gallery::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Gallery $m) => $push('gallery', $m));
        BlogPost::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (BlogPost $m) => $push('blog_post', $m));
        Amenity::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (Amenity $m) => $push('amenity', $m));
        BedSpecification::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (BedSpecification $m) => $push('bed_spec', $m));
        BlockedDate::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (BlockedDate $m) => $push('blocked_date', $m));

        Payment::onlyTrashed()->with(['booking' => fn ($q) => $q->withTrashed()])->latest('deleted_at')->limit(200)->get()->each(fn (Payment $m) => $push('payment', $m));
        RoomBlockedDate::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (RoomBlockedDate $m) => $push('room_blocked', $m));
        VenueBlockedDate::onlyTrashed()->latest('deleted_at')->limit(200)->get()->each(fn (VenueBlockedDate $m) => $push('venue_blocked', $m));

        return $rows->sortByDesc(fn (array $r) => $r['deleted_at']?->timestamp ?? 0)->values();
    }

    protected function rowLabel(string $type, Model $model): string
    {
        return match ($type) {
            'booking' => $model instanceof Booking ? (string) $model->reference_number : '—',
            'guest' => $model instanceof Guest ? ($model->full_name ?: $model->email ?: 'Guest #'.$model->getKey()) : '—',
            'room' => $model instanceof Room ? (string) $model->name : '—',
            'venue' => $model instanceof Venue ? (string) $model->name : '—',
            'staff' => $model instanceof User ? (trim((string) $model->name) ?: (string) $model->email) : '—',
            'review' => $model instanceof Review ? (trim((string) $model->title) !== '' ? (string) $model->title : 'Review #'.$model->getKey()) : '—',
            'contact' => $model instanceof ContactUs ? ((string) ($model->full_name ?: $model->email)) : '—',
            'gallery' => $model instanceof Gallery ? ('Gallery #'.$model->getKey()) : '—',
            'blog_post' => $model instanceof BlogPost ? (string) $model->title : '—',
            'amenity' => $model instanceof Amenity ? (string) $model->name : '—',
            'bed_spec' => $model instanceof BedSpecification ? (string) $model->specification : '—',
            'blocked_date' => $model instanceof BlockedDate ? ($model->date?->format('Y-m-d') ?? '—') : '—',
            'payment' => $model instanceof Payment ? ($this->paymentLabel($model)) : '—',
            'room_blocked' => $model instanceof RoomBlockedDate ? ($this->roomBlockedLabel($model)) : '—',
            'venue_blocked' => $model instanceof VenueBlockedDate ? ($this->venueBlockedLabel($model)) : '—',
            default => '—',
        };
    }

    protected function rowLocation(string $type, Model $model): string
    {
        return match ($type) {
            'booking' => __('Bookings'),
            'guest' => __('Guests'),
            'room' => __('Rooms'),
            'venue' => __('Venues'),
            'staff' => __('Staff'),
            'review' => __('Reviews'),
            'contact' => __('Contact'),
            'gallery' => __('Gallery'),
            'blog_post' => __('Blog'),
            'amenity' => __('Amenities'),
            'bed_spec' => __('Bed specifications'),
            'blocked_date' => __('Resort blocked dates'),
            'payment' => __('Booking → Payments'),
            'room_blocked' => __('Room → Blocked dates'),
            'venue_blocked' => __('Venue → Blocked dates'),
            default => __('Other'),
        };
    }

    protected function paymentLabel(Payment $payment): string
    {
        $payment->loadMissing('booking');
        $ref = $payment->booking?->reference_number ?? '—';

        return __('Payment :id (booking :ref)', ['id' => '#'.$payment->getKey(), 'ref' => $ref]);
    }

    protected function roomBlockedLabel(RoomBlockedDate $row): string
    {
        $name = Room::withTrashed()->whereKey($row->room_id)->value('name') ?? __('Unknown room');
        $d = $row->blocked_on?->format('Y-m-d') ?? '—';

        return $name.' · '.$d;
    }

    protected function venueBlockedLabel(VenueBlockedDate $row): string
    {
        $name = Venue::withTrashed()->whereKey($row->venue_id)->value('name') ?? __('Unknown venue');
        $d = $row->blocked_on?->format('Y-m-d') ?? '—';

        return $name.' · '.$d;
    }

    protected function resolveTrashedModel(string $type, int|string $id): Model
    {
        $class = match ($type) {
            'booking' => Booking::class,
            'guest' => Guest::class,
            'room' => Room::class,
            'venue' => Venue::class,
            'staff' => User::class,
            'review' => Review::class,
            'contact' => ContactUs::class,
            'gallery' => Gallery::class,
            'blog_post' => BlogPost::class,
            'amenity' => Amenity::class,
            'bed_spec' => BedSpecification::class,
            'blocked_date' => BlockedDate::class,
            'payment' => Payment::class,
            'room_blocked' => RoomBlockedDate::class,
            'venue_blocked' => VenueBlockedDate::class,
            default => abort(404),
        };

        $model = $class::onlyTrashed()->whereKey($id)->first();
        abort_unless($model, 404);

        return $model;
    }

    /**
     * @param  class-string  $resourceClass
     */
    private static function trashedListUrl(string $resourceClass, string $panel): string
    {
        $base = $resourceClass::getUrl(panel: $panel);
        $query = http_build_query([
            'filters' => [
                'trashed' => [
                    'value' => 0,
                ],
            ],
        ]);

        return $base.(str_contains($base, '?') ? '&' : '?').$query;
    }
}
