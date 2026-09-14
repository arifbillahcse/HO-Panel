<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource;

class ListTldPricing extends ListRecords
{
    protected static string $resource = TldPricingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add TLD')];
    }
}
