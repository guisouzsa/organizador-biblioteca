<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user.manage') ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [$user ? 'prohibited' : 'required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Já existe um usuário com este e-mail.', 'email.prohibited' => 'O e-mail não pode ser alterado.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil'];
    }
}
