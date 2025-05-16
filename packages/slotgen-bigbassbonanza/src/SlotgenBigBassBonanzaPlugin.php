<?php

namespace Slotgen\SlotgenBigBassBonanza;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Slotgen\SlotgenBigBassBonanza\Filament\Pages\SlotgenBigBassBonanzaConfigPage;

class SlotgenBigBassBonanzaPlugin implements Plugin
{
    public function getId(): string
    {
        return 'slotgen-bigbassbonanza';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            SlotgenBigBassBonanzaConfigPage::class,
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
        return NavigationGroup::make('Big Bass Bonanza')
            ->items([
                NavigationItem::make('fortune-tiger')
                    ->icon('heroicon-o-key')
                    ->label(fn (): string => 'Setting')
                    ->url(fn (): string => SlotgenBigBassBonanzaConfigPage::getUrl())
                    ->visible(true),
            ]);
    }
}
