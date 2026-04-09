<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class StaffForm
{
    public static function configure(Schema $schema, bool $withOtp = false): Schema
    {
        return $schema->schema([
            Section::make('Staff Details')
                ->description('Set identity and login credentials for the staff account.')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->placeholder('e.g. John Doe')
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->placeholder('staff@example.com')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->helperText('Leave blank during edit to keep the current password.')
                        ->required(fn ($record) => $record === null)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state)),

                    TextInput::make('otp')
                        ->label('Verification Code')
                        ->tel()
                        ->maxLength(6)
                        ->minLength(6)
                        ->rule('regex:/^\d{6}$/')
                        ->placeholder('Enter 6-digit OTP')
                        ->autocomplete(false)
                        ->helperText('Step 1: Enter email. Step 2: Click "Send OTP" (top-right). Step 3: Enter code, then create staff.')
                        ->visible(fn () => $withOtp),
                    Hidden::make('role')
                        ->default('staff')
                        ->dehydrated(true),
                ])
                ->columns(2),

            Section::make('Access & Privileges')
                ->description('Choose what this staff member can manage inside admin.')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Active account')
                        ->helperText('Inactive staff cannot log in to any panel.')
                        ->default(true),

                    CheckboxList::make('permissions')
                        ->label('Allowed actions')
                        ->options(User::staffPrivilegeOptions())
                        ->default([])
                        ->rules([
                            'array',
                            fn (): \Closure => function ($attribute, $value, $fail): void {
                                $value = is_array($value) ? $value : [];
                                $allowed = array_keys(User::staffPrivilegeOptions());

                                foreach ($value as $selected) {
                                    if (! is_string($selected)) {
                                        $fail('Invalid permission value.');
                                        return;
                                    }

                                    if (! in_array($selected, $allowed, true)) {
                                        $fail('One or more selected permissions are invalid.');
                                        return;
                                    }
                                }
                            },
                        ])
                        ->columns(3)
                        ->searchable(),
                ]),
        ]);
    }
}
