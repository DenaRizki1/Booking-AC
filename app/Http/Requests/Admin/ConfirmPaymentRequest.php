<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmPaymentRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'payment';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Pilih metode pembayaran.',
            'payment_method.*' => 'Metode pembayaran tidak valid.',
            'payment_proof.mimes' => 'Bukti bayar harus foto JPG, PNG, atau WEBP.',
            'payment_proof.max' => 'Ukuran bukti bayar maksimal 5 MB.',
            'payment_proof.*' => 'Bukti bayar tidak valid.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from((string) $this->validated('payment_method'));
    }
}
