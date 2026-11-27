<?php

/*
| FinEvents-specific settings that are NOT secrets.
| The module list drives the navigation shell; later phases switch modules on
| and permission checks decide who sees each one.
*/

return [

    'brand' => [
        'blue' => '#002F55',
        'orange' => '#F57634',
    ],

    'modules' => [
        ['key' => 'crm', 'label' => 'CRM', 'phase' => 2],
        ['key' => 'time', 'label' => 'Time', 'phase' => 3],
        ['key' => 'projects', 'label' => 'Projects', 'phase' => 4],
        ['key' => 'finance', 'label' => 'Finance', 'phase' => 5],
        ['key' => 'time-off', 'label' => 'Time off', 'phase' => 6],
        ['key' => 'wiki', 'label' => 'Wiki', 'phase' => 7],
        ['key' => 'brand-tools', 'label' => 'Brand tools', 'phase' => null], // after v1
    ],

];
