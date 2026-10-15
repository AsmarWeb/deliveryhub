@extends('layouts.dashboard')

@section('title', app()->getLocale() === 'ar' ? 'إدارة التجار' : 'Merchant Management')

@section('page-title', app()->getLocale() === 'ar' ? 'إدارة التجار' : 'Merchant Management')

@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';

        $statuses = [
            'active' => $isAr ? 'نشط' : 'Active',
            'inactive' => $isAr ? 'غير نشط' : 'Inactive',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">
                    {{ $isAr ? 'إدارة التجار' : 'Merchant Management' }}
                </h1>

                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr
                        ? 'متابعة وإدارة التجار والشركات المسجلة في المنصة'
                        : 'Monitor and manage registered merchants and businesses' }}
                </p>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
                <span class="text-sm text-slate-400">
                    {{ $isAr ? 'إجمالي النتائج' : 'Total Results' }}
                </span>

                <strong class="ms-2 text-xl text-white">
                    {{ $merchants->total() }}
                </strong>
            </div>
        </div>

        {{-- Success Message --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-700 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ $isAr ? 'تم تحديث حالة التاجر بنجاح.' : session('success') }}
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
                            {{ $isAr
                                ? ($errors->has('status')
                                    ? 'تعذر تغيير الحالة. تأكد من عدم وجود شحنات جارية وأن الحالة المطلوبة صحيحة.'
                                    : $error)
                                : $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Search & Filters --}}
        <form method="GET" action="{{ route('admin.merchants.index') }}"
            class="grid gap-3 rounded-2xl border border-slate-700 bg-slate-900 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] lg:items-center">

            <input type="search" name="search" value="{{ request('search') }}"
                placeholder="{{ $isAr ? 'اسم الشركة أو البريد الإلكتروني أو رقم الهاتف' : 'Business name, email or phone' }}"
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

        {{-- Merchants Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800 text-slate-300">
                        <tr>
                            @foreach ([$isAr ? 'الشركة' : 'Business', $isAr ? 'معلومات التواصل' : 'Contact', $isAr ? 'الحالة' : 'Status', $isAr ? 'الشحنات' : 'Deliveries', $isAr ? 'العناوين' : 'Addresses', $isAr ? 'تاريخ التسجيل' : 'Registered', $isAr ? 'الإجراءات' : 'Actions'] as $heading)
                                <th class="whitespace-nowrap px-5 py-4 text-start font-semibold">
                                    {{ $heading }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800">
                        @forelse ($merchants as $merchant)
                            @php
                                $status = $merchant->status;

                                $statusClass = match ($status) {
                                    'active' => 'bg-emerald-500/10 text-emerald-400',
                                    'inactive' => 'bg-red-500/10 text-red-400',
                                    default => 'bg-slate-500/10 text-slate-400',
                                };

                                $nextStatus = $status === 'active' ? 'inactive' : 'active';
                            @endphp

                            <tr class="transition hover:bg-slate-800/60">
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.merchants.show', $merchant) }}"
                                        class="font-semibold text-white transition hover:text-blue-400">
                                        {{ $merchant->business_name }}
                                    </a>

                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $merchant->business_email }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <p class="text-slate-300">
                                        {{ $merchant->user?->name ?? '—' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400" dir="ltr">
                                        {{ $merchant->business_phone ?? '—' }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $statuses[$status] ?? $status }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-slate-300">
                                    {{ $merchant->deliveries_count }}
                                </td>

                                <td class="px-5 py-4 text-slate-300">
                                    {{ $merchant->addresses_count }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-slate-400">
                                    {{ $merchant->created_at?->format('Y-m-d') }}
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('admin.merchants.show', $merchant) }}"
                                            class="font-semibold text-blue-400 hover:text-blue-300">
                                            {{ $isAr ? 'التفاصيل' : 'Details' }}
                                        </a>

                                        <form method="POST" action="{{ route('admin.merchants.status', $merchant) }}">
                                            @csrf
                                            @method('PATCH')

                                            <input type="hidden" name="status" value="{{ $nextStatus }}">

                                            <button type="submit" onclick="return confirm(@js($isAr ? 'هل أنت متأكد من تغيير حالة هذا التاجر؟' : 'Are you sure you want to change this merchant status?'))"
                                                class="rounded-lg px-3 py-2 text-xs font-semibold transition
                                                    {{ $nextStatus === 'inactive'
                                                        ? 'bg-red-500/10 text-red-400 hover:bg-red-500/20'
                                                        : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20' }}">
                                                {{ $nextStatus === 'inactive' ? ($isAr ? 'تعطيل' : 'Deactivate') : ($isAr ? 'تفعيل' : 'Activate') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-16 text-center text-slate-400">
                                    {{ $isAr ? 'لا يوجد تجار مطابقون للبحث' : 'No merchants found' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div>
            {{ $merchants->links() }}
        </div>
    </div>
@endsection
