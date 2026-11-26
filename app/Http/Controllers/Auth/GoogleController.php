<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::habilitado(), 404);

        if (blank(config('services.google.client_secret'))) {
            return $this->volverAlLogin('El inicio con Google aún no está disponible: falta configurar GOOGLE_CLIENT_SECRET.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::habilitado(), 404);

        try {
            /** @var GoogleUser $google */
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->volverAlLogin('No se pudo iniciar sesión con Google. Inténtelo nuevamente.');
        }

        $email = Str::lower((string) $google->getEmail());
        if ($email === '' || ($google->user['email_verified'] ?? false) !== true) {
            return $this->volverAlLogin('Su cuenta de Google no tiene un correo verificado.');
        }

        $usuario = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first();

        if ($usuario) {
            $this->vincular($usuario, $google);
        } else {
            $usuario = new User(['name' => $google->getName() ?: Str::before($email, '@'), 'email' => $email, 'password' => Str::password(40)]);
            $usuario->forceFill([
                'google_id' => $google->getId(),
                'avatar' => $google->getAvatar(),
                'email_verified_at' => now(),
            ])->save();
        }

        // Si activó 2FA, Google no lo reemplaza: pasa por el mismo desafío que el login con contraseña.
        if ($usuario->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $usuario->getKey(), 'login.remember' => true]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public static function habilitado(): bool
    {
        return filled(config('services.google.client_id'));
    }

    private function vincular(User $usuario, GoogleUser $google): void
    {
        $cambios = ['google_id' => $google->getId(), 'avatar' => $google->getAvatar() ?? $usuario->avatar];

        if (! $usuario->hasVerifiedEmail()) {
            // Alguien registró este correo con contraseña y nunca lo verificó. Google prueba que el dueño real
            // es quien entra ahora: se invalida esa contraseña para que el registro previo no conserve acceso.
            $cambios['email_verified_at'] = now();
            $cambios['password'] = Str::password(40);
        }

        $usuario->forceFill($cambios)->save();
    }

    private function volverAlLogin(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
