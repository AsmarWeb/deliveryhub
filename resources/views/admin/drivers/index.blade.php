@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'إدارة السائقين' : 'Drivers Management')

@section('page-title', app()->getLocale() === 'ar' ? 'إدارة السائقين' : 'Drivers Management')

@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';

        $statuses = [
            'pending' => $isAr ? 'بانتظار الموافقة' : 'Pending Approval',
            'active' => $isAr ? 'نشط' : 'Active',
            'inactive' => $isAr ? 'غير نشط' : 'Inactive',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">
                    {{ $isAr ? 'إدارة السائقين' : 'Drivers Management' }}
                </h1>

                <a href="{{ route('admin.drivers.map') }}"
                    class="inline-flex items-center rounded-xl
           bg-blue-600 px-5 py-3 font-semibold
           text-white transition hover:bg-blue-500">
                    {{ $isAr ? 'خريطة السائقين' : 'Drivers Map' }}
                </a>

                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr
                        ? 'متابعة وإدارة السائقين وحالة توفرهم وإسنادات التوصيل'
                        : 'Monitor drivers, availability and delivery assignments' }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
                <span class="text-sm text-slate-400">
                    {{ $isAr ? 'إجمالي النتائج' : 'Total Results' }}
                </span>

                <strong class="ms-2 text-xl text-white">
                    {{ $drivers->total() }}
                </strong>
            </div>
        </div>

        {{-- Success Message --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-700 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ $isAr ? 'تم تحديث حالة السائق بنجاح.' : session('success') }}
            </div>
        @endif

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="rounded-xl border border-red-700 bg-red-500/10 px-5 py-4 text-sm text-red-400">
                <p class="font-semibold">
                    {{ $isAr ? 'تعذر تنفيذ العملية' : 'Unable to complete the operation' }}
                </p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>
                            {{ $isAr && $errors->has('status')
                                ? 'تعذر تغيير حالة السائق. تحقق من عدم وجود إسنادات نشطة وصحة الحالة المطلوبة.'
                                : $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Search & Filters --}}
        <form method="GET" action="{{ route('admin.drivers.index') }}"
            class="grid gap-3 rounded-2xl border border-slate-700 bg-slate-900 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] lg:items-center">

            <input type="search" name="search" value="{{ request('search') }}"
                placeholder="{{ $isAr ? 'اسم السائق أو رقم الهاتف أو البريد الإلكتروني' : 'Driver name, phone number or email' }}"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white placeholder-slate-400 focus:border-blue-500 focus:outline-none">

            <select name="status"
                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-blue-500 focus:outline-none">

                <option value="">
                    {{ $isAr ? 'جميع الحالات' : 'All Statuses' }}
                </option>

                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                class="rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white transition hover:bg-blue-500">
                {{ $isAr ? 'بحث وتصفية' : 'Search & Filter' }}
            </button>
        </form>

        {{-- Drivers Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[950px] text-sm">
                    <thead class="bg-slate-800 text-slate-300">
                        <tr>
                            @foreach ([$isAr ? 'السائق' : 'Driver', $isAr ? 'رقم الهاتف' : 'Phone', $isAr ? 'المركبة' : 'Vehicle', $isAr ? 'حالة الحساب' : 'Account Status', $isAr ? 'التوفر' : 'Availability', $isAr ? 'الإسنادات' : 'Assignments', $isAr ? 'تاريخ التسجيل' : 'Registered', $isAr ? 'آخر موقع' : 'Last Location', $isAr ? 'الإجراءات' : 'Actions'] as $heading)
                                <th class="whitespace-nowrap px-5 py-4 text-start font-semibold">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800">
                        @forelse ($drivers as $driver)
                            @php
                                $status = $driver->status;

                                $statusClass = match ($status) {
                                    'active' => 'bg-emerald-500/10 text-emerald-400',
                                    'inactive' => 'bg-red-500/10 text-red-400',
                                    'pending' => 'bg-amber-500/10 text-amber-400',
                                    default => 'bg-slate-500/10 text-slate-400',
                                };

                                $availabilityClass = $driver->is_available
                                    ? 'bg-emerald-500/10 text-emerald-400'
                                    : 'bg-amber-500/10 text-amber-400';

                                $nextStatus = $status === 'active' ? 'inactive' : 'active';

                                $hasActiveAssignments = $driver->active_assignments_count > 0;

                                $cannotDeactivate = $nextStatus === 'inactive' && $hasActiveAssignments;

                                $hasLocation =
                                    $driver->current_latitude !== null && $driver->current_longitude !== null;
                                $lastSeen = $driver->last_location_at;
                                $isRecent = $hasLocation && $lastSeen && $lastSeen->gte(now()->subMinutes(5));
                            @endphp

                            <tr class="transition hover:bg-slate-800/60">

                                {{-- Driver --}}
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-white">
                                        {{ $driver->user?->name ?? ($isAr ? 'غير معروف' : 'Unknown') }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $driver->user?->email ?? '—' }}
                                    </div>
                                </td>

                                {{-- Phone --}}
                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">
                                    <span dir="ltr" class="inline-block">
                                        {{ $driver->phone ?? '—' }}
                                    </span>
                                </td>

                                {{-- Vehicle --}}
                                <td class="px-5 py-4">
                                    <div class="text-slate-300">
                                        {{ $driver->vehicle_type ?? '—' }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $driver->vehicle_plate ?? '—' }}
                                    </div>
                                </td>

                                {{-- Account Status --}}
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statuses[$status] ?? $status }}
                                    </span>
                                </td>

                                {{-- Availability --}}
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $availabilityClass }}">
                                        {{ $driver->is_available ? ($isAr ? 'متاح' : 'Available') : ($isAr ? 'غير متاح' : 'Unavailable') }}
                                    </span>
                                </td>

                                {{-- Assignments --}}
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-white">
                                        {{ $driver->assignments_count }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $isAr ? 'النشطة:' : 'Active:' }}
                                        <span class="{{ $hasActiveAssignments ? 'text-amber-400' : 'text-slate-400' }}">
                                            {{ $driver->active_assignments_count }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Registration Date --}}
                                <td class="whitespace-nowrap px-5 py-4 text-slate-400">
                                    {{ $driver->created_at?->format('Y-m-d') }}
                                </td>

                                {{-- Last known location: not proof that the driver is online --}}
                                <td class="px-5 py-4">
                                    @if ($hasLocation && $lastSeen)
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $isRecent ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">
                                            {{ $isRecent ? ($isAr ? 'حديث' : 'Recent') : ($isAr ? 'قديم' : 'Stale') }}
                                        </span>
                                        <div class="mt-2 whitespace-nowrap text-xs text-slate-400"
                                            title="{{ $lastSeen->format('Y-m-d H:i:s T') }}">
                                            {{ $lastSeen->diffForHumans() }}
                                        </div>
                                    @else
                                        <span
                                            class="text-xs text-slate-500">{{ $isAr ? 'لا يوجد موقع مسجل' : 'No recorded location' }}</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-2">
                                        @if ($hasLocation)
                                            <a href="{{ route('admin.drivers.location', $driver) }}"
                                                class="inline-flex rounded-lg bg-blue-500/10 px-3 py-2 text-xs font-semibold text-blue-400 hover:bg-blue-500/20">
                                                {{ $isAr ? 'بيانات الموقع' : 'Location details' }}
                                            </a>
                                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($driver->current_latitude . ',' . $driver->current_longitude) }}"
                                                target="_blank" rel="noopener noreferrer"
                                                class="inline-flex rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-600"
                                                title="{{ $isAr ? 'سيتم فتح الموقع لدى Google Maps' : 'Opens location in Google Maps' }}">
                                                {{ $isAr ? 'فتح الخريطة' : 'Open map' }}
                                            </a>
                                        @endif

                                        <form method="POST" action="{{ route('admin.drivers.status', $driver) }}">
                                            @csrf
                                            @method('PATCH')

                                            <input type="hidden" name="status" value="{{ $nextStatus }}">

                                            <button type="submit" @disabled($cannotDeactivate)
                                                @if (!$cannotDeactivate) onclick="return confirm(@js($isAr ? 'هل أنت متأكد من تغيير حالة هذا السائق؟' : 'Are you sure you want to change this driver status?'))" @endif
                                                title="{{ $cannotDeactivate
                                                    ? ($isAr
                                                        ? 'لا يمكن تعطيل السائق لوجود إسنادات نشطة'
                                                        : 'Driver has active assignments')
                                                    : '' }}"
                                                class="rounded-lg px-3 py-2 text-xs font-semibold transition
                                                disabled:cursor-not-allowed disabled:opacity-40
                                                {{ $nextStatus === 'inactive'
                                                    ? 'bg-red-500/10 text-red-400 hover:bg-red-500/20'
                                                    : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20' }}">

                                                {{ $status === 'pending'
                                                ? ($isAr ? 'الموافقة على السائق' : 'Approve Driver')
                                                : ($nextStatus === 'inactive'
                                                    ? ($isAr ? 'تعطيل' : 'Deactivate')
                                                    : ($isAr ? 'تفعيل' : 'Activate')) }}
                                            </button>
                                        </form>

                                        @if ($status === 'pending')
                                            <form method="POST"
                                                  action="{{ route('admin.drivers.status', $driver) }}">
                                                @csrf
                                                @method('PATCH')

                                                <input type="hidden" name="status" value="inactive">

                                                <button type="submit"
                                                        onclick="return confirm(@js($isAr ? 'هل تريد رفض طلب هذا السائق؟' : 'Reject this driver application?'))"
                                                        class="rounded-lg bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-400 hover:bg-red-500/20">
                                                    {{ $isAr ? 'رفض الطلب' : 'Reject Application' }}
                                                </button>
                                            </form>
                                        @endif

                                        @if ($cannotDeactivate)
                                            <p class="mt-2 max-w-40 text-xs text-amber-400">
                                                {{ $isAr ? 'لديه إسنادات نشطة' : 'Active assignments exist' }}
                                            </p>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-16 text-center text-slate-400">
                                    {{ $isAr ? 'لا يوجد سائقون مطابقون للبحث' : 'No drivers found' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div>
            {{ $drivers->links() }}
        </div>

    </div>
@endsection
