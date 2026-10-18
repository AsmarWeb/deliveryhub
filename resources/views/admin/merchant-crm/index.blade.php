@extends('layouts.dashboard')
@section('title', app()->getLocale() === 'ar' ? 'إدارة فرص التجار' : 'Merchant CRM')
@section('page-title', app()->getLocale() === 'ar' ? 'إدارة فرص التجار' : 'Merchant CRM')
@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';
        $statuses = [
            'new' => [$isAr ? 'جديد' : 'New', 'bg-blue-500/10 text-blue-400'],
            'contacted' => [$isAr ? 'تم التواصل' : 'Contacted', 'bg-violet-500/10 text-violet-400'],
            'interested' => [$isAr ? 'مهتم' : 'Interested', 'bg-cyan-500/10 text-cyan-400'],
            'negotiating' => [$isAr ? 'قيد التفاوض' : 'Negotiating', 'bg-amber-500/10 text-amber-400'],
            'won' => [$isAr ? 'انضم' : 'Won', 'bg-emerald-500/10 text-emerald-400'],
            'lost' => [$isAr ? 'مغلق' : 'Lost', 'bg-rose-500/10 text-rose-400'],
        ];
        $totalCount = collect($counts)->sum();
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">
                    {{ $isAr ? 'إدارة فرص التجار والشركات' : 'Merchant & Company Leads' }}</h1>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $isAr ? 'تابع طلبات الانضمام والفرص التجارية في الإمارات' : 'Manage UAE merchant applications and sales opportunities' }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 px-5 py-3"><span
                    class="text-sm text-slate-400">{{ $isAr ? 'إجمالي الفرص' : 'Total leads' }}</span><strong
                    class="ms-3 text-xl text-white">{{ $totalCount }}</strong></div>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($statuses as $key => $info)
                <a href="{{ route('admin.merchant-crm.index', ['status' => $key]) }}"
                    class="rounded-2xl border p-4 transition hover:border-blue-500 {{ request('status') === $key ? 'border-blue-500 bg-blue-500/10' : 'border-slate-700 bg-slate-900' }}"><span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $info[1] }}">{{ $info[0] }}</span>
                    <div class="mt-3 text-2xl font-bold text-white">{{ collect($counts)->get($key, 0) }}</div>
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-3"><a href="{{ route('admin.merchant-crm.index') }}"
                class="rounded-xl px-4 py-2 text-sm font-semibold {{ request('status') ? 'bg-slate-800 text-slate-200' : 'bg-blue-600 text-white' }}">{{ $isAr ? 'جميع الفرص' : 'All leads' }}</a><span
                class="text-sm text-slate-400">{{ $isAr ? 'النتائج المعروضة:' : 'Showing:' }} {{ $leads->total() }}</span>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-slate-800 text-slate-300">
                        <tr>
                            @foreach ([$isAr ? 'الشركة / المسؤول' : 'Company / Contact', $isAr ? 'الإمارة' : 'Emirate', $isAr ? 'الحالة' : 'Status', $isAr ? 'الأولوية' : 'Priority', $isAr ? 'المتابعة القادمة' : 'Next follow-up', $isAr ? 'الإجراءات' : 'Actions'] as $heading)
                                <th class="px-5 py-4 text-start font-semibold">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($leads as $lead)
                            @php $info = $statuses[$lead->status] ?? [$lead->status, 'bg-slate-700 text-slate-300']; @endphp
                            <tr class="transition hover:bg-slate-800/60">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-white">{{ $lead->company_name }}</div>
                                    <div class="mt-1 text-xs text-slate-400">{{ $lead->contact_name }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-300">{{ ucfirst($lead->emirate) }}</td>
                                <td class="px-5 py-4"><span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $info[1] }}">{{ $info[0] }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-300">{{ $lead->priority ?? 3 }} / 5</td>
                                <td class="px-5 py-4 text-slate-300">
                                    {{ $lead->next_follow_up_at ? \Illuminate\Support\Carbon::parse($lead->next_follow_up_at)->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="px-5 py-4"><a
                                        class="inline-flex rounded-lg bg-blue-500/10 px-3 py-2 font-semibold text-blue-400 hover:bg-blue-500/20"
                                        href="{{ route('admin.merchant-crm.show', $lead) }}">{{ $isAr ? 'عرض التفاصيل' : 'View details' }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-16 text-center text-slate-400">
                                    {{ $isAr ? 'لا توجد طلبات مطابقة' : 'No matching leads found' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $leads->withQueryString()->links() }}</div>
    </div>
@endsection
