<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'id-prueba', 'services.google.client_secret' => 'secreto-prueba']);
    }

    private function simularGoogle(string $email = 'ana@gmail.com', bool $verificado = true, string $id = 'google-123'): void
    {
        $google = (new GoogleUser)->setRaw(['email_verified' => $verificado])->map([
            'id' => $id,
            'name' => 'Ana Quispe',
            'email' => $email,
            'avatar' => 'https://lh3.googleusercontent.com/a/foto',
        ]);

        $proveedor = Mockery::mock();
        $proveedor->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    public function test_boton_de_google_aparece_con_el_client_id(): void
    {
        $this->get(route('login'))->assertSee('Continuar con Google');
        $this->get(route('register'))->assertSee('Continuar con Google');

        config(['services.google.client_id' => null]);
        $this->get(route('login'))->assertDontSee('Continuar con Google');
        $this->get(route('auth.google.redirect'))->assertNotFound();
    }

    public function test_sin_secreto_vuelve_al_login_con_mensaje_claro(): void
    {
        config(['services.google.client_secret' => null]);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'El inicio con Google aún no está disponible: falta configurar GOOGLE_CLIENT_SECRET.']);
    }

    public function test_usuario_nuevo_queda_registrado_verificado_y_con_sesion(): void
    {
        $this->simularGoogle();

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

        $usuario = User::where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($usuario);
        $this->assertTrue($usuario->hasVerifiedEmail());
        $this->assertSame('google-123', $usuario->google_id);
        $this->assertSame('Ana Quispe', $usuario->name);
    }

    public function test_vincula_cuenta_existente_por_correo(): void
    {
        $existente = User::factory()->create(['email' => 'ana@gmail.com', 'password' => 'clave-original']);
        $this->simularGoogle();

        $this->get(route('auth.google.callback'));

        $existente->refresh();
        $this->assertAuthenticatedAs($existente);
        $this->assertSame('google-123', $existente->google_id);
        $this->assertTrue(Hash::check('clave-original', $existente->password), 'Una cuenta ya verificada conserva su contraseña.');
        $this->assertSame(1, User::count());
    }

    public function test_cuenta_no_verificada_pierde_la_contrasena_de_quien_la_registro(): void
    {
        // Un tercero registró el correo de Ana con su propia contraseña y nunca lo verificó.
        $usurpada = User::factory()->unverified()->create(['email' => 'ana@gmail.com', 'password' => 'clave-del-intruso']);
        $this->simularGoogle();

        $this->get(route('auth.google.callback'));

        $usurpada->refresh();
        $this->assertTrue($usurpada->hasVerifiedEmail());
        $this->assertFalse(Hash::check('clave-del-intruso', $usurpada->password));
    }

    public function test_rechaza_correo_de_google_no_verificado(): void
    {
        $this->simularGoogle(verificado: false);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_respeta_la_verificacion_en_dos_pasos(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@gmail.com']);
        $usuario->forceFill([
            'two_factor_secret' => encrypt('secreto'),
            'two_factor_recovery_codes' => encrypt(json_encode(['codigo'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->simularGoogle();

        $this->get(route('auth.google.callback'))->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($usuario->id, session('login.id'));
    }

    public function test_usuario_con_sesion_no_puede_usar_el_callback(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('auth.google.callback'))
            ->assertRedirect();
    }
}
