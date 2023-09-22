<?php

namespace Eminos\StatamicTabs\Fieldtypes;

use Statamic\Fields\Fieldtype;

class TabFieldtype extends Fieldtype
{
    protected $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path fill="currentColor" d="M253.75 166.28L231.46 92a13.91 13.91 0 0 0-13.41-10H208a6 6 0 0 0 0 12h10.05a2 2 0 0 1 1.95 1.42L240 162h-35.54l-21-70a13.91 13.91 0 0 0-13.41-10H160a6 6 0 0 0 0 12h10.05a2 2 0 0 1 1.95 1.42L192 162h-35.54l-21-70a13.91 13.91 0 0 0-13.41-10H38a13.91 13.91 0 0 0-13.46 10L2.28 166.2v.2a2.79 2.79 0 0 0-.1.39a.11.11 0 0 0 0 .05A6 6 0 0 0 8 174h240a6 6 0 0 0 5.75-7.72ZM36 95.42A2 2 0 0 1 38 94h84.1a2 2 0 0 1 1.9 1.43L144 162H16.06Z"></path></svg>';

    /**
     * The blank/default value.
     *
     * @return array
     */
    public function defaultValue()
    {
        return null;
    }

    /**
     * Pre-process the data before it gets sent to the publish page.
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function preProcess($data)
    {
        return $data;
    }

    /**
     * Process the data before it gets saved.
     *
     * @param mixed $data
     * @return array|mixed
     */
    public function process($data)
    {
        return $data;
    }

    protected function configFieldItems(): array
    {
        $configFieldItems = [
            'tab_icon' => [
                'display' => 'Tab Icon',
                'instructions' => 'The icon to display for this tab.',
                'type' => 'icon',
                'width' => 50
            ],
        ];

        if (class_exists('StatamicIconify\Fieldtypes\IconifyFieldtype')) {
            $configFieldItems['tab_iconify_icon'] = [
                'display' => 'Tab Iconify Icon',
                'instructions' => 'The icon to display for this tab. If set, this will override the "Tab Icon" field.',
                'type' => 'iconify',
                'width' => 50
            ];
        }

        return $configFieldItems;
    }
}
