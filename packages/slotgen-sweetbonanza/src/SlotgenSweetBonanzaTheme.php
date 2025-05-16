<?php

namespace Slotgen\SlotgenSweetBonanza;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Theme;
use Filament\Support\Color;
use Filament\Support\Facades\FilamentAsset;

class SlotgenSweetBonanza implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-sweetbonanza';
    }

    public function register(Panel $panel): void
    {
        FilamentAsset::register([
            Theme::make('slotgen-sweetbonanza', __DIR__ . '/../resources/dist/slotgen-sweetbonanza.css'),
        ]);

        $panel
            ->font('DM Sans')
            ->primaryColor(Color::Amber)
            ->secondaryColor(Color::Gray)
            ->warningColor(Color::Amber)
            ->dangerColor(Color::Rose)
            ->successColor(Color::Green)
            ->grayColor(Color::Gray)
            ->theme('slotgen-sweetbonanza');
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
