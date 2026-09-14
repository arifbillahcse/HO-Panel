<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Paymenter\Extensions\Others\DomainService\Services\DomainOrderService;

/**
 * Lets staff place a domain order on a customer's behalf — register or
 * transfer — the same way the storefront does (same pricing, same invoice),
 * with the option to mark it paid on the spot so it provisions immediately
 * instead of waiting for the customer to pay.
 */
class OrderDomain extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'Domains';

    protected static ?string $navigationLabel = 'Order for Customer';

    protected static ?string $title = 'Order Domain for Customer';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-user-add-line';

    protected string $view = 'domainservice::admin.order-domain';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'action' => 'register',
            'years' => 1,
            'mark_paid' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('user_id')
                        ->label('Customer')
                        ->required()
                        ->searchable()
                        ->options(fn () => User::query()->orderBy('name')->limit(50)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"]))
                        ->getSearchResultsUsing(fn (string $search) => User::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"]))
                        ->native(false),
                    Select::make('action')
                        ->label('Order type')
                        ->required()
                        ->live()
                        ->options([
                            'register' => 'Register a new domain',
                            'transfer' => 'Transfer an existing domain in',
                        ])
                        ->native(false),
                    TextInput::make('name')
                        ->label('Domain name')
                        ->required()
                        ->placeholder('example.com')
                        ->rule('regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i')
                        ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state))),
                    TextInput::make('auth_code')
                        ->label('Authorisation (EPP) code')
                        ->visible(fn (Get $get) => $get('action') === 'transfer')
                        ->required(fn (Get $get) => $get('action') === 'transfer'),
                    TextInput::make('years')
                        ->label('Years')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(10)
                        ->required(),
                    Select::make('currency')
                        ->required()
                        ->options(fn () => Currency::pluck('code', 'code'))
                        ->native(false),
                    Toggle::make('mark_paid')
                        ->label('Mark invoice as paid & provision immediately')
                        ->helperText('Leave off to send the customer a normal invoice to pay first. Turn on when they already paid outside the system, or for a courtesy domain.'),
                ])
                    ->livewireSubmitHandler('placeOrder')
                    ->footer([
                        Actions::make([
                            Action::make('placeOrder')
                                ->label('Place order')
                                ->submit('placeOrder'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function placeOrder(): void
    {
        Gate::authorize('has-permission', 'admin.domains.manage');

        $data = $this->form->getState();

        try {
            $user = User::findOrFail($data['user_id']);
            $service = app(DomainOrderService::class);

            $invoice = $data['action'] === 'transfer'
                ? $service->transfer($user, $data['name'], $data['currency'], $data['auth_code'], (int) $data['years'])
                : $service->register($user, $data['name'], $data['currency'], (int) $data['years']);

            if ($data['mark_paid']) {
                $invoice->update(['status' => Invoice::STATUS_PAID]);
            }

            Notification::make()
                ->title($data['mark_paid'] ? 'Order placed and marked paid — provisioning now.' : 'Order placed. Invoice sent to the customer to pay.')
                ->success()
                ->actions([
                    NotificationAction::make('view')
                        ->label('View invoice')
                        ->url(InvoiceResource::getUrl('edit', ['record' => $invoice]))
                        ->button(),
                ])
                ->send();

            $this->form->fill([
                'action' => 'register',
                'years' => 1,
                'mark_paid' => false,
            ]);
        } catch (Exception $e) {
            Notification::make()->title('Could not place order')->body($e->getMessage())->danger()->send();
        }
    }

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user && $user->hasPermission('admin.domains.manage');
    }
}
