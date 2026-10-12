@extends('layouts.dashboard')

@section('content')
    @php
        $ar = app()->getLocale() === 'ar';

        $labels = [
            'all' => $ar ? 'الكل' : 'All',
            'pending' => $ar ? 'بانتظار الرد' : 'Pending',
            'accepted' => $ar ? 'مقبولة' : 'Accepted',
            'rejected' => $ar ? 'مرفوضة' : 'Rejected',
            'expired' => $ar ? 'منتهية' : 'Expired',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">

        <div>
            <h1 class="text-2xl font-bold">
                {{ $ar ? 'مهامي' : 'My Assignments' }}
            </h1>

            <p class="mt-2 text-sm opacity-70">
                {{ $ar ? 'إدارة مهام التوصيل المسندة إليك' : 'Manage your assigned deliveries' }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach (['all', 'pending', 'accepted', 'rejected'] as $key)
                <a href="{{ route('driver.assignments.index', ['status' => $key]) }}"
                    class="rounded-xl border p-4 {{ $status === $key ? 'border-blue-500' : 'border-slate-700' }}">
                    <div class="text-sm opacity-70">
                        {{ $labels[$key] }}
                    </div>

                    <div class="mt-2 text-2xl font-bold">
                        {{ $counts[$key] }}
                    </div>
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($labels as $key => $label)
                <a href="{{ route('driver.assignments.index', ['status' => $key]) }}"
                    class="rounded-lg border px-4 py-2 text-sm
               {{ $status === $key ? 'border-blue-500 text-blue-400' : 'border-slate-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="space-y-4">
            @forelse ($assignments as $assignment)
                @php $delivery = $assignment->delivery; @endphp

                <div class="rounded-xl border border-slate-700 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold">
                                #{{ $delivery?->tracking_number ?? $assignment->id }}
                            </div>

                            <div class="mt-1 text-sm opacity-70">
                                {{ $ar ? 'حالة المهمة' : 'Assignment status' }}:
                                {{ $labels[$assignment->status] ?? ucfirst($assignment->status) }}
                            </div>
                        </div>

                        <a href="{{ route('driver.assignments.show', $assignment) }}"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                            {{ $ar ? 'عرض التفاصيل' : 'View Details' }}
                        </a>
                    </div>

                    <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                        <div>
                            <div class="opacity-60">
                                {{ $ar ? 'الاستلام' : 'Pickup' }}
                            </div>

                            <div class="mt-1">
                                {{ $delivery?->pickupAddress?->address_line ?? '—' }}
                            </div>
                        </div>

                        <div>
                            <div class="opacity-60">
                                {{ $ar ? 'التسليم' : 'Drop-off' }}
                            </div>

                            <div class="mt-1">
                                {{ $delivery?->dropoffAddress?->address_line ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-700 p-10 text-center">
                    <p class="opacity-70">
                        {{ $ar ? 'لا توجد مهام مطابقة حالياً' : 'No matching assignments found' }}
                    </p>
                </div>
            @endforelse
        </div>

        {{ $assignments->links() }}
    </div>
@endsection
