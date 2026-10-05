<?php

use App\Models\User;
use App\Support\AiSettings;

beforeEach(function () {
    $this->withoutVite();
    $this->envPath = storage_path('framework/testing/ai-settings-'.uniqid().'.env');
    file_put_contents($this->envPath, "APP_NAME=Monetka\nUNCHANGED=value\nAI_PROVIDER=openrouter\nAI_API_KEY=old-secret\nAI_MODEL=openrouter/free\nAI_BASE_URL=https://openrouter.ai/api/v1\n");
    $this->app->instance(AiSettings::class, new AiSettings($this->envPath));
});

afterEach(function () {
    if (isset($this->envPath) && is_file($this->envPath)) {
        unlink($this->envPath);
    }
});

it('allows only administrators to open AI settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $member = User::factory()->create(['is_admin' => false]);

    $this->actingAs($member)->get(route('admin.ai.edit'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.ai.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ai/Index')
            ->where('settings.api_key_configured', false)
            ->has('providers.openrouter')
            ->has('providers.proxyapi')
            ->has('providers.vsegpt'));
});

it('updates only whitelisted AI environment values and never returns the key', function () {
    config([
        'services.ai.key' => 'old-secret',
        'services.ai.provider' => 'openrouter',
        'services.ai.model' => 'openrouter/free',
        'services.ai.base_url' => 'https://openrouter.ai/api/v1',
    ]);

    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->put(route('admin.ai.update'), [
            'provider' => 'proxyapi',
            'api_key' => 'new-$ecret',
            'model' => 'openai/gpt-5-mini',
            'base_url' => 'https://api.proxyapi.ru/v1/',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $contents = file_get_contents($this->envPath);

    expect($contents)
        ->toContain('UNCHANGED=value')
        ->toContain('AI_PROVIDER="proxyapi"')
        ->toContain('AI_API_KEY="new-\\$ecret"')
        ->toContain('AI_MODEL="openai/gpt-5-mini"')
        ->toContain('AI_BASE_URL="https://api.proxyapi.ru/v1"');

    $this->actingAs($admin)
        ->get(route('admin.ai.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('settings.api_key_configured', true)
            ->where('settings.api_key_hint', 'new-••••••••cret')
            ->missing('settings.api_key'));
});

it('keeps the saved key when the key field is blank', function () {
    config(['services.ai.key' => 'old-secret']);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->put(route('admin.ai.update'), [
            'provider' => 'vsegpt',
            'api_key' => '',
            'model' => 'openai/gpt-4o-mini',
            'base_url' => 'https://api.vsegpt.ru/v1',
        ])
        ->assertSessionHasNoErrors();

    expect(file_get_contents($this->envPath))->toContain('AI_API_KEY=old-secret');
});
