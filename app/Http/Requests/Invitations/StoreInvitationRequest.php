<?php

namespace App\Http\Requests\Invitations;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('invite', $workspace);
    }

    /**
     * @return array<string, list<string>|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
