<?php

namespace DhruvilNagar\ActionEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteBulkActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string'],
            'model' => ['required', 'string'],
            'filters' => ['sometimes', 'array'],
            'filters.ids' => ['sometimes', 'array'],
            'filters.ids.*' => ['required', 'integer'],
            'filters.where' => ['sometimes', 'array'],
            'filters.where.*' => ['array', 'min:2', 'max:3'],
            'filters.where_in' => ['sometimes', 'array'],
            'filters.where_in.*' => ['array', 'size:2'],
            'filters.where_not_in' => ['sometimes', 'array'],
            'filters.where_not_in.*' => ['array', 'size:2'],
            'filters.where_between' => ['sometimes', 'array'],
            'filters.where_between.*' => ['array', 'size:2'],
            'parameters' => ['sometimes', 'array'],
            'options' => ['sometimes', 'array'],
            'options.batch_size' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'options.with_undo' => ['sometimes', 'boolean'],
            'options.undo_expiry_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'options.sync' => ['sometimes', 'boolean'],
            'options.dry_run' => ['sometimes', 'boolean'],
            'options.schedule_for' => ['sometimes', 'date', 'after:now'],
            'options.schedule_timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'An action name is required.',
            'model.required' => 'A model class is required.',
            'options.schedule_for.after' => 'Scheduled time must be in the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure model is fully qualified
        if ($this->has('model') && !str_starts_with($this->model, '\\')) {
            $this->merge([
                'model' => '\\' . ltrim($this->model, '\\'),
            ]);
        }
    }
}
