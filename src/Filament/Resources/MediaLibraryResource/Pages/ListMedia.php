<?php

namespace RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use RaiseStudio\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
