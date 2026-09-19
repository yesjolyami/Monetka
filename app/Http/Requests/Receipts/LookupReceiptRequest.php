<?php

namespace App\Http\Requests\Receipts;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LookupReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('view', $workspace);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'qr' => ['nullable', 'string', 'max:500', 'required_without:photo'],
            'photo' => ['nullable', 'image', 'max:5120', 'required_without:qr'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qr.required_without' => 'Вставьте строку QR или загрузите фото чека.',
            'photo.required_without' => 'Вставьте строку QR или загрузите фото чека.',
            'photo.image' => 'Нужно изображение с QR-кодом.',
            'photo.max' => 'Файл слишком большой (до 5 МБ).',
        ];
    }
}
