<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources;

use App\Models\Currency;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages\CreateTldPricing;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages\EditTldPricing;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages\ListTldPricing;
use Paymenter\Extensions\Others\DomainService\Models\DomainRegistrar;
use Paymenter\Extensions\Others\DomainService\Models\DomainTld;

/**
 * The single pricing screen: one row per TLD, edited to set its registrar and
 * its register / renew / transfer price in every currency you sell in.
 */
class TldPricingResource extends Resource
{
    protected static ?string $model = DomainTld::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-price-tag-3-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Domains';

    protected static ?string $navigationLabel = 'TLD Pricing';

    protected static ?string $modelLabel = 'TLD';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('tld')
                ->label('TLD')
                ->required()
                ->maxLength(63)
                ->placeholder('com')
                ->helperText('Without the leading dot, lower-case. e.g. com, net, co.uk')
                ->dehydrateStateUsing(fn ($state) => ltrim(strtolower(trim((string) $state)), '.')),
            Select::make('registrar_id')
                ->label('Registrar')
                ->required()
                ->options(fn () => DomainRegistrar::where('enabled', true)->pluck('name', 'id'))
                ->native(false),
            TextInput::make('min_years')->numeric()->default(1)->minValue(1)->maxValue(10)->required(),
            TextInput::make('max_years')->numeric()->default(10)->minValue(1)->maxValue(10)->required(),
            TextInput::make('grace_days')->numeric()->default(0)->minValue(0)
                ->helperText('Days after expiry the domain can still renew normally.'),
            TextInput::make('redemption_days')->numeric()->default(0)->minValue(0)
                ->helperText('Days after grace where recovery costs a premium.'),
            Toggle::make('enabled')->default(true),
            Repeater::make('pricing')
                ->relationship()
                ->label('Pricing per currency')
                ->columnSpanFull()
                ->schema([
                    Select::make('currency')
                        ->required()
                        ->options(fn () => Currency::pluck('code', 'code'))
                        ->native(false),
                    TextInput::make('register_price')->numeric()->required()->default(0)->minValue(0),
                    TextInput::make('renew_price')->numeric()->required()->default(0)->minValue(0),
                    TextInput::make('transfer_price')->numeric()->required()->default(0)->minValue(0),
                    TextInput::make('redemption_fee')->numeric()->required()->default(0)->minValue(0)
                        ->helperText('Added on top of the renew price if a customer recovers the domain during redemption.'),
                ])
                ->columns(4)
                ->addActionLabel('Add a currency')
                ->helperText('Set register, renew, transfer and the redemption recovery fee for each currency you sell in.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tld')->label('TLD')->prefix('.')->searchable()->sortable(),
                TextColumn::make('registrar.name')->label('Registrar')->sortable(),
                TextColumn::make('pricing_count')->counts('pricing')->label('Currencies'),
                IconColumn::make('enabled')->boolean()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTldPricing::route('/'),
            'create' => CreateTldPricing::route('/create'),
            'edit' => EditTldPricing::route('/{record}/edit'),
        ];
    }
}
