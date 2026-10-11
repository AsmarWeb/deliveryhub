@extends('layouts.dashboard')

@section('title', __('dashboard.my_deliveries'))
@section('page-title', __('dashboard.my_deliveries'))

@section('content')

    @php
        $locale = app()->getLocale();

        $search = request('search', '');
        $selectedStatus = request('status', '');

        $hasFilters = filled($search) || filled($selectedStatus);

        $statusColors = [
            'draft' => 'bg-slate-500/10 text-slate-300 ring-slate-500/20',
            'pending_pricing' => 'bg-amber-500/10 text-amber-300 ring-amber-500/20',
            'priced' => 'bg-cyan-500/10 text-cyan-300 ring-cyan-500/20',
            'matching' => 'bg-violet-500/10 text-violet-300 ring-violet-500/20',
            'assigned' => 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
            'accepted' => 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
            'pickup' => 'bg-indigo-500/10 text-indigo-300 ring-indigo-500/20',
            'in_transit' => 'bg-indigo-500/10 text-indigo-300 ring-indigo-500/20',
            'delivered' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
            'completed' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
            'cancelled' => 'bg-red-500/10 text-red-300 ring-red-500/20',
            'pickup_failed' => 'bg-red-500/10 text-red-300 ring-red-500/20',
            'delivery_failed' => 'bg-red-500/10 text-red-300 ring-red-500/20',
            'rescheduled' => 'bg-amber-500/10 text-amber-300 ring-amber-500/20',
            'expired' => 'bg-slate-500/10 text-slate-400 ring-slate-500/20',
        ];
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-6">

        {{-- Heading --}}
        <div class="flex flex-wrap items-center justify-between gap-4">

            <div class="min-w-0">
                <h2 class="text-2xl font-bold tracking-tight text-white">
                    {{ __('dashboard.my_deliveries') }}
                </h2>

                <p class="mt-2 text-sm text-slate-400">
                    {{ __('deliveries.manage_description') }}
                </p>
            </div>

            @if (Route::has('merchant.deliveries.create'))
                <a href="{{ route('merchant.deliveries.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">

                    <span aria-hidden="true" class="text-xl leading-none">+</span>
                    <span>{{ __('dashboard.new_delivery') }}</span>

                </a>
            @endif

        </div>

        {{-- Search and filters --}}
        <form method="GET" action="{{ route('merchant.deliveries.index') }}" role="search"
            class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-5">

            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,220px)_auto]">

                {{-- Tracking search --}}
                <div class="min-w-0">
                    <label for="delivery-search" class="mb-2 block text-xs font-semibold text-slate-400">
                        {{ __('deliveries.search_tracking') }}
                    </label>

                    <input id="delivery-search" type="search" name="search" dir="auto" value="{{ $search }}"
                        placeholder="{{ __('deliveries.search_placeholder') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>

                {{-- Status filter --}}
                <div class="min-w-0">
                    <label for="delivery-status" class="mb-2 block text-xs font-semibold text-slate-400">
                        {{ __('deliveries.status') }}
                    </label>

                    <select id="delivery-status" name="status"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">

                        <option value="">
                            {{ __('deliveries.all_statuses') }}
                        </option>

                        @foreach ($statuses as $status)
                            @php
                                $statusValue = $status->value;
                                $statusKey = 'dashboard.statuses.' . $statusValue;

                                $statusLabel = __($statusKey);

                                if ($statusLabel === $statusKey) {
                                    $statusLabel = ucwords(str_replace('_', ' ', $statusValue));
                                }
                            @endphp

                            <option value="{{ $statusValue }}" @selected($selectedStatus === $statusValue)>
                                {{ $statusLabel }}
                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- Apply --}}
                <div class="flex items-end">
                    <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 md:w-auto">

                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 7h16M7 12h10M10 17h4" />
                        </svg>

                        {{ __('deliveries.apply_filters') }}
                    </button>
                </div>

            </div>

            @if ($hasFilters)
                <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-800 pt-4">
                    <p class="text-xs text-slate-400">
                        {{ __('deliveries.filters_active') }}
                    </p>

                    <a href="{{ route('merchant.deliveries.index') }}"
                        class="text-xs font-semibold text-blue-400 transition hover:text-blue-300">
                        {{ __('deliveries.clear_filters') }}
                    </a>
                </div>
            @endif

        </form>

        {{-- Deliveries table --}}
        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4 sm:px-6">

                <div>
                    <h3 class="font-semibold text-white">
                        {{ __('deliveries.delivery_list') }}
                    </h3>

                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('deliveries.results_count', [
                            'count' => $deliveries->total(),
                        ]) }}
                    </p>
                </div>

                <span class="rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-300">
                    {{ __('deliveries.total_deliveries', [
                        'count' => $deliveries->total(),
                    ]) }}
                </span>

            </div>

            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[680px] text-start text-sm">

                    <thead class="bg-slate-800/60 text-xs font-semibold text-slate-400">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-start">
                                {{ __('deliveries.tracking') }}
                            </th>
                            <th scope="col" class="px-6 py-4 text-start">
                                {{ __('deliveries.recipient') }}
                            </th>
                            <th scope="col" class="px-6 py-4 text-start">
                                {{ __('deliveries.status') }}
                            </th>
                            <th scope="col" class="px-6 py-4 text-start">
                                {{ __('deliveries.created') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800">

                        @forelse ($deliveries as $delivery)
                            @php
                                $statusValue = $delivery->status->value;

                                $statusKey = 'dashboard.statuses.' . $statusValue;
                                $statusLabel = __($statusKey);

                                if ($statusLabel === $statusKey) {
                                    $statusLabel = ucwords(str_replace('_', ' ', $statusValue));
                                }

                                $statusClass =
                                    $statusColors[$statusValue] ?? 'bg-slate-500/10 text-slate-300 ring-slate-500/20';
                            @endphp

                            <tr class="transition hover:bg-slate-800/40">

                                <td class="px-6 py-4">
                                    <span dir="ltr" class="inline-block font-mono text-sm font-semibold text-blue-400">
                                        {{ $delivery->tracking_number }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 font-medium text-slate-200">
                                    {{ $delivery->recipient_name }}
                                </td>

                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-slate-400">
                                    <time datetime="{{ $delivery->created_at->toIso8601String() }}">
                                        {{ $delivery->created_at->copy()->timezone('Asia/Dubai')->locale($locale)->translatedFormat('d M Y') }}
                                    </time>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="4" class="px-6 py-14 text-center text-slate-400">
                                    {{ __('deliveries.no_deliveries') }}
                                </td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="divide-y divide-slate-800 md:hidden">

                @forelse ($deliveries as $delivery)
                    @php
                        $statusValue = $delivery->status->value;

                        $statusKey = 'dashboard.statuses.' . $statusValue;
                        $statusLabel = __($statusKey);

                        if ($statusLabel === $statusKey) {
                            $statusLabel = ucwords(str_replace('_', ' ', $statusValue));
                        }

                        $statusClass =
                            $statusColors[$statusValue] ?? 'bg-slate-500/10 text-slate-300 ring-slate-500/20';
                    @endphp

                    <article class="space-y-4 p-5">

                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <div class="min-w-0">
                                <p class="mb-1 text-xs text-slate-500">
                                    {{ __('deliveries.tracking') }}
                                </p>

                                <p dir="ltr" class="break-all text-start font-mono text-sm font-bold text-blue-400">
                                    {{ $delivery->tracking_number }}
                                </p>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                        </div>

                        <div>
                            <p class="text-xs text-slate-500">
                                {{ __('deliveries.recipient') }}
                            </p>

                            <p class="mt-1 text-sm font-medium text-white">
                                {{ $delivery->recipient_name }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
                            <span class="text-xs text-slate-500">
                                {{ __('deliveries.created') }}
                            </span>

                            <time datetime="{{ $delivery->created_at->toIso8601String() }}" class="text-xs text-slate-300">
                                {{ $delivery->created_at->copy()->timezone('Asia/Dubai')->locale($locale)->translatedFormat('d M Y') }}
                            </time>
                        </div>

                    </article>

                @empty

                    <div class="px-5 py-14 text-center">
                        <div class="mb-3 text-3xl">📦</div>

                        <p class="font-semibold text-slate-200">
                            {{ __('deliveries.no_deliveries') }}
                        </p>

                        <p class="mt-2 text-sm text-slate-500">
                            {{ __('deliveries.no_deliveries_description') }}
                        </p>

                        @if (Route::has('merchant.deliveries.create'))
                            <a href="{{ route('merchant.deliveries.create') }}"
                                class="mt-5 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                                {{ __('dashboard.new_delivery') }}
                            </a>
                        @endif
                    </div>
                @endforelse

            </div>

        </section>

        {{-- Pagination --}}
        @if ($deliveries->hasPages())
            <div class="overflow-x-auto pt-2">
                {{ $deliveries->withQueryString()->links() }}
            </div>
        @endif

    </div>

@endsection
