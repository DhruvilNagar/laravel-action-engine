<?php

namespace DhruvilNagar\ActionEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBulkActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'filters.where_in' => ['sometimes', 'array'],
            'parameters' => ['sometimes', 'array'],
            'preview_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('model') && !str_starts_with($this->model, '\\')) {
            $this->merge([
                'model' => '\\' . ltrim($this->model, '\\'),
            ]);
        }
    }
}
