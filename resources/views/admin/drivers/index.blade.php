<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drivers Management - DeliveryHub</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="mx-auto max-w-7xl px-4 py-10">

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">
                    {{ app()->getLocale() === 'ar' ? 'إدارة السائقين' : 'Drivers Management' }}
                </h1>
                <p class="mt-2 text-slate-400">
                    DeliveryHub Admin
                </p>
            </div>

            <a href="{{ route('admin.dashboard') }}" class="rounded-lg bg-slate-800 px-4 py-2 hover:bg-slate-700">
                {{ app()->getLocale() === 'ar' ? 'لوحة التحكم' : 'Dashboard' }}
            </a>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-red-700 bg-red-950 p-4 text-red-300">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('admin.drivers.index') }}" class="mb-6 flex flex-wrap gap-3">

            <input type="search" name="search" value="{{ request('search') }}"
                placeholder="{{ app()->getLocale() === 'ar' ? 'بحث بالاسم أو الهاتف أو البريد' : 'Search name, phone or email' }}"
                class="min-w-64 flex-1 rounded-lg border border-slate-700 bg-slate-900 px-4 py-3">

            <select name="status" class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-3">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>
                    Active
                </option>
                <option value="inactive" @selected(request('status') === 'inactive')>
                    Inactive
                </option>
            </select>

            <button class="rounded-lg bg-blue-600 px-5 py-3 font-medium hover:bg-blue-500">
                Search
            </button>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-800 text-slate-300">
                    <tr>
                        <th class="p-4 text-start">Driver</th>
                        <th class="p-4 text-start">Phone</th>
                        <th class="p-4 text-start">Vehicle</th>
                        <th class="p-4 text-start">Status</th>
                        <th class="p-4 text-start">Availability</th>
                        <th class="p-4 text-start">Assignments</th>
                        <th class="p-4 text-start">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-800">
                    @forelse($drivers as $driver)
                        <tr class="hover:bg-slate-800/50">
                            <td class="p-4">
                                <div class="font-semibold">
                                    {{ $driver->user?->name ?? 'Unknown' }}
                                </div>
                                <div class="text-slate-400">
                                    {{ $driver->user?->email }}
                                </div>
                            </td>

                            <td class="p-4">
                                {{ $driver->phone ?? '-' }}
                            </td>

                            <td class="p-4">
                                {{ $driver->vehicle_type ?? '-' }}
                                <div class="text-slate-400">
                                    {{ $driver->vehicle_plate }}
                                </div>
                            </td>

                            <td class="p-4">
                                {{ ucfirst($driver->status) }}
                            </td>

                            <td class="p-4">
                                {{ $driver->is_available ? 'Available' : 'Unavailable' }}
                            </td>

                            <td class="p-4">
                                {{ $driver->assignments_count }}
                                <div class="text-xs text-slate-400">
                                    Active: {{ $driver->active_assignments_count }}
                                </div>
                            </td>

                            <td class="p-4">
                                @php
                                    $nextStatus = $driver->status === 'active' ? 'inactive' : 'active';
                                @endphp

                                <form method="POST" action="{{ route('admin.drivers.status', $driver) }}">
                                    @csrf
                                    @method('PATCH')

                                    <input type="hidden" name="status" value="{{ $nextStatus }}">

                                    <button type="submit" @disabled($nextStatus === 'inactive' && $driver->active_assignments_count > 0)
                                        class="rounded-lg bg-slate-700 px-3 py-2 hover:bg-slate-600 disabled:cursor-not-allowed disabled:opacity-40">
                                        {{ $nextStatus === 'active' ? 'Activate' : 'Deactivate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-slate-400">
                                No drivers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $drivers->links() }}
        </div>

    </div>
</body>

</html>
