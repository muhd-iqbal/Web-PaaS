<?php

namespace App\Http\Requests;

use App\Enums\ProjectRuntime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:projects,slug'],
            'runtime' => ['required', Rule::enum(ProjectRuntime::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user()->loadMissing('plan');

                if (! $user->hasHostingAccess() || ! $user->plan) {
                    $validator->errors()->add('plan', 'Activate a hosting plan before creating a project.');
                } elseif ($user->projects()->count() >= $user->plan->website_limit) {
                    $validator->errors()->add('plan', 'Your hosting plan website limit has been reached.');
                }
            },
        ];
    }
}
