<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Export\ExportWorkspaceBackup;
use App\Actions\Import\ImportWorkspaceBackup;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('imports a backup as a new workspace with the same operations count', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'Карта', 'type' => 'checking', 'bank_id' => null, 'user_id' => $user->id, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 100,
        'occurred_on' => '2026-09-01', 'description' => 'x',
    ]);

    $payload = (new ExportWorkspaceBackup)->execute($ws);
    $imported = (new ImportWorkspaceBackup)->execute($user, $payload, null);

    expect($imported->id)->not->toBe($ws->id)
        ->and($imported->transactions()->count())->toBe($ws->transactions()->count())
        ->and($user->fresh()->current_workspace_id)->toBe($imported->id);
});

it('forbids a member from replacing the current workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => 'member']);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();
    $payload = (new ExportWorkspaceBackup)->execute($ws);

    (new ImportWorkspaceBackup)->execute($member, $payload, $ws);
})->throws(AuthorizationException::class);

it('rejects unknown schema versions', function () {
    $user = User::factory()->create();
    (new ImportWorkspaceBackup)->execute($user, ['schema_version' => 2], null);
})->throws(ValidationException::class);

it('imports categories without seeding defaults and uniquifies the name', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $payload = (new ExportWorkspaceBackup)->execute($ws);
    $imported = (new ImportWorkspaceBackup)->execute($user, $payload, null);

    expect($imported->categories()->count())->toBe(count($payload['categories']))
        ->and($imported->name)->toBe('Семья (копия)');
});

it('downloads a json backup attachment', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => $user->id,
        'opening_balance' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('export.json'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('monetka-backup.json');

    $payload = $response->json();

    expect($payload['schema_version'])->toBe(1)
        ->and($payload['workspace']['name'])->toBe('Семья')
        ->and($payload['workspace']['currency'])->toBe('RUB')
        ->and($payload)->toHaveKeys(['exported_at', 'banks', 'accounts', 'categories', 'transactions', 'goals', 'debts', 'limits', 'recurrences'])
        ->and($payload)->not->toHaveKey('invitations')
        ->and($payload)->not->toHaveKey('users');

    $exportedAccount = collect($payload['accounts'])->firstWhere('name', 'Карта');

    expect($exportedAccount)->not->toBeNull()
        ->and($exportedAccount['owner_email'])->toBe($user->email)
        ->and($exportedAccount)->not->toHaveKey('user_id')
        ->and($exportedAccount)->not->toHaveKey('password');
});

it('returns 403 when a member replaces via http', function () {
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

it('maps account owner_email to a current member or null', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $member = User::factory()->create(['email' => 'MEMBER@example.com']);
    $stranger = User::factory()->create(['email' => 'stranger@example.com']);
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => 'member']);

    (new CreateAccount)->execute($ws, $owner, [
        'name' => 'Личный',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => $member->id,
        'opening_balance' => 0,
    ]);
    (new CreateAccount)->execute($ws, $owner, [
        'name' => 'Общий',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $strangerAccount = $ws->accounts()->create([
        'name' => 'Чужой',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => $stranger->id,
    ]);

    $payload = (new ExportWorkspaceBackup)->execute($ws);
    $exported = collect($payload['accounts'])->keyBy('name');

    expect($exported['Личный']['owner_email'])->toBe('MEMBER@example.com')
        ->and($exported['Общий']['owner_email'])->toBeNull()
        ->and($exported['Чужой']['owner_email'])->toBe('stranger@example.com');

    $imported = (new ImportWorkspaceBackup)->execute($owner, $payload, $ws);
    $accounts = $imported->accounts()->get()->keyBy('name');

    expect($imported->id)->toBe($ws->id)
        ->and($accounts['Личный']->user_id)->toBe($member->id)
        ->and($accounts['Общий']->user_id)->toBeNull()
        ->and($accounts['Чужой']->user_id)->toBeNull()
        ->and($accounts['Чужой']->id)->not->toBe($strangerAccount->id);
});

it('renders workspace settings', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('workspaces.settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('workspaces/Settings'));
});

it('rejects invalid json with a russian message', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $file = UploadedFile::fake()->createWithContent('monetka-backup.json', '{not-json');

    $this->actingAs($user)
        ->from(route('workspaces.settings'))
        ->post(route('workspaces.import'), ['file' => $file])
        ->assertRedirect(route('workspaces.settings'))
        ->assertSessionHasErrors('file');

    expect(session('errors')->first('file'))->toBe('Некорректный JSON.');
});
