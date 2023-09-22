<?php

namespace Eminos\StatamicTabs;

use Statamic\Providers\AddonServiceProvider;
use Eminos\StatamicTabs\Fieldtypes\TabFieldtype;
 
class ServiceProvider extends AddonServiceProvider
{

    public function __construct()
    {
        $this->vite['hotFile'] = base_path('vendor/eminos/statamic-tabs/dist/vite.hot');

        parent::__construct(app());
    }

    protected $fieldtypes = [
        TabFieldtype::class,
    ];

    // protected $tags = [
    //     IconifyTag::class,
    // ];

    protected $vite = [
        'hotFile' => null, // set in the constructor for reasons
        'publicDirectory' => 'dist',
        'input' => [
            'resources/js/statamic-tabs.js',
            'resources/css/statamic-tabs.css'
        ],
    ];

    public function bootAddon()
    {

        // 
        
    }
}