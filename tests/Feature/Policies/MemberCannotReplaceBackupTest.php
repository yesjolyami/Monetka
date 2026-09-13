<?php

use App\Actions\Export\ExportWorkspaceBackup;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('returns 403 when a member replaces the workspace backup', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => 'member']);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();
    $payload = (new ExportWorkspaceBackup)->execute($ws);

    $file = UploadedFile::fake()->createWithContent(
        'monetka-backup.json',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    );

    $this->actingAs($member)
        ->post(route('workspaces.import'), [
            'file' => $file,
            'replace' => '1',
        ])
        ->assertForbidden();
});
