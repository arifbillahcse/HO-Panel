<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\TldPricingResource;

class CreateTldPricing extends CreateRecord
{
    protected static string $resource = TldPricingResource::class;
}
