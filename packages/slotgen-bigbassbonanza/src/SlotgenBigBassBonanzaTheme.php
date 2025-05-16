<?php

namespace Slotgen\SlotgenBigBassBonanza;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Theme;
use Filament\Support\Color;
use Filament\Support\Facades\FilamentAsset;

class SlotgenBigBassBonanza implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-bigbassbonanza';
    }

    public function register(Panel $panel): void
    {
        FilamentAsset::register([
            Theme::make('slotgen-bigbassbonanza', __DIR__ . '/../resources/dist/slotgen-bigbassbonanza.css'),
        ]);

        $panel
            ->font('DM Sans')
            ->primaryColor(Color::Amber)
            ->secondaryColor(Color::Gray)
            ->warningColor(Color::Amber)
            ->dangerColor(Color::Rose)
            ->successColor(Color::Green)
            ->grayColor(Color::Gray)
            ->theme('slotgen-bigbassbonanza');
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
