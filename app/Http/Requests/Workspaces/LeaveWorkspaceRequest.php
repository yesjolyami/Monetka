<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class LeaveWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->attributes->get('workspace');

        return $workspace instanceof Workspace
            && $this->user()?->can('view', $workspace);
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
