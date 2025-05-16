<?php

namespace Slotgen\SlotgenSweetBonanza;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Slotgen\SlotgenSweetBonanza\Filament\Pages\SlotgenSweetBonanzaConfigPage;

class SlotgenSweetBonanzaPlugin implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-sweetbonanza';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SlotgenSweetBonanzaConfigPage::class,
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
        return NavigationGroup::make('Sweet Bonanza')
            ->items([
                NavigationItem::make('fortune-tiger')
                    ->icon('heroicon-o-key')
                    ->label(fn (): string => 'Setting')
                    ->url(fn (): string => SlotgenSweetBonanzaConfigPage::getUrl())
                    ->visible(true),
            ]);
    }
}
