@extends('layouts.dashboard')
@section('title', app()->getLocale() === 'ar' ? 'تفاصيل الفرصة' : 'Lead Details')
@section('page-title', app()->getLocale() === 'ar' ? 'تفاصيل الفرصة' : 'Lead Details')
@section('content')
    @php
        $isAr = app()->getLocale() === 'ar';
        $statuses = [
            'new' => $isAr ? 'جديد' : 'New',
            'contacted' => $isAr ? 'تم التواصل' : 'Contacted',
            'interested' => $isAr ? 'مهتم' : 'Interested',
            'negotiating' => $isAr ? 'قيد التفاوض' : 'Negotiating',
            'won' => $isAr ? 'انضم' : 'Won',
            'lost' => $isAr ? 'مغلق' : 'Lost',
        ];
        $inputClass =
            'mt-2 block w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none';
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><a href="{{ route('admin.merchant-crm.index') }}"
                    class="text-sm text-blue-400 hover:text-blue-300">{{ $isAr ? '← العودة إلى جميع الفرص' : '← Back to leads' }}</a>
                <h1 class="mt-3 text-2xl font-bold text-white">{{ $lead->company_name }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $isAr ? 'الفرصة رقم' : 'Lead #' }} {{ $lead->id }} ·
                    {{ $lead->created_at?->format('Y-m-d') }}</p>
            </div><span
                class="inline-flex w-fit rounded-full bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-400">{{ $statuses[$lead->status] ?? $lead->status }}</span>
        </div>
        @if (session('crm_success'))
            <div role="status"
                class="rounded-xl border border-emerald-700 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-400">
                {{ session('crm_success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-700 bg-red-500/10 px-5 py-4 text-sm text-red-400">
                <p class="font-semibold">{{ $isAr ? 'تعذر حفظ التغييرات' : 'Unable to save changes' }}</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                    <h2 class="text-lg font-bold text-white">{{ $isAr ? 'بيانات الشركة' : 'Company information' }}</h2>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        @foreach ([[$isAr ? 'مسؤول التواصل' : 'Contact person', $lead->contact_name], [$isAr ? 'الإمارة' : 'Emirate', ucfirst($lead->emirate)], [$isAr ? 'نوع النشاط' : 'Business type', $lead->business_type], [$isAr ? 'حجم التوصيلات شهرياً' : 'Monthly deliveries', $lead->monthly_deliveries ?? '—']] as $field)
                            <div>
                                <dt class="text-xs text-slate-400">{{ $field[0] }}</dt>
                                <dd class="mt-1 font-medium text-white">{{ $field[1] }}</dd>
                            </div>
                        @endforeach
                        <div>
                            <dt class="text-xs text-slate-400">{{ $isAr ? 'البريد الإلكتروني' : 'Email' }}</dt>
                            <dd class="mt-1"><a class="break-all text-blue-400"
                                    href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">{{ $isAr ? 'الهاتف' : 'Phone' }}</dt>
                            <dd class="mt-1"><a dir="ltr" class="inline-block text-blue-400"
                                    href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-slate-800 pt-5">
                        <h3 class="text-sm font-semibold text-slate-300">{{ $isAr ? 'رسالة العميل' : 'Customer message' }}
                        </h3>
                        <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-300">
                            {{ $lead->message ?: '—' }}</p>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                    <h2 class="text-lg font-bold text-white">{{ $isAr ? 'مساعد الذكاء الاصطناعي' : 'AI Assistant' }}</h2>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-xl bg-slate-800 p-4">
                            <h3 class="text-sm font-semibold text-blue-400">{{ $isAr ? 'ملخص الفرصة' : 'Lead summary' }}
                            </h3>
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-200">
                                {{ $lead->ai_summary ?: ($isAr ? 'لم يتم التحليل بعد' : 'Not analyzed yet') }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-800 p-4">
                            <h3 class="text-sm font-semibold text-blue-400">
                                {{ $isAr ? 'الخطوة المقترحة' : 'Recommended next step' }}</h3>
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-200">
                                {{ $lead->ai_recommendation ?: '—' }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-amber-300">
                        {{ $isAr ? 'التحليل الآلي اقتراح للمراجعة البشرية ولا يرسل رسائل تلقائياً.' : 'AI output requires human review and does not send messages automatically.' }}
                    </p>
                    <form method="POST" action="{{ route('admin.merchant-crm.analyze', $lead) }}" class="mt-5">
                        @csrf<button type="submit"
                            class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-500">{{ $isAr ? 'تحليل الفرصة باستخدام AI' : 'Analyze lead with AI' }}</button>
                    </form>
                </section>
                <section class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                    <h2 class="text-lg font-bold text-white">{{ $isAr ? 'سجل النشاط' : 'Activity timeline' }}</h2>
                    <div class="mt-5 space-y-4">
                        @forelse($activities as $activity)
                            <div class="border-s-2 border-slate-700 ps-4">
                                <div class="text-xs text-slate-400">{{ $activity->created_at?->format('Y-m-d H:i') }} ·
                                    {{ $activity->type }} · {{ $activity->user?->name ?? 'System' }}</div>
                                <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-200">
                                    {{ $activity->details }}</p>
                        </div>@empty<p class="text-sm text-slate-400">
                                {{ $isAr ? 'لا يوجد نشاط بعد' : 'No activity yet' }}</p>
                        @endforelse
                    </div>
                </section>
            </div>
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                    <h2 class="text-lg font-bold text-white">{{ $isAr ? 'إدارة الفرصة' : 'Manage lead' }}</h2>
                    <form method="POST" action="{{ route('admin.merchant-crm.update', $lead) }}" class="mt-5 space-y-4">
                        @csrf @method('PATCH')<label
                            class="block text-sm text-slate-300">{{ $isAr ? 'الحالة' : 'Status' }}<select name="status"
                                class="{{ $inputClass }}">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', $lead->status) === $key)>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label><label
                            class="block text-sm text-slate-300">{{ $isAr ? 'الأولوية (1 الأعلى)' : 'Priority (1 highest)' }}<select
                                name="priority" class="{{ $inputClass }}">
                                @foreach (range(1, 5) as $n)
                                    <option value="{{ $n }}" @selected((int) old('priority', $lead->priority ?? 3) === $n)>{{ $n }}
                                    </option>
                                @endforeach
                            </select></label><label
                            class="block text-sm text-slate-300">{{ $isAr ? 'موعد المتابعة' : 'Next follow-up' }}<input
                                type="datetime-local" name="next_follow_up_at" class="{{ $inputClass }}"
                                value="{{ old('next_follow_up_at', $lead->next_follow_up_at ? \Illuminate\Support\Carbon::parse($lead->next_follow_up_at)->format('Y-m-d\TH:i') : '') }}"></label><button
                            type="submit"
                            class="w-full rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-500">{{ $isAr ? 'حفظ التغييرات' : 'Save changes' }}</button>
                    </form>
                </section>
                <section class="rounded-2xl border border-slate-700 bg-slate-900 p-6">
                    <h2 class="text-lg font-bold text-white">{{ $isAr ? 'إضافة ملاحظة' : 'Add note' }}</h2>
                    <form method="POST" action="{{ route('admin.merchant-crm.note', $lead) }}" class="mt-5 space-y-4">
                        @csrf
                        <textarea name="note" required maxlength="3000" rows="5"
                            placeholder="{{ $isAr ? 'اكتب تفاصيل المكالمة أو الخطوة القادمة...' : 'Add call notes or next steps...' }}"
                            class="{{ $inputClass }}">{{ old('note') }}</textarea><button type="submit"
                            class="w-full rounded-xl bg-slate-700 px-5 py-3 font-semibold text-white hover:bg-slate-600">{{ $isAr ? 'إضافة ملاحظة' : 'Save note' }}</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
@endsection
