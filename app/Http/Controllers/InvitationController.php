<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\DeclineInvitation;
use App\Actions\Invitations\SendInvitation;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, SendInvitation $sendInvitation): RedirectResponse
    {
        $data = $request->validated();

        $sendInvitation->execute($request->user(), $request->attributes->get('workspace'), $data['email']);

        return back();
    }

    public function show(Invitation $invitation): Response
    {
        $invitation->load('workspace');

        return Inertia::render('invitations/Show', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'workspace' => [
                    'name' => $invitation->workspace->name,
                ],
            ],
        ]);
    }

    public function accept(Invitation $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->execute(request()->user(), $invitation);

        return redirect()->route('dashboard');
    }

    public function decline(Invitation $invitation, DeclineInvitation $declineInvitation): RedirectResponse
    {
        $declineInvitation->execute(request()->user(), $invitation);

        return redirect()->route('home');
    }
}
