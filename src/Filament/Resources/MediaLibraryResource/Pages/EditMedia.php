<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;
use RaiseStudio\FilamentMediaLibrary\Models\Media;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->disabled(fn (Media $record): bool => $record->references()->exists())
                ->tooltip(fn (Media $record): ?string => $record->references()->exists()
                    ? __('media-library::fields.in_use')
                    : null),
        ];
    }
}
