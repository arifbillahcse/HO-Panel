<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource;

class CreateRegistrar extends CreateRecord
{
    protected static string $resource = RegistrarResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['credentials'] = ['apikey' => $data['apikey'] ?? ''];
        unset($data['apikey']);

        return $data;
    }
}
