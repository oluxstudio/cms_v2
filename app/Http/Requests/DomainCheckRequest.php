<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validates the "Find a domain" availability lookup (?domain=mysalon[.co.uk]). */
class DomainCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-zA-Z0-9.\- ]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.regex' => 'Domain may only contain letters, numbers, dots and hyphens.',
        ];
    }
}
