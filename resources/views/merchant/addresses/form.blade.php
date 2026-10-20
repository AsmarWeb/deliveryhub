@extends('layouts.dashboard')

@php
    $editing = $address->exists;
    $pageTitle = $editing ? __('addresses.edit') : __('addresses.create');
@endphp

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white">
                    {{ $pageTitle }}
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    {{ __('addresses.subtitle') }}
                </p>
            </div>

            <a href="{{ route('merchant.addresses.index') }}" class="text-sm text-blue-400 hover:text-blue-300">
                {{ __('addresses.cancel') }}
            </a>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-500/30 bg-red-500/10 p-4">
                <ul class="list-inside list-disc space-y-1 text-sm text-red-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $editing ? route('merchant.addresses.update', $address) : route('merchant.addresses.store') }}"
            class="space-y-6">

            @csrf

            @if ($editing)
                @method('PUT')
            @endif

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7">
                <div class="grid gap-5 md:grid-cols-2">

                    <div>
                        <label for="emirate" class="mb-2 block text-sm font-medium text-slate-300">{{ __('addresses.emirate') }}</label>
                        <select id="emirate" name="emirate" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100">
                            <option value="">—</option>
                            @foreach (\App\Support\UaeAddressFields::EMIRATES as $emirate)
                                <option value="{{ $emirate }}" @selected(old('emirate', $address->emirate) === $emirate)>{{ __('addresses.emirates.' . $emirate) }}</option>
                            @endforeach
                        </select>
                        @error('emirate')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    @foreach ([
            'label' => 'label',
            'contact_name' => 'contact_name',
            'contact_phone' => 'contact_phone',
            'country' => 'country',
            'city' => 'city',
            'area' => 'area',
            'street' => 'street',
            'building' => 'building',
            'unit' => 'unit',
            'landmark' => 'landmark',
            'makani_number' => 'makani_number',
        ] as $field => $translation)
                        <div>
                            <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('addresses.' . $translation) }}
                                @if (in_array($field, ['country', 'city']))
                                    <span class="text-red-400">*</span>
                                @endif
                            </label>

                            <input id="{{ $field }}" name="{{ $field }}"
                                type="{{ $field === 'contact_phone' ? 'tel' : 'text' }}"
                                maxlength="{{ $field === 'contact_phone' ? 30 : 255 }}"
                                value="{{ old($field, $field === 'country' ? ($address->$field ?: 'United Arab Emirates') : $address->$field) }}"
                                @if (in_array($field, ['country', 'city'])) required @endif
                                @if ($field === 'contact_phone') dir="ltr" @endif
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error($field)
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="md:col-span-2">
                        <label for="address_line" class="mb-2 block text-sm font-medium text-slate-300">
                            {{ __('addresses.address_line') }}
                            <span class="text-red-400">*</span>
                        </label>

                        <textarea id="address_line" name="address_line" rows="3" maxlength="2000" required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">{{ old('address_line', $address->address_line) }}</textarea>

                        @error('address_line')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="emirate" class="mb-2 block text-sm font-medium text-slate-300">{{ __('addresses.emirate') }}</label>
                        <select id="emirate" name="emirate" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100">
                            <option value="">—</option>
                            @foreach (\App\Support\UaeAddressFields::EMIRATES as $emirate)
                                <option value="{{ $emirate }}" @selected(old('emirate', $address->emirate) === $emirate)>{{ __('addresses.emirates.' . $emirate) }}</option>
                            @endforeach
                        </select>
                        @error('emirate')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    @foreach ([
            'latitude' => [-90, 90],
            'longitude' => [-180, 180],
        ] as $field => $range)
                        <div>
                            <label for="{{ $field }}" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('addresses.' . $field) }}
                            </label>

                            <input id="{{ $field }}" name="{{ $field }}" type="number" step="any"
                                min="{{ $range[0] }}" max="{{ $range[1] }}" dir="ltr"
                                value="{{ old($field, $address->$field) }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-left text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error($field)
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="md:col-span-2">
                        <label for="delivery_instructions" class="mb-2 block text-sm font-medium text-slate-300">
                            {{ __('addresses.instructions') }}
                        </label>

                        <textarea id="delivery_instructions" name="delivery_instructions" rows="3" maxlength="5000"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">{{ old('delivery_instructions', $address->delivery_instructions) }}</textarea>

                        @error('delivery_instructions')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('merchant.addresses.index') }}"
                    class="rounded-xl border border-slate-700 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-slate-800">
                    {{ __('addresses.cancel') }}
                </a>

                <button type="submit"
                    class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                    {{ $editing ? __('addresses.update') : __('addresses.save') }}
                </button>
            </div>

        </form>
    </div>
@endsection
