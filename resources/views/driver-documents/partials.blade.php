@php
    $ar = app()->getLocale() === 'ar';
    $labels = ['missing' => ['لم يُرفع', 'Not uploaded'], 'pending' => ['قيد المراجعة', 'In review'], 'approved' => ['معتمد', 'Approved'], 'rejected' => ['يحتاج تحديثًا', 'Needs an update'], 'expired' => ['منتهي الصلاحية', 'Expired']];
@endphp
<div class="grid gap-5 lg:grid-cols-2">
@foreach($documents as $document)
    <article class="rounded-2xl border border-white/10 bg-slate-900 p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-bold text-white">{{ $document[$ar ? 'label_ar' : 'label_en'] }}</h2>
            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $document['status'] === 'approved' ? 'bg-emerald-500/10 text-emerald-400' : (in_array($document['status'], ['rejected', 'expired']) ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') }}">{{ $labels[$document['status']][$ar ? 0 : 1] }}</span>
        </div>
        @if($document['expires_on'])<p class="text-sm text-slate-300">{{ $ar ? 'انتهاء الصلاحية: ' : 'Expires: ' }}{{ $document['expires_on'] }}</p>@endif
        @if($document['rejection_reason'])<p class="rounded-xl bg-red-500/10 p-3 text-sm text-red-400">{{ $document['rejection_reason'] }}</p>@endif
        @if($document['id'])
            <a class="inline-flex min-h-11 items-center rounded-xl border border-white/10 px-4 font-semibold text-emerald-400" href="{{ route('driver.documents.file', $document['id']) }}" target="_blank" rel="noopener noreferrer">{{ $ar ? 'عرض المستند' : 'View document' }}</a>
        @endif
        @if($adminView ?? false)
            @if($document['id'])
            <form method="POST" action="{{ route('admin.drivers.documents.review', $document['id']) }}" class="space-y-3">
                @csrf
                <label class="block text-sm text-slate-300" for="reason-{{ $document['type'] }}">{{ $ar ? 'ملاحظات المراجعة (مطلوبة عند الرفض)' : 'Review notes (required for rejection)' }}</label>
                <textarea id="reason-{{ $document['type'] }}" name="reason" maxlength="1000" class="w-full rounded-xl border border-white/10 bg-slate-800 p-3 text-white" rows="2"></textarea>
                <div class="flex flex-wrap gap-3">
                    <button name="status" value="approved" @disabled($document['status'] === 'expired') class="min-h-11 rounded-xl bg-emerald-600 px-5 font-bold text-white disabled:opacity-40">{{ $ar ? 'قبول' : 'Approve' }}</button>
                    <button name="status" value="rejected" class="min-h-11 rounded-xl border border-red-400/40 px-5 font-bold text-red-400">{{ $ar ? 'طلب تحديث' : 'Request update' }}</button>
                </div>
            </form>
            @endif
        @else
            <form method="POST" action="{{ route('driver.documents.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="hidden" name="type" value="{{ $document['type'] }}">
                <label class="block text-sm text-slate-300" for="file-{{ $document['type'] }}">{{ $document['id'] ? ($ar ? 'رفع نسخة بديلة' : 'Upload replacement') : ($ar ? 'اختر المستند' : 'Choose document') }}</label>
                <input id="file-{{ $document['type'] }}" name="file" type="file" required accept="{{ $document['type'] === 'portrait' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,application/pdf' }}" class="block w-full text-sm text-slate-300">
                @if($document['expiry_required'])
                    <label class="block text-sm text-slate-300" for="expiry-{{ $document['type'] }}">{{ $ar ? 'تاريخ الانتهاء' : 'Expiry date' }}</label>
                    <input id="expiry-{{ $document['type'] }}" name="expires_on" type="date" required min="{{ today()->format('Y-m-d') }}" class="w-full rounded-xl border border-white/10 bg-slate-800 p-3 text-white">
                @endif
                <button class="min-h-11 rounded-xl bg-emerald-600 px-5 font-bold text-white">{{ $ar ? 'إرسال للمراجعة' : 'Submit for review' }}</button>
            </form>
        @endif
    </article>
@endforeach
</div>
