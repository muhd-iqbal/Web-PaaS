<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadProjectArchiveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxUploadMb = max(1, (int) $this->user()?->plan?->max_upload_mb);

        return [
            'archive' => [
                'required',
                File::types(['zip'])->max($maxUploadMb * 1024),
                'extensions:zip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archive.extensions' => 'The website upload must use the .zip extension.',
        ];
    }
}
