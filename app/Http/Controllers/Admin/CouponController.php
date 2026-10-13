<?php

namespace App\Http\Controllers\Admin;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CouponController extends ResourceController
{
    protected function model(): string
    {
        return Coupon::class;
    }

    protected function title(): string
    {
        return 'Coupons';
    }

    protected function base(): string
    {
        return '/admin/coupons';
    }

    protected function searchColumn(): ?string
    {
        return 'code';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'format' => 'mono'],
            ['key' => 'value_label', 'label' => 'Discount', 'format' => 'text'],
            ['key' => 'min_order_amount', 'label' => 'Min order', 'format' => 'money'],
            ['key' => 'used_count', 'label' => 'Used', 'format' => 'muted'],
            ['key' => 'expires_at', 'label' => 'Expires', 'format' => 'muted'],
            ['key' => 'is_active', 'label' => 'Status', 'format' => 'active'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Coupon code', 'type' => 'text', 'placeholder' => 'e.g. EID20', 'required' => true],
            ['key' => 'type', 'label' => 'Discount type', 'type' => 'select', 'default' => 'percent', 'options' => [
                ['value' => 'percent', 'label' => 'Percentage (%)'],
                ['value' => 'fixed', 'label' => 'Fixed amount (BDT)'],
            ]],
            ['key' => 'value', 'label' => 'Discount value', 'type' => 'number', 'placeholder' => 'e.g. 10', 'required' => true],
            ['key' => 'min_order_amount', 'label' => 'Minimum order amount', 'type' => 'number', 'placeholder' => 'e.g. 1000', 'default' => 0],
            ['key' => 'max_discount', 'label' => 'Max discount (percent coupons)', 'type' => 'number', 'placeholder' => 'e.g. 500'],
            ['key' => 'usage_limit', 'label' => 'Total usage limit (blank = unlimited)', 'type' => 'number', 'placeholder' => 'e.g. 100'],
            ['key' => 'starts_at', 'label' => 'Starts on', 'type' => 'date'],
            ['key' => 'expires_at', 'label' => 'Expires on', 'type' => 'date'],
            ['key' => 'once_per_user', 'label' => 'One use per customer', 'type' => 'checkbox', 'default' => true],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $item): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($item?->id)],
            'type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'numeric', 'gt:0', request('type') === 'percent' ? 'max:100' : 'max:99999999'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'max_discount' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'once_per_user' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;

        return $data;
    }
}
