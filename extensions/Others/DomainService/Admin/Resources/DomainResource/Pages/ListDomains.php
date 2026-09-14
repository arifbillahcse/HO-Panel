<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\DomainResource\Pages;

use App\Models\Currency;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\DomainResource;
use Paymenter\Extensions\Others\DomainService\Models\Domain;
use Paymenter\Extensions\Others\DomainService\Models\DomainRegistrar;
use Throwable;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    /**
     * Links a domain you already own at the registrar (bought outside
     * Paymenter, e.g. directly at Cosmotown) to a customer account, without
     * going through the paid-order flow. Creates the row as pending and
     * immediately syncs it from the registrar, which is what flips it to
     * active and fills in expiry/nameservers/lock/privacy — the same sync
     * logic the paid-order flow relies on.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import Domain')
                ->icon('ri-download-2-line')
                ->form([
                    TextInput::make('name')
                        ->label('Domain name')
                        ->required()
                        ->placeholder('example.com')
                        ->helperText('The domain must already be registered under your account at the registrar below.')
                        ->rule('regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i')
                        ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state))),
                    Select::make('user_id')
                        ->label('Customer')
                        ->required()
                        ->searchable()
                        ->options(fn () => User::query()->orderBy('first_name')->orderBy('last_name')->limit(50)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"]))
                        ->getSearchResultsUsing(fn (string $search) => User::query()
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"]))
                        ->native(false),
                    Select::make('registrar_id')
                        ->label('Registrar')
                        ->required()
                        ->options(fn () => DomainRegistrar::where('enabled', true)->pluck('name', 'id'))
                        ->native(false),
                    Select::make('currency')
                        ->required()
                        ->options(fn () => Currency::pluck('code', 'code'))
                        ->native(false),
                    Toggle::make('autorenew')->default(true),
                ])
                ->action(function (array $data) {
                    $name = $data['name'];

                    if (Domain::where('name', $name)->where('status', '!=', Domain::STATUS_CANCELLED)->exists()) {
                        Notification::make()->title("{$name} is already in the system.")->danger()->send();

                        return;
                    }

                    $dot = strpos($name, '.');
                    [$sld, $tld] = [substr($name, 0, $dot), substr($name, $dot + 1)];

                    $domain = Domain::create([
                        'user_id' => $data['user_id'],
                        'registrar_id' => $data['registrar_id'],
                        'name' => $name,
                        'sld' => $sld,
                        'tld' => $tld,
                        'currency' => $data['currency'],
                        'status' => Domain::STATUS_PENDING,
                        'autorenew' => $data['autorenew'],
                    ]);

                    try {
                        $domain->driver()->sync($domain);
                        $domain->refresh();

                        if ($domain->status === Domain::STATUS_ACTIVE) {
                            Notification::make()->title("{$name} imported and synced from the registrar.")->success()->send();
                        } else {
                            Notification::make()
                                ->title("{$name} imported, but the registrar did not confirm it as active.")
                                ->body('Check the domain name and registrar, then use Sync from the list.')
                                ->warning()
                                ->send();
                        }
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title("{$name} imported, but the initial sync failed.")
                            ->body($e->getMessage() . ' — use Sync from the list to retry.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
