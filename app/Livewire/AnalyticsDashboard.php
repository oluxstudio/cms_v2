<?php

namespace App\Livewire;

use App\Models\Site;
use Livewire\Component;

class AnalyticsDashboard extends Component
{
    public Site $site;

    public array $channelData = [
        ['name' => 'Email',    'value' => 5,  'color' => '#f5c842'],
        ['name' => 'Referal',  'value' => 12, 'color' => '#4fffdc'],
        ['name' => 'Organic',  'value' => 8,  'color' => '#7b5cf6'],
        ['name' => 'Direct',   'value' => 6,  'color' => '#f5697b'],
        ['name' => 'Campaign', 'value' => 8,  'color' => '#4fbfff'],
    ];

    public array $deviceData = [
        ['name' => 'Desktops', 'value' => 12843, 'day' => -3, 'week' => -3, 'color' => '#4fffdc'],
        ['name' => 'Tablets',  'value' => 6843,  'day' => -5, 'week' => -5, 'color' => '#4fbfff'],
        ['name' => 'Mobiles',  'value' => 843,   'day' => -8, 'week' => -8, 'color' => '#7b5cf6'],
    ];

    public array $countryData = [
        'US' => 25234, 'IN' => 15234, 'CN' => 11234, 'GB' => 12345,
        'CA' => 9876,  'JP' => 7890,  'DE' => 8432,  'FR' => 7621,
        'BR' => 6543,  'AU' => 5432,  'RU' => 5678,  'KR' => 4321,
        'MX' => 4567,  'IT' => 4230,  'ID' => 4560,  'TR' => 3340,
        'NG' => 3456,  'SG' => 3456,  'NL' => 3765,  'ES' => 3120,
        'SA' => 2760,  'SE' => 2890,  'MY' => 2890,  'TH' => 2870,
        'AE' => 2987,  'ZA' => 2345,  'PL' => 2100,  'VN' => 2340,
        'AR' => 3210,  'UA' => 1890,  'PK' => 1980,  'PH' => 1760,
    ];

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    public function render()
    {
        return view('livewire.analytics-dashboard');
    }
}
