<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Support\ActivityLogger;
use App\Support\BookingDamageSettlement;
use App\Support\BookingLifecycleActions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class RoomChecklistsRelationManager extends RelationManager
{
    public bool $managerOverrideChecklistEdit = false;

    protected static string $relationship = 'roomChecklists';

    protected static ?string $title = 'Room inventory checklist';

    protected static ?string $recordTitleAttribute = 'room_id';

    public function form(Schema $schema): Schema
    {
        /** @var Booking|null $booking */
        $booking = $this->getOwnerRecord() instanceof Booking ? $this->getOwnerRecord() : null;

        return $schema->components([
            Section::make('Booking & inspection')
                ->description('Read-only booking details (left). Optional time when the physical inspection finished (right).')
                ->icon('heroicon-o-identification')
                ->compact()
                ->columns(2)
                ->schema([
                    Group::make([
                        Placeholder::make('booking_reference')
                            ->label('Booking reference')
                            ->content(fn (): string => (string) ($booking?->reference_number ?? '—')),

                        Placeholder::make('guest_name')
                            ->label('Guest')
                            ->content(fn (): string => (string) ($booking?->guest?->full_name ?? '—')),

                        Placeholder::make('inspection_room_name')
                            ->label('Room')
                            ->content(function (?RoomChecklist $record): string {
                                if ($record === null) {
                                    return '—';
                                }
                                $record->loadMissing('room');

                                return (string) ($record->room?->name ?? '—');
                            }),

                        Placeholder::make('guest_contact')
                            ->label('Guest contact')
                            ->content(fn (): string => (string) ($booking?->guest?->contact_num ?? '—')),

                        Placeholder::make('guest_email')
                            ->label('Guest email')
                            ->content(fn (): string => (string) ($booking?->guest?->email ?? '—')),

                        Placeholder::make('inspection_generated_at')
                            ->label('Generated at')
                            ->content(function (?RoomChecklist $record): string {
                                if ($record === null || $record->generated_at === null) {
                                    return '—';
                                }

                                return $record->generated_at
                                    ->timezone(config('app.timezone'))
                                    ->format('M j, Y g:i:s A');
                            }),
                    ])
                        ->columns(3)
                        ->columnSpan(['default' => 2, 'lg' => 1]),

                    DateTimePicker::make('completed_at')
                        ->label('Inspection completed at')
                        ->native(false)
                        ->hint('Optional')
                        ->columnSpan(['default' => 2, 'lg' => 1]),
                ]),

            Section::make('Room checklist')
                ->description('Review each room item clearly. Add a row only for newly installed equipment.')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Placeholder::make('checklist_empty_state')
                        ->label('Setup reminder')
                        ->content('No checklist items are available for this room. Staff can still complete checkout and add notes when needed.')
                        ->visible(fn (?RoomChecklist $record): bool => (int) ($record?->items()->count() ?? 0) === 0),

                    Repeater::make('items')
                        ->relationship('items')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel('Add room item')
                        ->deletable(false)
                        ->collapsible()
                        ->collapsed(function (array $state): bool {
                            $status = (string) ($state['status'] ?? RoomChecklistItem::STATUS_GOOD);

                            return ! in_array($status, [
                                RoomChecklistItem::STATUS_BROKEN,
                                RoomChecklistItem::STATUS_MISSING,
                            ], true);
                        })
                        ->helperText('Rows marked with [ISSUE] need attention (broken or missing).')
                        ->schema([
                            TextInput::make('label')
                                ->label('Item')
                                ->required()
                                ->placeholder('Example: TV remote')
                                ->disabled(fn (callable $get): bool => filled($get('id'))),

                            TextInput::make('charge')
                                ->label('Charge')
                                ->placeholder('Amount')
                                ->prefix('₱')
                                ->disabled(fn (callable $get): bool => filled($get('id'))),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->disabled(fn (callable $get): bool => filled($get('id'))),

                            Group::make([
                                Select::make('status')
                                    ->label('Status')
                                    ->native(false)
                                    ->options([
                                        RoomChecklistItem::STATUS_GOOD => 'Good',
                                        RoomChecklistItem::STATUS_BROKEN => 'Broken',
                                        RoomChecklistItem::STATUS_MISSING => 'Missing',
                                        RoomChecklistItem::STATUS_NOT_APPLICABLE => 'Not in room',
                                    ])
                                    ->default(RoomChecklistItem::STATUS_GOOD)
                                    ->required()
                                    ->live(),
                                Textarea::make('notes')
                                    ->label('Issue notes')
                                    ->rows(2)
                                    ->placeholder('Example: Crack on left side, remote missing.')
                                    ->visible(fn (callable $get): bool => in_array((string) $get('status'), [
                                        RoomChecklistItem::STATUS_BROKEN,
                                        RoomChecklistItem::STATUS_MISSING,
                                    ], true))
                                    ->required(fn (callable $get): bool => in_array((string) $get('status'), [
                                        RoomChecklistItem::STATUS_BROKEN,
                                        RoomChecklistItem::STATUS_MISSING,
                                    ], true)),
                                FileUpload::make('evidence_photo_path')
                                    ->label('Issue photo')
                                    ->disk('public')
                                    ->directory('checklists/evidence')
                                    ->image()
                                    ->imageEditor()
                                    ->visible(fn (callable $get): bool => in_array((string) $get('status'), [
                                        RoomChecklistItem::STATUS_BROKEN,
                                        RoomChecklistItem::STATUS_MISSING,
                                    ], true))
                                    ->required(fn (callable $get): bool => in_array((string) $get('status'), [
                                        RoomChecklistItem::STATUS_BROKEN,
                                        RoomChecklistItem::STATUS_MISSING,
                                    ], true)),
                            ])
                                ->columns(1),
                        ])
                        ->columns(3)
                        ->itemLabel(function (array $state): ?string {
                            $label = trim((string) ($state['label'] ?? ''));
                            if ($label === '') {
                                return null;
                            }

                            $status = (string) ($state['status'] ?? RoomChecklistItem::STATUS_GOOD);
                            if (in_array($status, [RoomChecklistItem::STATUS_BROKEN, RoomChecklistItem::STATUS_MISSING], true)) {
                                $statusText = $status === RoomChecklistItem::STATUS_BROKEN ? 'Broken' : 'Missing';

                                return "[ISSUE] {$label} - {$statusText}";
                            }

                            return $label;
                        }),
                ]),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        /** @var Booking|null $booking */
        $booking = $this->getOwnerRecord() instanceof Booking ? $this->getOwnerRecord() : null;
        if ($booking instanceof Booking) {
            $booking->loadMissing(['guest', 'rooms']);
            BookingLifecycleActions::ensureCompletionRoomChecklists($booking);
        }

        return $table
            ->recordTitle(fn (RoomChecklist $record): string => $record->room?->name ?? 'Room checklist')
            ->columns([
                TextColumn::make('booking.reference_number')
                    ->label('Reference')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('booking.guest.contact_num')
                    ->label('Guest contact')
                    ->placeholder('—')
                    ->url(fn (RoomChecklist $record): ?string => filled($record->booking?->guest?->contact_num)
                        ? 'tel:'.preg_replace('/\s+/', '', (string) $record->booking->guest->contact_num)
                        : null)
                    ->toggleable(),

                TextColumn::make('booking.guest.email')
                    ->label('Guest email')
                    ->placeholder('—')
                    ->url(fn (RoomChecklist $record): ?string => filled($record->booking?->guest?->email)
                        ? 'mailto:'.$record->booking->guest->email
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('generated_at')
                    ->label('Generated')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Inspected')
                    ->dateTime()
                    ->sortable(),

                BadgeColumn::make('damage_count')
                    ->label('Damage / Missing')
                    ->getStateUsing(function (RoomChecklist $record): string {
                        $count = (int) $record->items()
                            ->whereIn('status', [
                                RoomChecklistItem::STATUS_BROKEN,
                                RoomChecklistItem::STATUS_MISSING,
                            ])
                            ->count();

                        return (string) $count;
                    })
                    ->color(fn (string $state): string => ((int) $state) > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => ((int) $state) > 0 ? "{$state} issue(s)" : 'None'),

                TextColumn::make('inspection_overview')
                    ->label('Inspection')
                    ->getStateUsing(function (RoomChecklist $record): string {
                        $items = $record->items()->get(['status']);
                        $total = $items->count();
                        $issues = $items->filter(fn (RoomChecklistItem $item): bool => in_array((string) $item->status, [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))->count();

                        if ($total === 0) {
                            return 'No checklist items';
                        }

                        return "{$total} items · {$issues} issue(s)";
                    }),

                TextColumn::make('issue_preview')
                    ->label('Issue details')
                    ->wrap()
                    ->getStateUsing(function (RoomChecklist $record): string {
                        $issues = $record->items()
                            ->whereIn('status', [
                                RoomChecklistItem::STATUS_BROKEN,
                                RoomChecklistItem::STATUS_MISSING,
                            ])
                            ->get(['label'])
                            ->pluck('label')
                            ->filter()
                            ->values();

                        if ($issues->isEmpty()) {
                            return 'No issues';
                        }

                        $preview = $issues->take(2)->implode(', ');
                        $remaining = $issues->count() - min(2, $issues->count());

                        return $remaining > 0 ? "{$preview} +{$remaining} more" : $preview;
                    }),
            ])
            ->defaultSort('room.name')
            ->emptyStateHeading('No room checklist yet')
            ->emptyStateDescription('Assign room(s) to this booking first, then reopen this tab so inventory checklist is created automatically.')
            ->headerActions([
                Action::make('managerOverrideChecklistEdit')
                    ->label('Manager override: enable edits')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enable manager override?')
                    ->modalDescription('This booking is completed. Enable override to allow checklist edits for corrections.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Override reason')
                            ->rows(3)
                            ->placeholder('Why do you need to reopen this completed checklist?')
                            ->required(),
                    ])
                    ->visible(fn (): bool => $this->isChecklistLocked() && ! $this->managerOverrideChecklistEdit && $this->canUseManagerOverride())
                    ->action(function (array $data): void {
                        $this->managerOverrideChecklistEdit = true;
                        $booking = $this->getOwnerRecord();
                        $actor = auth()->user();
                        $reason = trim((string) ($data['reason'] ?? ''));

                        if ($booking instanceof Booking && $actor !== null) {
                            ActivityLogger::log(
                                category: 'booking',
                                event: 'checklist.override_enabled',
                                description: sprintf(
                                    '%s enabled manager override for checklist edits on booking %s.',
                                    (string) $actor->name,
                                    (string) $booking->reference_number,
                                ),
                                subject: $booking,
                                meta: [
                                    'reference_number' => (string) $booking->reference_number,
                                    'reason' => $reason,
                                    'enabled_by_user_id' => (int) $actor->id,
                                    'enabled_by_user_name' => (string) $actor->name,
                                ],
                                userId: (int) $actor->id,
                            );
                        }

                        Notification::make()
                            ->title('Manager override enabled')
                            ->body('Checklist editing is temporarily unlocked for this session.')
                            ->warning()
                            ->send();
                    }),
                Action::make('markDamagePaid')
                    ->label(fn (): string => 'Record damage payment (₱'.number_format($this->damageOutstandingBalance(), 2).' due)')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Record damage payment')
                    ->modalDescription('Record full or partial payment for damage charges. Settlement completes automatically once fully paid.')
                    ->visible(fn (): bool => $this->canRecordDamagePayment())
                    ->form([
                        Placeholder::make('damage_total')
                            ->label('Total damage charge')
                            ->content(fn (): string => '₱'.number_format($this->damageChargeTotal(), 2)),
                        Placeholder::make('damage_paid_so_far')
                            ->label('Already paid')
                            ->content(fn (): string => '₱'.number_format($this->damagePaidSoFar(), 2)),
                        Placeholder::make('damage_balance')
                            ->label('Outstanding balance')
                            ->content(fn (): string => '₱'.number_format($this->damageOutstandingBalance(), 2)),
                        TextInput::make('amount')
                            ->label('Payment amount')
                            ->numeric()
                            ->required()
                            ->prefix('₱')
                            ->minValue(1)
                            ->maxValue(fn (): float => max(1, $this->damageOutstandingBalance()))
                            ->default(fn (): float => $this->damageOutstandingBalance()),
                        Textarea::make('notes')
                            ->label('Payment notes')
                            ->rows(3)
                            ->placeholder('Optional OR/reference or staff note.'),
                    ])
                    ->action(function (array $data): void {
                        $booking = $this->getOwnerRecord();
                        $actor = auth()->user();
                        if (! $booking instanceof Booking || $actor === null) {
                            return;
                        }

                        $requestedAmount = (float) ($data['amount'] ?? 0);
                        $outstanding = $this->damageOutstandingBalance();
                        $amount = (int) round(min(max($requestedAmount, 0), $outstanding));
                        if ($amount <= 0) {
                            Notification::make()
                                ->title('No damage amount found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $note = trim((string) ($data['notes'] ?? ''));

                        $booking->payments()->create([
                            'payment_type' => Payment::TYPE_DAMAGE,
                            'total_amount' => (int) round($this->damageChargeTotal()),
                            'partial_amount' => $amount,
                            'is_fullypaid' => $amount >= (int) round($outstanding),
                            'provider_status' => 'confirmed',
                            'notes' => $note !== '' ? $note : null,
                        ]);

                        $remainingAfter = max(0.0, $outstanding - $amount);
                        if ($remainingAfter <= 0.009) {
                            BookingDamageSettlement::markSettled($booking, $note, $actor);
                        } else {
                            $booking->update([
                                'has_damage_claim' => true,
                                'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
                                'damage_settlement_notes' => $note !== '' ? $note : null,
                                'damage_settlement_marked_by' => $actor->id,
                                'damage_settlement_marked_at' => now(),
                            ]);
                        }

                        ActivityLogger::log(
                            category: 'booking',
                            event: 'damage.payment_recorded',
                            description: sprintf(
                                '%s recorded damage payment for booking %s.',
                                (string) $actor->name,
                                (string) $booking->reference_number,
                            ),
                            subject: $booking,
                            meta: [
                                'reference_number' => (string) $booking->reference_number,
                                'amount' => $amount,
                                'notes' => $note !== '' ? $note : null,
                                'recorded_by_user_id' => (int) $actor->id,
                                'recorded_by_user_name' => (string) $actor->name,
                            ],
                            userId: (int) $actor->id,
                        );

                        Notification::make()
                            ->title('Damage payment recorded')
                            ->body($remainingAfter <= 0.009
                                ? 'Damage fully paid, marked settled, and included in revenue.'
                                : 'Partial damage payment recorded and included in revenue.')
                            ->success()
                            ->send();
                    }),
                Action::make('markAllItemsGood')
                    ->label('Mark all good')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark all checklist items as good?')
                    ->modalDescription('Use this only when all inspected room items are in good condition.')
                    ->modalSubmitActionLabel('Yes, mark all good')
                    ->visible(fn (): bool => $this->canMutateChecklist())
                    ->action(function (): void {
                        $booking = $this->getOwnerRecord();
                        if (! $booking instanceof Booking) {
                            return;
                        }

                        $rows = BookingLifecycleActions::checkoutChecklistFormItems($booking);
                        $normalized = array_map(static function (array $row): array {
                            $row['status'] = RoomChecklistItem::STATUS_GOOD;
                            $row['notes'] = null;

                            return $row;
                        }, $rows);

                        BookingLifecycleActions::saveCheckoutChecklistItems($booking, $normalized);
                        $this->markChecklistsInspectedNow($booking);

                        Notification::make()
                            ->title('All items marked good')
                            ->body('Checklist statuses were updated and inspection time was recorded.')
                            ->success()
                            ->send();
                    }),
                Action::make('quickInspectIssuesOnly')
                    ->label('Review issues only')
                    ->icon('heroicon-o-funnel')
                    ->color('warning')
                    ->modalHeading('Review broken / missing items')
                    ->modalDescription('Focus only on items currently marked as Broken or Missing.')
                    ->modalSubmitActionLabel('Save issue updates')
                    ->visible(fn (): bool => $this->canMutateChecklist())
                    ->slideOver()
                    ->fillForm(function (): array {
                        $booking = $this->getOwnerRecord();
                        if (! $booking instanceof Booking) {
                            return ['rows' => []];
                        }

                        $rows = BookingLifecycleActions::checkoutChecklistFormItems($booking);
                        $filtered = array_values(array_filter(
                            $rows,
                            fn (array $row): bool => in_array((string) ($row['status'] ?? ''), [
                                RoomChecklistItem::STATUS_BROKEN,
                                RoomChecklistItem::STATUS_MISSING,
                            ], true),
                        ));

                        return ['rows' => $filtered];
                    })
                    ->form($this->quickInspectionForm())
                    ->action(function (array $data): void {
                        $booking = $this->getOwnerRecord();
                        if (! $booking instanceof Booking) {
                            return;
                        }

                        BookingLifecycleActions::saveCheckoutChecklistItems(
                            $booking,
                            is_array($data['rows'] ?? null) ? $data['rows'] : [],
                        );
                        $this->markChecklistsInspectedNow($booking);

                        Notification::make()
                            ->title('Room inspection saved')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Room inventory inspection')
                    ->modalSubmitActionLabel('Save inspection')
                    ->modalWidth('7xl')
                    ->label('Inspect room')
                    ->after(function (RoomChecklist $record): void {
                        if ($record->completed_at === null) {
                            $record->update(['completed_at' => now()]);
                        }
                    })
                    ->visible(fn (): bool => $this->canMutateChecklist()),
            ]);
    }

    private function markChecklistsInspectedNow(Booking $booking): void
    {
        $booking->loadMissing('roomChecklists');

        $checklistIds = $booking->roomChecklists
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($checklistIds === []) {
            return;
        }

        RoomChecklist::query()
            ->whereIn('id', $checklistIds)
            ->update(['completed_at' => now()]);
    }

    /**
     * @return array<int, Component>
     */
    private function quickInspectionForm(): array
    {
        return [
            Placeholder::make('inspection_progress')
                ->label('Inspection summary')
                ->content(function (callable $get): HtmlString {
                    $rows = is_array($get('rows')) ? $get('rows') : [];
                    $total = count($rows);
                    $counts = [
                        RoomChecklistItem::STATUS_GOOD => 0,
                        RoomChecklistItem::STATUS_BROKEN => 0,
                        RoomChecklistItem::STATUS_MISSING => 0,
                        RoomChecklistItem::STATUS_NOT_APPLICABLE => 0,
                    ];

                    foreach ($rows as $row) {
                        $status = (string) ($row['status'] ?? RoomChecklistItem::STATUS_GOOD);
                        if (array_key_exists($status, $counts)) {
                            $counts[$status]++;
                        }
                    }

                    if ($total === 0) {
                        return new HtmlString('<span class="text-sm text-gray-500">No checklist items to show for this view.</span>');
                    }

                    $issues = $counts[RoomChecklistItem::STATUS_BROKEN] + $counts[RoomChecklistItem::STATUS_MISSING];
                    $progressText = sprintf('Reviewed: %d/%d | Issues: %d', $total, $total, $issues);

                    $cards = [
                        [
                            'label' => 'Good',
                            'count' => $counts[RoomChecklistItem::STATUS_GOOD],
                            'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
                        ],
                        [
                            'label' => 'Broken',
                            'count' => $counts[RoomChecklistItem::STATUS_BROKEN],
                            'class' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30',
                        ],
                        [
                            'label' => 'Missing',
                            'count' => $counts[RoomChecklistItem::STATUS_MISSING],
                            'class' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
                        ],
                        [
                            'label' => 'Not in room',
                            'count' => $counts[RoomChecklistItem::STATUS_NOT_APPLICABLE],
                            'class' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-400/30',
                        ],
                    ];

                    $cardsHtml = collect($cards)->map(function (array $card): string {
                        return '<div class="rounded-lg ring-1 p-3 '.$card['class'].'">'
                            .'<div class="text-xs font-medium">'.$card['label'].'</div>'
                            .'<div class="mt-1 text-xl font-bold tabular-nums">'.(int) $card['count'].'</div>'
                            .'</div>';
                    })->implode('');

                    $html = '<div class="space-y-2">'
                        .'<div class="text-sm font-medium text-gray-700 dark:text-gray-200">'.e($progressText).'</div>'
                        .'<div class="grid grid-cols-2 md:grid-cols-4 gap-2">'.$cardsHtml.'</div>'
                        .'</div>';

                    return new HtmlString($html);
                })
                ->columnSpanFull(),
            Repeater::make('rows')
                ->label('Checklist items')
                ->columns(3)
                ->reorderable(false)
                ->addable(false)
                ->deletable(false)
                ->schema([
                    Hidden::make('id'),
                    TextInput::make('room_name')
                        ->label('Room')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('label')
                        ->label('Item')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    ToggleButtons::make('status')
                        ->label('Status')
                        ->options([
                            RoomChecklistItem::STATUS_GOOD => 'Good',
                            RoomChecklistItem::STATUS_BROKEN => 'Broken',
                            RoomChecklistItem::STATUS_MISSING => 'Missing',
                            RoomChecklistItem::STATUS_NOT_APPLICABLE => 'Not in this room',
                        ])
                        ->colors([
                            RoomChecklistItem::STATUS_GOOD => 'success',
                            RoomChecklistItem::STATUS_BROKEN => 'warning',
                            RoomChecklistItem::STATUS_MISSING => 'danger',
                            RoomChecklistItem::STATUS_NOT_APPLICABLE => 'gray',
                        ])
                        ->inline()
                        ->required(),
                    Textarea::make('notes')
                        ->label('Issue notes')
                        ->rows(2)
                        ->placeholder('Required for broken or missing items.')
                        ->visible(fn (callable $get): bool => in_array((string) $get('status'), [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))
                        ->required(fn (callable $get): bool => in_array((string) $get('status'), [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))
                        ->columnSpanFull(),
                    FileUpload::make('evidence_photo_path')
                        ->label('Issue photo')
                        ->disk('public')
                        ->directory('checklists/evidence')
                        ->image()
                        ->imageEditor()
                        ->visible(fn (callable $get): bool => in_array((string) $get('status'), [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))
                        ->required(fn (callable $get): bool => in_array((string) $get('status'), [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))
                        ->columnSpanFull(),
                ])
                ->itemLabel(fn (array $state): ?string => isset($state['room_name'], $state['label'])
                    ? "{$state['room_name']} - {$state['label']}"
                    : null),
        ];
    }

    private function canMutateChecklist(): bool
    {
        return ! $this->isChecklistLocked() || $this->managerOverrideChecklistEdit;
    }

    private function isChecklistLocked(): bool
    {
        $booking = $this->getOwnerRecord();

        return $booking instanceof Booking
            && (string) $booking->booking_status === Booking::BOOKING_STATUS_COMPLETED;
    }

    private function canUseManagerOverride(): bool
    {
        $role = (string) (auth()->user()?->role ?? '');

        return $role === 'admin';
    }

    private function canRecordDamagePayment(): bool
    {
        $booking = $this->getOwnerRecord();
        if (! $booking instanceof Booking || $booking->trashed()) {
            return false;
        }

        if ((string) $booking->damage_settlement_status !== Booking::DAMAGE_SETTLEMENT_STATUS_PENDING) {
            return false;
        }

        if (! $this->hasInspectedChecklist()) {
            return false;
        }

        return $this->damageOutstandingBalance() > 0;
    }

    private function hasInspectedChecklist(): bool
    {
        $booking = $this->getOwnerRecord();
        if (! $booking instanceof Booking) {
            return false;
        }

        $booking->loadMissing('roomChecklists');

        return $booking->roomChecklists->contains(
            fn (RoomChecklist $checklist): bool => $checklist->completed_at !== null
        );
    }

    private function damageChargeTotal(): float
    {
        $booking = $this->getOwnerRecord();
        if (! $booking instanceof Booking) {
            return 0.0;
        }

        $booking->loadMissing('roomChecklists.items');

        return (float) $booking->roomChecklists
            ->flatMap(fn (RoomChecklist $checklist) => $checklist->items)
            ->filter(fn (RoomChecklistItem $item): bool => in_array((string) $item->status, [
                RoomChecklistItem::STATUS_BROKEN,
                RoomChecklistItem::STATUS_MISSING,
            ], true))
            ->sum(function (RoomChecklistItem $item): float {
                $quantity = max(1, (int) ($item->quantity ?? 1));

                return $this->parseMoneyToFloat((string) ($item->charge ?? '0')) * $quantity;
            });
    }

    private function damagePaidSoFar(): float
    {
        $booking = $this->getOwnerRecord();
        if (! $booking instanceof Booking) {
            return 0.0;
        }

        $booking->loadMissing('payments');

        return (float) $booking->payments
            ->where('payment_type', Payment::TYPE_DAMAGE)
            ->sum('partial_amount');
    }

    private function damageOutstandingBalance(): float
    {
        return max(0.0, round($this->damageChargeTotal() - $this->damagePaidSoFar(), 2));
    }

    private function parseMoneyToFloat(string $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value);
        if (! is_string($normalized) || $normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0.0;
        }

        return max(0, (float) $normalized);
    }
}
