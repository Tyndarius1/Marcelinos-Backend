<?php

namespace App\Filament\Resources\ContactUs\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Mail\ContactReply;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class ContactUsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'gray',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'danger',
                    }),

                TextColumn::make('replied_at')
                    ->label('Replied At')
                    ->dateTime()
                    ->placeholder('Not replied yet')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->trashed() && in_array($record->status, ['new', 'in_progress']))
                    ->form([
                        Select::make('method')
                            ->label('Reply Method')
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS (Coming Soon)',
                            ])
                            ->default('email')
                            ->required(),
                        Textarea::make('message')
                            ->label('Reply Message')
                            ->required()
                            ->rows(4)
                            ->placeholder('Compose your reply to the customer...'),
                    ])
                    ->action(function (array $data, $record) {
                        // Handle the reply logic here
                        if ($data['method'] === 'email') {
                            // Send email reply using mailable
                            Mail::to($record->email)->send(new ContactReply($record, $data['message']));

                            // Update status to in_progress if it's new, and set replied_at
                            $record->update([
                                'status' => $record->status === 'new' ? 'in_progress' : $record->status,
                                'replied_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Reply sent successfully!')
                                ->success()
                                ->send();
                        } else {
                            // SMS functionality (placeholder for future)
                            Notification::make()
                                ->title('SMS functionality coming soon!')
                                ->warning()
                                ->send();
                        }
                    })
                    ->modalHeading('Reply to Customer')
                    ->modalSubmitActionLabel('Send Reply'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
