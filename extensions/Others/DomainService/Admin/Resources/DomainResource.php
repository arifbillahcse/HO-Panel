<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\DomainResource\Pages\ListDomains;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Throwable;

/**
 * Read-mostly admin view of every domain. Domains are created by orders, not
 * here, so there is no create form — only inspection and manual sync/renew.
 */
class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string|\BackedEnum|null $navigationIcon = 'ri-global-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Domains';

    protected static ?string $navigationLabel = 'Domains';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Customer')->searchable()->sortable(),
                TextColumn::make('registrar.name')->label('Registrar')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Domain::STATUS_ACTIVE => 'success',
                        Domain::STATUS_PENDING, Domain::STATUS_TRANSFER_PENDING, Domain::STATUS_GRACE => 'warning',
                        Domain::STATUS_TRANSFER_FAILED, Domain::STATUS_EXPIRED, Domain::STATUS_REDEMPTION => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('expires_at')->dateTime('M d, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Domain::STATUS_ACTIVE => 'Active',
                    Domain::STATUS_PENDING => 'Pending',
                    Domain::STATUS_GRACE => 'Grace period',
                    Domain::STATUS_REDEMPTION => 'Redemption',
                    Domain::STATUS_TRANSFER_PENDING => 'Transfer pending',
                    Domain::STATUS_TRANSFER_FAILED => 'Transfer failed',
                    Domain::STATUS_EXPIRED => 'Expired (lost)',
                    Domain::STATUS_CANCELLED => 'Cancelled',
                ]),
                SelectFilter::make('registrar')->relationship('registrar', 'name'),
            ])
            ->recordActions([
                Action::make('sync')
                    ->label('Sync')
                    ->icon('ri-refresh-line')
                    ->action(function (Domain $record) {
                        try {
                            $record->driver()->sync($record);
                            Notification::make()->title('Synced from registrar')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDomains::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
