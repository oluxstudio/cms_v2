<?php

/*
 * Estimator trade catalog — the single source of truth for the Estimator module.
 *
 * Each trade declares its INPUTS and its PRICING + TIME model. The engine
 * (App\Services\EstimatorEngine) computes:
 *
 *   cost  = (base_cents + Σ input.per_unit_cents × qty [+ toggle add_cents])
 *           × Π select/toggle multipliers × site rate_multiplier
 *   hours = base_hours + Σ input.per_unit_hours × qty  (× select multiplier)
 *   completion = hours ÷ crew, rolled into working days (8h)
 *
 * Input types: number (qty), select (one choice, price/time multiplier),
 * toggle (on/off — multiplier and/or flat add).
 * All money in CENTS. A site can scale everything with the feature's
 * rate_multiplier setting (percent, default 100).
 */
return [

    'range_pct' => 12,     // estimate shown as ±12% range
    'workday_hours' => 8,

    'trades' => [

        'cleaner' => [
            'name' => 'Cleaning', 'icon' => '🧹',
            'base_cents' => 3500, 'base_hours' => 1.5, 'crew' => 1,
            'inputs' => [
                ['key' => 'bedrooms',  'label' => 'Bedrooms',  'type' => 'number', 'min' => 0, 'max' => 12, 'default' => 2, 'per_unit_cents' => 1500, 'per_unit_hours' => 0.5],
                ['key' => 'bathrooms', 'label' => 'Bathrooms', 'type' => 'number', 'min' => 0, 'max' => 8,  'default' => 1, 'per_unit_cents' => 2000, 'per_unit_hours' => 0.75],
                ['key' => 'deep',      'label' => 'Deep clean', 'type' => 'toggle', 'multiplier' => 1.5],
            ],
        ],

        'landscaper' => [
            'name' => 'Landscaping', 'icon' => '🌿',
            'base_cents' => 4000, 'base_hours' => 2, 'crew' => 2,
            'inputs' => [
                ['key' => 'lawn_m2',  'label' => 'Lawn / garden area', 'unit' => 'm²', 'type' => 'number', 'min' => 0, 'max' => 2000, 'default' => 50, 'per_unit_cents' => 80, 'per_unit_hours' => 0.02],
                ['key' => 'hedges',   'label' => 'Hedges / trees to trim', 'type' => 'number', 'min' => 0, 'max' => 40, 'default' => 2, 'per_unit_cents' => 1200, 'per_unit_hours' => 0.5],
                ['key' => 'clearance', 'label' => 'Green waste clearance', 'type' => 'toggle', 'multiplier' => 1.3],
            ],
        ],

        'laundry' => [
            'name' => 'Laundry', 'icon' => '🧺',
            'base_cents' => 1500, 'base_hours' => 3, 'crew' => 1,
            'inputs' => [
                ['key' => 'loads',    'label' => 'Wash loads', 'type' => 'number', 'min' => 1, 'max' => 30, 'default' => 2, 'per_unit_cents' => 900, 'per_unit_hours' => 1],
                ['key' => 'ironing',  'label' => 'Ironing', 'type' => 'toggle', 'multiplier' => 1.4],
                ['key' => 'delivery', 'label' => 'Pickup & delivery', 'type' => 'toggle', 'add_cents' => 500],
            ],
        ],

        'carpenter' => [
            'name' => 'Carpentry', 'icon' => '🪚',
            'base_cents' => 6000, 'base_hours' => 2, 'crew' => 1,
            'inputs' => [
                ['key' => 'job', 'label' => 'Job type', 'type' => 'select', 'options' => [
                    ['key' => 'repair',    'label' => 'Furniture repair', 'multiplier' => 0.8],
                    ['key' => 'shelving',  'label' => 'Shelving / storage', 'multiplier' => 1.0],
                    ['key' => 'doors',     'label' => 'Doors & frames', 'multiplier' => 1.2],
                    ['key' => 'flooring',  'label' => 'Flooring', 'multiplier' => 2.0],
                ]],
                ['key' => 'units', 'label' => 'Items / rooms', 'type' => 'number', 'min' => 1, 'max' => 20, 'default' => 1, 'per_unit_cents' => 4500, 'per_unit_hours' => 1.5],
            ],
        ],

        'mover' => [
            'name' => 'Moving', 'icon' => '🚚',
            'base_cents' => 9000, 'base_hours' => 3, 'crew' => 2,
            'inputs' => [
                ['key' => 'bedrooms', 'label' => 'Bedrooms', 'type' => 'number', 'min' => 0, 'max' => 10, 'default' => 2, 'per_unit_cents' => 6000, 'per_unit_hours' => 1],
                ['key' => 'distance', 'label' => 'Distance', 'unit' => 'km', 'type' => 'number', 'min' => 1, 'max' => 1500, 'default' => 20, 'per_unit_cents' => 120, 'per_unit_hours' => 0.03],
                ['key' => 'floors',   'label' => 'Floors without lift', 'type' => 'number', 'min' => 0, 'max' => 10, 'default' => 0, 'per_unit_cents' => 2500, 'per_unit_hours' => 0.5],
            ],
        ],

        'builder' => [
            'name' => 'Building', 'icon' => '🧱',
            'base_cents' => 80000, 'base_hours' => 16, 'crew' => 3,
            'inputs' => [
                ['key' => 'project', 'label' => 'Project', 'type' => 'select', 'options' => [
                    ['key' => 'repair',     'label' => 'Structural repair', 'multiplier' => 0.6],
                    ['key' => 'renovation', 'label' => 'Renovation', 'multiplier' => 1.6],
                    ['key' => 'extension',  'label' => 'Extension', 'multiplier' => 3.0],
                ]],
                ['key' => 'area_m2', 'label' => 'Area', 'unit' => 'm²', 'type' => 'number', 'min' => 1, 'max' => 500, 'default' => 20, 'per_unit_cents' => 15000, 'per_unit_hours' => 6],
            ],
        ],

        'plumber' => [
            'name' => 'Plumbing', 'icon' => '🔧',
            'base_cents' => 8000, 'base_hours' => 1.5, 'crew' => 1,
            'inputs' => [
                ['key' => 'job', 'label' => 'Job type', 'type' => 'select', 'options' => [
                    ['key' => 'leak',     'label' => 'Leak repair', 'multiplier' => 0.8],
                    ['key' => 'blockage', 'label' => 'Blocked drain', 'multiplier' => 0.9],
                    ['key' => 'boiler',   'label' => 'Boiler service / install', 'multiplier' => 1.8],
                    ['key' => 'bathroom', 'label' => 'Bathroom installation', 'multiplier' => 2.5],
                ]],
                ['key' => 'fixtures', 'label' => 'Fixtures involved', 'type' => 'number', 'min' => 1, 'max' => 20, 'default' => 1, 'per_unit_cents' => 6000, 'per_unit_hours' => 1],
            ],
        ],

        'electrician' => [
            'name' => 'Electrical', 'icon' => '⚡',
            'base_cents' => 7500, 'base_hours' => 1.5, 'crew' => 1,
            'inputs' => [
                ['key' => 'job', 'label' => 'Job type', 'type' => 'select', 'options' => [
                    ['key' => 'fault',    'label' => 'Fault finding', 'multiplier' => 0.8],
                    ['key' => 'sockets',  'label' => 'Sockets & switches', 'multiplier' => 1.0],
                    ['key' => 'lighting', 'label' => 'Lighting installation', 'multiplier' => 1.2],
                    ['key' => 'rewire',   'label' => 'Full / partial rewire', 'multiplier' => 3.0],
                ]],
                ['key' => 'points', 'label' => 'Points / fittings', 'type' => 'number', 'min' => 1, 'max' => 60, 'default' => 4, 'per_unit_cents' => 3500, 'per_unit_hours' => 0.75],
            ],
        ],
    ],
];
