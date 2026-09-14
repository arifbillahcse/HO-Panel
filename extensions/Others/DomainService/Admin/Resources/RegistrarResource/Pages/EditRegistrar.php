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

    // Never surface a stored secret back into the form.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (CreateRegistrar::CREDENTIAL_FIELDS as $field) {
            unset($data[$field]);
        }
        unset($data['credentials']);

        return $data;
    }

    // Merge only the credential fields that were actually re-entered, so
    // leaving a field blank keeps its stored value.
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existing = (array) ($this->record->credentials ?? []);

        foreach (CreateRegistrar::CREDENTIAL_FIELDS as $field) {
            if (filled($data[$field] ?? null)) {
                $existing[$field] = $data[$field];
            }
            unset($data[$field]);
        }
        $data['credentials'] = $existing;

        return $data;
    }
}
