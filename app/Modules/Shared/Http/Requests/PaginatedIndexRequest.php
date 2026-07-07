<?php

namespace App\Modules\Shared\Http\Requests;

abstract class PaginatedIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_direction' => ['sometimes', 'nullable', 'in:asc,desc'],
        ], $this->filterRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFilters(): array
    {
        $validated = $this->validated();

        unset($validated['limit'], $validated['offset'], $validated['search'], $validated['sort_by'], $validated['sort_direction']);

        return array_filter(
            $validated,
            static fn ($value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function filterRules(): array;
}
