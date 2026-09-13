<?php

namespace App\Http\Controllers;

use App\Actions\Settings\UpdateTheme;
use App\Enums\Appearance;
use App\Http\Requests\Settings\UpdateThemeRequest;
use Illuminate\Http\RedirectResponse;

class ThemeController extends Controller
{
    public function update(UpdateThemeRequest $request, UpdateTheme $updateTheme): RedirectResponse
    {
        $data = $request->validated();

        $updateTheme->execute($request->user(), Appearance::from($data['theme']));

        return back();
    }
}
