<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label('')
                    ->html()
                    ->searchable()
                    ->formatStateUsing(function (ActivityLog $record): string {
                        $actorName = $record->user?->name ?? 'System';
                        $actorLabel = auth()->id() === $record->user_id ? 'You' : $actorName;
                        $timeAgo = $record->created_at?->diffForHumans() ?? '';
                        $message = self::displayMessage($record);

                        return sprintf(
                            '<div class="leading-tight">
                                <div class="text-[22px] font-medium text-gray-900 dark:text-gray-100">%s</div>
                                <div class="mt-1 text-[18px] text-gray-600 dark:text-gray-400">%s | %s</div>
                            </div>',
                            e($message),
                            e($actorLabel),
                            e($timeAgo)
                        );
                    }),

                TextColumn::make('created_at')
                    ->label('')
                    ->formatStateUsing(fn (ActivityLog $record): string => $record->created_at?->format('H:i m/d/y') ?? '-')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'auth' => 'Auth',
                        'booking' => 'Booking',
                        'review' => 'Review',
                        'resource' => 'Resource',
                        'report' => 'Report',
                    ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name'));
    }

    private static function displayMessage(ActivityLog $record): string
    {
        $message = trim((string) $record->description);
        $actor = trim((string) ($record->user?->name ?? ''));

        if ($record->category === 'auth' && $record->event === 'user.login') {
            return 'logged in.';
        }

        if ($record->category === 'auth' && $record->event === 'user.logout') {
            return 'logged out.';
        }

        if ($record->category === 'review' && $record->event === 'review.approval_changed') {
            return (bool) data_get($record->meta, 'is_approved') ? 'approved a review.' : 'unapproved a review.';
        }

        if ($record->category === 'resource') {
            $model = class_basename((string) data_get($record->meta, 'model', ''));

            return match ([$record->event, $model]) {
                ['resource.created', 'BlockedDate'] => 'created a blocked date.',
                ['resource.updated', 'BlockedDate'] => 'updated a blocked date.',
                ['resource.deleted', 'BlockedDate'] => 'deleted a blocked date.',
                ['resource.created', 'Gallery'] => 'uploaded gallery media.',
                ['resource.updated', 'Gallery'] => 'updated gallery media.',
                ['resource.deleted', 'Gallery'] => 'deleted gallery media.',
                ['resource.created', 'User'] => 'created a new user.',
                ['resource.updated', 'User'] => 'updated a user.',
                ['resource.deleted', 'User'] => 'deleted a user.',
                ['resource.created', 'Room'] => 'created a room.',
                ['resource.updated', 'Room'] => 'updated a room.',
                ['resource.deleted', 'Room'] => 'deleted a room.',
                ['resource.created', 'Amenity'] => 'created an amenity.',
                ['resource.updated', 'Amenity'] => 'updated an amenity.',
                ['resource.deleted', 'Amenity'] => 'deleted an amenity.',
                default => $message,
            };
        }

        if ($actor !== '' && str_starts_with($message, $actor . ' ')) {
            $message = ltrim(substr($message, strlen($actor)));
        }

        if (str_starts_with($message, 'Unknown user ')) {
            $message = ltrim(substr($message, strlen('Unknown user')));
        }

        return $message;
    }
}
