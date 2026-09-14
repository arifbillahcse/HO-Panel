<?php

namespace Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Paymenter\Extensions\Others\DomainService\Admin\Resources\RegistrarResource;

class EditRegistrar extends EditRecord
{
    protected static string $resource = RegistrarResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    // Never surface the stored key back into the form.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['apikey'], $data['credentials']);

        return $data;
    }

    // Only overwrite the key when a new one was actually entered.
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['apikey'] ?? null)) {
            $data['credentials'] = ['apikey' => $data['apikey']];
        }
        unset($data['apikey']);

        return $data;
    }
}
