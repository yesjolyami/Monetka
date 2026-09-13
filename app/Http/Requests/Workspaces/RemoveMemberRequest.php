<?php

namespace App\Http\Requests\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class RemoveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');
        $member = $this->route('member');

        return $workspace instanceof Workspace
            && $member instanceof User
            && $this->user()?->can('removeMember', $workspace);
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
