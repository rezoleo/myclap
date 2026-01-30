<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CLAAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CLAController extends Controller
{
    public function __construct(
        private CLAAuthService $claAuth
    ) {}

    public function login()
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return redirect($this->claAuth->getAuthUrl());
    }

    public function handleCallback(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        $ticket = $request->get('ticket');

        if (! $ticket) {
            abort(400, 'Ticket manquant');
        }

        $claData = $this->claAuth->validateTicket($ticket);

        if (! $claData) {
            abort(500, 'Authentification CLA échouée');
        }

        $user = $this->claAuth->createOrUpdateUser($claData);

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
