<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiSettingsRequest;
use App\Support\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function edit(Request $request, AiSettings $settings): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('admin/ai/Index', [
            'settings' => $settings->current(),
            'providers' => AiSettings::providers(),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, AiSettings $settings): RedirectResponse
    {
        $settings->update($request->settings());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'AI-провайдер сохранён. Новые запросы пойдут через него.',
        ]);

        return back();
    }
}
