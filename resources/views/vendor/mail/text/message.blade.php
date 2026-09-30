@php($companyName = \App\Models\CompanySettings::query()->value('company_name') ?? config('app.name'))
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $companyName }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            Email ini dikirim otomatis, mohon tidak membalas. © {{ date('Y') }} {{ $companyName }}.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
