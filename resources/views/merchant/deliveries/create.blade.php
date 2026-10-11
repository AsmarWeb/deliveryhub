@extends('layouts.dashboard')

@section('title', __('deliveries.create_delivery'))
@section('page-title', __('deliveries.create_delivery'))

@section('content')

    <div class="mx-auto w-full max-w-4xl space-y-6">

        {{-- Page heading --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-white">
                    {{ __('deliveries.create_new') }}
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    {{ __('deliveries.description') }}
                </p>
            </div>

            @if (Route::has('merchant.deliveries.index'))
                <a href="{{ route('merchant.deliveries.index') }}"
                    class="inline-flex items-center rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800">
                    {{ __('deliveries.back_to_deliveries') }}
                </a>
            @endif
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-500/30 bg-red-500/10 p-5 text-red-300">
                <p class="font-semibold">
                    {{ __('deliveries.validation_errors') }}
                </p>

                <ul class="mt-3 list-inside list-disc space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($addresses->isEmpty())

            {{-- No addresses --}}
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-8 text-center">
                <div class="mb-4 text-4xl">📍</div>

                <h3 class="text-lg font-semibold text-amber-200">
                    {{ __('deliveries.no_addresses_title') }}
                </h3>

                <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-amber-100/80">
                    {{ __('deliveries.no_addresses_description') }}
                </p>

                @if (Route::has('merchant.addresses.create'))
                    <a href="{{ route('merchant.addresses.create') }}"
                        class="mt-6 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                        {{ __('deliveries.add_address') }}
                    </a>
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('merchant.deliveries.store') }}" class="space-y-6">

                @csrf

                {{-- Pickup and drop-off --}}
                <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-xl">
                            📍
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                {{ __('deliveries.address_section') }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ __('deliveries.address_section_hint') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="pickup_address_id" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.pickup_address') }}
                                <span class="text-red-400">*</span>
                            </label>

                            <select id="pickup_address_id" name="pickup_address_id" required
                                aria-invalid="{{ $errors->has('pickup_address_id') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                                <option value="">
                                    {{ __('deliveries.select_pickup') }}
                                </option>

                                @foreach ($addresses as $address)
                                    <option value="{{ $address->id }}" @selected(old('pickup_address_id') == $address->id)>
                                        {{ __('deliveries.address_number', ['number' => $address->id]) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('pickup_address_id')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="dropoff_address_id" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.dropoff_address') }}
                                <span class="text-red-400">*</span>
                            </label>

                            <select id="dropoff_address_id" name="dropoff_address_id" required
                                aria-invalid="{{ $errors->has('dropoff_address_id') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                                <option value="">
                                    {{ __('deliveries.select_dropoff') }}
                                </option>

                                @foreach ($addresses as $address)
                                    <option value="{{ $address->id }}" @selected(old('dropoff_address_id') == $address->id)>
                                        {{ __('deliveries.address_number', ['number' => $address->id]) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('dropoff_address_id')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </section>

                {{-- Recipient --}}
                <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-xl">
                            👤
                        </div>

                        <h3 class="text-lg font-semibold text-white">
                            {{ __('deliveries.recipient_section') }}
                        </h3>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="recipient_name" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.recipient_name') }}
                                <span class="text-red-400">*</span>
                            </label>

                            <input id="recipient_name" name="recipient_name" type="text" required maxlength="255"
                                value="{{ old('recipient_name') }}"
                                placeholder="{{ __('deliveries.recipient_name_placeholder') }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error('recipient_name')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_phone" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.recipient_phone') }}
                                <span class="text-red-400">*</span>
                            </label>

                            <input id="recipient_phone" name="recipient_phone" type="tel" dir="ltr" required
                                maxlength="30" value="{{ old('recipient_phone') }}" placeholder="+971 50 123 4567"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-left text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error('recipient_phone')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </section>

                {{-- Package --}}
                <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">

                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-xl">
                            📦
                        </div>

                        <h3 class="text-lg font-semibold text-white">
                            {{ __('deliveries.package_section') }}
                        </h3>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <div>
                            <label for="package_type" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.package_type') }}
                            </label>

                            <input id="package_type" name="package_type" maxlength="255" value="{{ old('package_type') }}"
                                placeholder="{{ __('deliveries.package_type_placeholder') }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error('package_type')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="weight" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.weight') }}
                            </label>

                            <input id="weight" name="weight" type="number" min="0" step="0.01"
                                inputmode="decimal" dir="ltr" value="{{ old('weight') }}" placeholder="0.00"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-left text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            @error('weight')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="scheduled_at" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.scheduled_pickup') }}
                            </label>

                            <input id="scheduled_at" name="scheduled_at" type="datetime-local" dir="ltr"
                                value="{{ old('scheduled_at') }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-left text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                            <p class="mt-2 text-xs text-slate-500">
                                {{ __('deliveries.dubai_timezone') }}
                            </p>

                            @error('scheduled_at')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="special_instructions" class="mb-2 block text-sm font-medium text-slate-300">
                                {{ __('deliveries.special_instructions') }}
                            </label>

                            <textarea id="special_instructions" name="special_instructions" rows="4"
                                placeholder="{{ __('deliveries.instructions_placeholder') }}"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">{{ old('special_instructions') }}</textarea>

                            @error('special_instructions')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </section>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center justify-end gap-3">

                    <a href="{{ route('merchant.deliveries.index') }}"
                        class="rounded-xl border border-slate-700 px-6 py-3 text-sm font-semibold text-slate-300 transition hover:bg-slate-800">
                        {{ __('deliveries.cancel') }}
                    </a>

                    <button type="submit"
                        class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        {{ __('deliveries.create_draft') }}
                    </button>

                </div>

            </form>

        @endif

    </div>

@endsection
