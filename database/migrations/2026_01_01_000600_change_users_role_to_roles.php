<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De um papel para um conjunto de papéis.
 *
 * A separação de deveres exige que alguém possa ter só ouvidoria, só RH, ou os
 * dois explicitamente. Com uma coluna única isso não se expressa: ou se inventa
 * um valor combinado ("hr_ombudsman"), que multiplica com cada área nova, ou se
 * dá a um papel poder sobre tudo, que é justamente o desvio a evitar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('roles')->nullable()->after('email');
        });

        // Preserva quem já existia. 'none' não tem equivalente: virar lista
        // vazia é exatamente o mesmo significado — nenhum acesso.
        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            $roles = in_array($user->role, ['hr', 'admin'], true) ? [$user->role] : [];
            DB::table('users')->where('id', $user->id)->update(['roles' => json_encode($roles)]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('none')->after('email');
        });

        foreach (DB::table('users')->select('id', 'roles')->get() as $user) {
            /** @var list<string> $roles */
            $roles = json_decode((string) $user->roles, true) ?: [];
            $role = in_array('admin', $roles, true) ? 'admin' : (in_array('hr', $roles, true) ? 'hr' : 'none');
            DB::table('users')->where('id', $user->id)->update(['role' => $role]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }
};
