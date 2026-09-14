<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\DomainResource\Pages\ListDomains;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Services\TransferPollService;
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
                Action::make('checkTransfer')
                    ->label('Check Transfer')
                    ->icon('ri-time-line')
                    ->visible(fn (Domain $record) => $record->status === Domain::STATUS_TRANSFER_PENDING)
                    ->action(function (Domain $record) {
                        try {
                            app(TransferPollService::class)->poll($record);
                            $record->refresh();

                            match ($record->status) {
                                Domain::STATUS_ACTIVE => Notification::make()->title('Transfer completed — domain is now active')->success()->send(),
                                Domain::STATUS_TRANSFER_FAILED => Notification::make()->title('Registrar reports the transfer failed')->danger()->send(),
                                default => Notification::make()->title('Still pending at the registrar')->body('No change yet — try again later.')->warning()->send(),
                            };
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not check transfer status')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('nameservers')
                    ->label('Nameservers')
                    ->icon('ri-server-line')
                    ->visible(fn (Domain $record) => in_array($record->status, [Domain::STATUS_ACTIVE, Domain::STATUS_GRACE, Domain::STATUS_REDEMPTION], true))
                    ->fillForm(fn (Domain $record) => array_combine(
                        ['ns1', 'ns2', 'ns3', 'ns4', 'ns5'],
                        array_pad(array_slice($record->nameservers ?? [], 0, 5), 5, ''),
                    ))
                    ->form([
                        TextInput::make('ns1')->label('Nameserver 1')->placeholder('ns1.example.com'),
                        TextInput::make('ns2')->label('Nameserver 2')->placeholder('ns2.example.com'),
                        TextInput::make('ns3')->label('Nameserver 3')->placeholder('ns3.example.com'),
                        TextInput::make('ns4')->label('Nameserver 4')->placeholder('ns4.example.com'),
                        TextInput::make('ns5')->label('Nameserver 5')->placeholder('ns5.example.com'),
                    ])
                    ->action(function (Domain $record, array $data) {
                        $values = array_values(array_filter(array_map('trim', [
                            $data['ns1'] ?? '', $data['ns2'] ?? '', $data['ns3'] ?? '', $data['ns4'] ?? '', $data['ns5'] ?? '',
                        ])));

                        if (count($values) < 2) {
                            Notification::make()->title('Enter at least two nameservers.')->danger()->send();

                            return;
                        }

                        try {
                            $record->driver()->saveNameservers($record, $values);
                            $record->update(['nameservers' => $values]);
                            Notification::make()
                                ->title('Nameservers updated.')
                                ->body('Changes can take a few hours to spread across the internet.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not update nameservers')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('forceActive')
                    ->label('Force Active')
                    ->icon('ri-shield-check-line')
                    ->color('warning')
                    ->visible(fn (Domain $record) => in_array($record->status, [Domain::STATUS_PENDING, Domain::STATUS_TRANSFER_PENDING], true))
                    ->requiresConfirmation()
                    ->modalDescription('Marks this domain active without waiting for registrar confirmation. Only use this once you have verified at the registrar that the domain is really live under your account — this does not register or transfer anything itself.')
                    ->action(function (Domain $record) {
                        $record->update([
                            'status' => Domain::STATUS_ACTIVE,
                            'registered_at' => $record->registered_at ?? now(),
                        ]);

                        try {
                            $record->driver()->sync($record);
                        } catch (Throwable $e) {
                            // Status is set either way; a sync failure just means expiry/nameservers stay unrefreshed for now.
                        }

                        Notification::make()->title('Domain marked active')->success()->send();
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
