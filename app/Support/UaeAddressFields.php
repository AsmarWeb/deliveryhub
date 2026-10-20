<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UaeAddressFields
{
    public const EMIRATES = ['abu_dhabi', 'dubai', 'sharjah', 'ajman', 'umm_al_quwain', 'ras_al_khaimah', 'fujairah'];

    public static function prepare(Request $request): void
    {
        if (is_string($request->input('makani_number'))) {
            $number = strtr($request->input('makani_number'), array_combine(
                preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
                str_split('01234567890123456789')
            ));
            $number = preg_replace('/\s+/u', '', $number);
            $request->merge(['makani_number' => $number === '' ? null : $number]);
        }
    }

    public static function rules(Request $request): array
    {
        return [
            'emirate' => ['nullable', Rule::in(self::EMIRATES)],
            'landmark' => ['nullable', 'string', 'max:255'],
            'makani_number' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', Rule::prohibitedIf($request->input('emirate') !== 'dubai')],
        ];
    }
}
