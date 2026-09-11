<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;

/**
 * Cria conta do portal.
 *
 * Substitui a tela de registro: o portal dá acesso a currículo — dado pessoal de
 * quem confiou na empresa — e cadastro aberto seria um convite. A senha é lida
 * de forma oculta, sem eco e sem ficar no histórico do shell.
 */
final class CreateUser extends Command
{
    protected $signature = 'montec:create-user
        {email : E-mail de acesso}
        {--name= : Nome do usuário}
        {--roles=hr : Papéis separados por vírgula — hr, ombudsman, admin}';

    protected $description = 'Cria um usuário do portal interno';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $roles = $this->parseRoles((string) $this->option('roles'));

        if ($roles === null) {
            $this->error('Papel inválido. Use: '.implode(', ', UserRole::values()).'.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask('Nome do usuário'));

        // Nunca como argumento de linha de comando: ficaria no histórico do shell
        // e visível em `ps` para qualquer processo da máquina.
        $plainPassword = promptPassword(
            label: 'Senha (mínimo 12 caracteres)',
            required: true,
        );

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $plainPassword],
            [
                'email' => ['required', 'email', 'max:180', 'unique:users,email'],
                'name' => ['required', 'string', 'min:2', 'max:120'],
                // 12 caracteres porque esta conta abre currículos: senha curta
                // aqui custa mais caro do que em conta comum.
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'roles' => array_map(fn (UserRole $role): string => $role->value, $roles),
        ]);

        $this->info("Usuário criado: {$user->email} ({$user->roleLabels()})");

        if (count($roles) > 1) {
            // Acumular papéis é legítimo, mas nunca deve passar despercebido:
            // a separação de deveres existe para ser a regra, não a exceção.
            $this->warn('Atenção: esta conta acumula mais de uma área do portal.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<UserRole>|null null quando algum papel é inválido
     */
    private function parseRoles(string $raw): ?array
    {
        $names = array_filter(array_map('trim', explode(',', $raw)));

        if ($names === []) {
            return null;
        }

        $roles = [];
        foreach ($names as $name) {
            $role = UserRole::tryFrom($name);
            if ($role === null) {
                return null;
            }
            $roles[$name] = $role;
        }

        return array_values($roles);
    }
}
