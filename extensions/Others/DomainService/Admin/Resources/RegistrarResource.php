<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DomainService\Models\DomainRegistrar;
use Paymenter\Extensions\Others\DomainService\Registrars\RegistrarManager;
use Throwable;

class RegistrarResource extends Resource
{
    protected static ?string $model = DomainRegistrar::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-server-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Domains';

    protected static ?string $navigationLabel = 'Registrars';

    protected static ?string $modelLabel = 'registrar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Cosmotown (live)'),
            Select::make('driver')
                ->required()
                ->options(fn () => app(RegistrarManager::class)->available())
                ->native(false),
            // Virtual field. Packed into the encrypted credentials array by the
            // Create/Edit pages, and never re-displayed — an admin who opens
            // the form cannot read the stored key, only replace it.
            TextInput::make('apikey')
                ->label('API key')
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->placeholder('Leave blank to keep the current key')
                ->helperText('Stored encrypted. Paste the reseller API key only, never a password.'),
            Toggle::make('sandbox')
                ->helperText('Use the registrar sandbox. Sandbox keys differ from live ones.'),
            Toggle::make('enabled')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('driver')->badge()->sortable(),
                IconColumn::make('sandbox')->boolean(),
                IconColumn::make('enabled')->boolean()->sortable(),
                TextColumn::make('tlds_count')->counts('tlds')->label('TLDs'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test connection')
                    ->icon('ri-plug-line')
                    ->action(function (DomainRegistrar $record) {
                        try {
                            $ok = $record->driver()->testConnection();
                            Notification::make()
                                ->title($ok ? 'Connection successful' : 'Connection failed')
                                ->{$ok ? 'success' : 'danger'}()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Connection failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrars::route('/'),
            'create' => Pages\CreateRegistrar::route('/create'),
            'edit' => Pages\EditRegistrar::route('/{record}/edit'),
        ];
    }
}
