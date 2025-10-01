<?php

namespace Blackpig\FilamentComponentPicker;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentComponentPickerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'blackpig-component-picker';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('blackpig/filament-component-picker');
            });
    }
}
