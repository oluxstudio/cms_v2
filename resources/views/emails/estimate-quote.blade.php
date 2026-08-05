<x-mail::message>
{!! nl2br(e($bodyText)) !!}

@if (count($results))
<x-mail::table>
| Your estimate | |
|:--|--:|
@foreach ($results as $r)
| {{ $r['name'] }} | **{{ $r['formatted'] }}** |
@endforeach
</x-mail::table>
@elseif ($estimate->cost_high_cents > 0)
<x-mail::table>
| Your estimate | |
|:--|--:|
| Estimated cost | **{{ $estimate->costLabel() }}** |
@if ($estimate->completion)
| Estimated completion | **{{ $estimate->completion }}** |
@endif
</x-mail::table>
@endif

Reference: **{{ $estimate->reference }}**

Thanks,<br>
{{ ucwords(str_replace('-', ' ', $site->name ?? config('app.name'))) }}
</x-mail::message>
