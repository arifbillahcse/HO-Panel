<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource;

class CreateRegistrar extends CreateRecord
{
    protected static string $resource = RegistrarResource::class;

    /** Credential field names across all drivers, packed into credentials. */
    public const CREDENTIAL_FIELDS = ['apikey', 'reseller_id', 'api_key'];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $credentials = [];
        foreach (self::CREDENTIAL_FIELDS as $field) {
            if (filled($data[$field] ?? null)) {
                $credentials[$field] = $data[$field];
            }
            unset($data[$field]);
        }
        $data['credentials'] = $credentials;

        return $data;
    }
}
