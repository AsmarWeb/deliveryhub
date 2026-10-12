@extends('layouts.dashboard')

@section('title', __('addresses.title'))
@section('page-title', __('addresses.title'))

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white">
                    {{ __('addresses.title') }}
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    {{ __('addresses.subtitle') }}
                </p>
            </div>

            <a href="{{ route('merchant.addresses.create') }}"
                class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                + {{ __('addresses.add') }}
            </a>
        </div>

        @if ($addresses->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900 p-12 text-center">
                <div class="mb-4 text-4xl">📍</div>
                <p class="text-slate-300">{{ __('addresses.empty') }}</p>
                <a href="{{ route('merchant.addresses.create') }}"
                    class="mt-5 inline-block text-sm font-semibold text-blue-400 hover:text-blue-300">
                    {{ __('addresses.add') }}
                </a>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($addresses as $address)
                    @php
                        $isUsed = $address->pickup_deliveries_count > 0 || $address->dropoff_deliveries_count > 0;
                    @endphp

                    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words text-lg font-semibold text-white">
                                    {{ $address->label ?: $address->city }}
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    #{{ $address->id }}
                                </p>
                            </div>
                            <span class="rounded-lg bg-blue-500/10 px-3 py-2 text-blue-400">
                                📍
                            </span>
                        </div>

                        <p class="mt-5 whitespace-pre-line break-words text-sm text-slate-200">
                            {{ $address->address_line }}
                        </p>

                        <p class="mt-2 text-sm text-slate-400">
                            {{ collect([$address->area, $address->city, $address->country])->filter()->implode('، ') }}
                        </p>

                        @if ($address->contact_name)
                            <p class="mt-4 text-sm text-slate-300">
                                {{ $address->contact_name }}
                            </p>
                        @endif

                        @if ($address->contact_phone)
                            <p dir="ltr" class="mt-1 text-start text-sm text-slate-400">
                                {{ $address->contact_phone }}
                            </p>
                        @endif

                        @if ($isUsed)
                            <p class="mt-4 text-xs font-medium text-amber-300">
                                {{ __('addresses.used') }}
                            </p>
                        @endif

                        <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-800 pt-4">
                            <a href="{{ route('merchant.addresses.edit', $address) }}"
                                class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">
                                {{ __('addresses.edit') }}
                            </a>

                            @unless ($isUsed)
                                <form method="POST" action="{{ route('merchant.addresses.destroy', $address) }}"
                                    onsubmit="return confirm(@js(__('addresses.delete_confirm')))">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                        class="rounded-lg border border-red-500/30 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10">
                                        {{ __('addresses.delete') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $addresses->links() }}
            </div>
        @endif

    </div>
@endsection
