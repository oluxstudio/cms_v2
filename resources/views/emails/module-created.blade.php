@php $name = ucwords(str_replace('-', ' ', $site->name)); @endphp
<x-mail::message>
# New module added 🧩

Your AI assistant (Polux) created a new module on **{{ $name }}** in response to a request:

**{{ $moduleName }}** — {{ $moduleDescription }}.

It added a public page where visitors interact with it, and you can manage entries from the admin.

<x-mail::button :url="$adminUrl">
Manage entries
</x-mail::button>

Public page: [{{ $pageUrl }}]({{ $pageUrl }})

If this wasn't expected, you can delete the module from the Collections screen.

Thanks,<br>
{{ $name }}
</x-mail::message>
