<?php

namespace App\Http\Requests;

use App\Enums\ProjectRuntime;
use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
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
            'slug' => [
                'required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')->ignore($this->route('project')),
            ],
            'runtime' => ['required', Rule::enum(ProjectRuntime::class)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $project = $this->route('project');

            if ($project?->status === ProjectStatus::Deploying) {
                $validator->errors()->add('name', 'Wait for the current deployment to finish before changing this project.');
            }

            if ($this->input('runtime') !== ProjectRuntime::Php->value && $project?->hostedDatabase()->exists()) {
                $validator->errors()->add('runtime', 'Delete the managed database before changing to the static runtime.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
    }
}
