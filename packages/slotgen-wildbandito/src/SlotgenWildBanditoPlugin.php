<?php

namespace Slotgen\SlotgenWildBandito;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Slotgen\SlotgenWildBandito\Filament\Pages\SlotgenWildBanditoConfigPage;

class SlotgenWildBanditoPlugin implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-wildbandito';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SlotgenWildBanditoConfigPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getNavigationItems(): NavigationGroup
    {
        return NavigationGroup::make('Wild Bandito')
            ->items([
                NavigationItem::make('fortune-tiger')
                    ->icon('heroicon-o-key')
                    ->label(fn (): string => 'Setting')
                    ->url(fn (): string => SlotgenWildBanditoConfigPage::getUrl())
                    ->visible(true),
            ]);
    }
}
