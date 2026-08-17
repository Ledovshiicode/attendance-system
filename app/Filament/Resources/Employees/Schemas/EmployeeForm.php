<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Login account')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),

                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->maxLength(255)
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Employment')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('employee_number')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('department')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('job_title')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(255),

                            DatePicker::make('hire_date'),

                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->required(),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
