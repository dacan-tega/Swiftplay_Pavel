<?php

namespace Slotgen\SlotgenWildBandito;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Theme;
use Filament\Support\Color;
use Filament\Support\Facades\FilamentAsset;

class SlotgenWildBandito implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-wildbandito';
    }

    public function register(Panel $panel): void
    {
        FilamentAsset::register([
            Theme::make('slotgen-wildbandito', __DIR__ . '/../resources/dist/slotgen-wildbandito.css'),
        ]);

        $panel
            ->font('DM Sans')
            ->primaryColor(Color::Amber)
            ->secondaryColor(Color::Gray)
            ->warningColor(Color::Amber)
            ->dangerColor(Color::Rose)
            ->successColor(Color::Green)
            ->grayColor(Color::Gray)
            ->theme('slotgen-wildbandito');
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
