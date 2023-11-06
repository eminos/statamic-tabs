<?php

namespace Eminos\StatamicTabs;

use Statamic\Providers\AddonServiceProvider;
use Eminos\StatamicTabs\Fieldtypes\TabFieldtype;
 
class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [
        TabFieldtype::class,
    ];

    protected $vite = [
        'hotFile' => __DIR__ . '/../dist/vite.hot',
        'publicDirectory' => 'dist',
        'input' => [
            'resources/js/statamic-tabs.js',
            'resources/css/statamic-tabs.css'
        ],
    ];
}