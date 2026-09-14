<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource;

class EditTldPricing extends EditRecord
{
    protected static string $resource = TldPricingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
