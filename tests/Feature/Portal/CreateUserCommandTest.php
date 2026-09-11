<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cria_usuario_do_rh_com_senha_hasheada(): void
    {
        $this->artisan('montec:create-user', ['email' => 'rh@montecmococa.com.br', '--name' => 'Maria RH'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'senha-bem-longa-123')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame([UserRole::Hr], $user->roles());
        // Senha em claro no banco é o pior erro possível numa tabela de acesso.
        $this->assertNotSame('senha-bem-longa-123', $user->getAuthPassword());
        $this->assertTrue(Hash::check('senha-bem-longa-123', $user->getAuthPassword()));
    }

    /** Esta conta abre currículos: senha curta custa mais caro aqui. */
    #[Test]
    public function recusa_senha_com_menos_de_12_caracteres(): void
    {
        $this->artisan('montec:create-user', ['email' => 'rh@montecmococa.com.br', '--name' => 'Maria RH'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'curta123')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function aceita_papeis_acumulados(): void
    {
        $this->artisan('montec:create-user', [
            'email' => 'ambos@montecmococa.com.br',
            '--name' => 'Quem Faz os Dois',
            '--roles' => 'hr,ombudsman',
        ])->expectsQuestion('Senha (mínimo 12 caracteres)', 'senha-bem-longa-123')
            ->assertSuccessful();

        $this->assertSame(
            [UserRole::Hr, UserRole::Ombudsman],
            User::sole()->roles(),
        );
    }

    /** Um papel inválido invalida o comando todo — nada é criado pela metade. */
    #[Test]
    public function recusa_papel_invalido(): void
    {
        $this->artisan('montec:create-user', [
            'email' => 'x@montecmococa.com.br',
            '--name' => 'X',
            '--roles' => 'hr,superusuario',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    #[Test]
    public function recusa_email_repetido(): void
    {
        User::factory()->create(['email' => 'rh@montecmococa.com.br']);

        $this->artisan('montec:create-user', ['email' => 'rh@montecmococa.com.br', '--name' => 'Outro'])
            ->expectsQuestion('Senha (mínimo 12 caracteres)', 'senha-bem-longa-123')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
