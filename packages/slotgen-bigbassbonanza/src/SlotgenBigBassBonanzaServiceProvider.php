<?php

namespace Slotgen\SlotgenBigBassBonanza;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Slotgen\SlotgenBigBassBonanza\Commands\SimulateSpinlog;
use Slotgen\SlotgenBigBassBonanza\Commands\SlotgenBigBassBonanzaCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SlotgenBigBassBonanzaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'slotgen-bigbassbonanza';

    public static string $viewNamespace = 'slotgen-bigbassbonanza';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasRoute('api-client')
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('slotgen/slotgen-bigbassbonanza');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            // foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
            //     $this->publishes([
            //         $file->getRealPath() => base_path("stubs/slotgen-bigbassbonanza/{$file->getFilename()}"),
            //     ], 'slotgen-bigbassbonanza-stubs');
            // }
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'slotgen/slotgen-bigbassbonanza';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('slotgen-bigbassbonanza', __DIR__ . '/../resources/dist/components/slotgen-bigbassbonanza.js'),
            // Css::make('slotgen-bigbassbonanza-styles', __DIR__ . '/../resources/dist/slotgen-bigbassbonanza.css'),
            // Js::make('slotgen-bigbassbonanza-scripts', __DIR__ . '/../resources/dist/slotgen-bigbassbonanza.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            // SlotgenBigBassBonanzaCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_slotgen_big_bass_bonanza_configs_table',
            'create_big_bass_bonanza_players_table',
            'create_big_bass_bonanza_spin_logs_table',
            'add_value_buy_feature_to_slotgen_big_bass_bonanza_configs',
            'add_rtp_to_slotgen_big_bass_bonanza_spin_logs',
        ];
    }
}
