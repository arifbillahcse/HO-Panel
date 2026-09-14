<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource;

class ListRegistrars extends ListRecords
{
    protected static string $resource = RegistrarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
