<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class CLAAuthService
{
    public function getAuthUrl(): string
    {
        $host = config('services.cla.host');
        $identifier = config('services.cla.identifier');

        return "{$host}/authentification/{$identifier}";
    }

    public function validateTicket(string $ticket): ?array
    {
        $host = config('services.cla.host');
        $identifier = config('services.cla.identifier');
        $url = "{$host}/authentification/{$identifier}/".urlencode($ticket);

        try {
            $response = Http::get($url);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return $data['success'] ? $data['payload'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function createOrUpdateUser(array $claData): User
    {
        $user = User::where('username', $claData['username'])->first();

        if ($user) {
            // Existing user - update email, login time and promo
            $user->school_email = $claData['emailSchool'];
            $user->promo = $claData['promo'];
            $user->logged_on = now();
            $user->save();
        } else {
            // New user - create
            $user = User::create([
                'username' => $claData['username'],
                'first_name' => $claData['firstName'],
                'last_name' => $claData['lastName'],
                'school_email' => $claData['emailSchool'],
                'promo' => $claData['promo'],
                'alumni' => 0,
                'logged_on' => now(),
            ]);
        }

        return $user;
    }
}
