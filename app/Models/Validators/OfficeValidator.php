<?php

namespace App\Models\Validators;

use App\Models\Office;
use App\Models\Tag;
use Illuminate\Validation\Rule;

class OfficeValidator
{
    public function validate(Office $office, array $attributes): array
    {
        return validator($attributes, [
            'title' => [Rule::when($office->exists, 'sometimes'), 'required', 'string'],
            'description' => [Rule::when($office->exists, 'sometimes'),'required', 'string'],
            'lat' => [Rule::when($office->exists, 'sometimes'),'required', 'numeric'],
            'lng' => [Rule::when($office->exists, 'sometimes'),'required', 'numeric'],
            'address_line1' => [Rule::when($office->exists, 'sometimes'),'required', 'string'],
            'price_per_day' => [Rule::when($office->exists, 'sometimes'),'required', 'integer', 'min:100'],
            'monthly_discount' => [Rule::when($office->exists, 'sometimes'),'required', 'integer', 'min:0'],

            'hidden' => ['bool'],

            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists(Tag::class, 'id')],

        ])->validate();
    }
}
