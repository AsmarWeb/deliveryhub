@extends('layouts.dashboard')

@section('content')
    @php
        $ar = app()->getLocale() === 'ar';
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">

        <div>
            <a href="{{ route('driver.assignments.show', $assignment) }}" class="text-sm text-blue-600 hover:underline">
                {{ $ar ? '← العودة إلى المهمة' : '← Back to assignment' }}
            </a>

            <h1 class="mt-4 text-2xl font-bold">
                {{ $ar ? 'إثبات تسليم الشحنة' : 'Proof of Delivery' }}
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                {{ $ar ? 'أدخل بيانات المستلم لتأكيد التسليم.' : 'Enter recipient details to confirm delivery.' }}
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-red-700">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('driver.assignments.proof.store', $assignment) }}" enctype="multipart/form-data"
            class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

            @csrf

            <div>
                <label for="recipient_name" class="mb-2 block font-semibold">
                    {{ $ar ? 'اسم المستلم' : 'Recipient Name' }} *
                </label>

                <input id="recipient_name" type="text" name="recipient_name"
                    value="{{ old('recipient_name', $delivery->recipient_name) }}" maxlength="255" required
                    class="w-full rounded-xl border border-slate-300 px-4 py-3">
            </div>

            <div>
                <label for="notes" class="mb-2 block font-semibold">
                    {{ $ar ? 'ملاحظات التسليم' : 'Delivery Notes' }}
                </label>

                <textarea id="notes" name="notes" rows="4" maxlength="2000"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3">{{ old('notes') }}</textarea>
            </div>

            <div>
                <label for="photo" class="mb-2 block font-semibold">
                    {{ $ar ? 'صورة إثبات التسليم (اختياري)' : 'Delivery Photo (Optional)' }}
                </label>

                <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp"
                    class="w-full rounded-xl border border-slate-300 p-3">

                <p class="mt-1 text-xs text-slate-500">
                    JPG, PNG, WebP — Max 5 MB
                </p>
            </div>

            <div>
                <label for="signature" class="mb-2 block font-semibold">
                    {{ $ar ? 'صورة التوقيع (اختياري)' : 'Signature Image (Optional)' }}
                </label>

                <input id="signature" type="file" name="signature" accept=".jpg,.jpeg,.png,.webp"
                    class="w-full rounded-xl border border-slate-300 p-3">

                <p class="mt-1 text-xs text-slate-500">
                    JPG, PNG, WebP — Max 2 MB
                </p>
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-green-600 px-5 py-3 font-semibold text-white hover:bg-green-700">
                {{ $ar ? 'تأكيد التسليم' : 'Confirm Delivery' }}
            </button>
        </form>
    </div>
@endsection
