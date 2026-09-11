<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JobApplication;
use App\Models\PortalAccessLog;
use App\Models\User;
use App\Models\WhistleblowerReport;
use App\Support\Demo\PdfBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dados de demonstração do portal.
 *
 * Só para desenvolvimento: contas com senha conhecida, currículos fictícios e
 * denúncias inventadas. Recusa rodar em produção — semear usuário com senha
 * previsível num sistema real é abrir a porta.
 */
final class DemoPortalSeeder extends Seeder
{
    private const PASSWORD = 'montec-teste-2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoPortalSeeder não roda em produção.');

            return;
        }

        $users = $this->users();
        $applications = $this->applications();
        $reports = $this->reports();
        $this->auditTrail($users, $applications, $reports);

        $this->command?->info(sprintf(
            '%d usuários · %d candidaturas · %d denúncias · %d acessos',
            count($users),
            count($applications),
            count($reports),
            PortalAccessLog::count(),
        ));
    }

    /**
     * @return array<string, User>
     */
    private function users(): array
    {
        $definitions = [
            'rh' => ['Maria (RH)', 'rh@montec.test', ['hr']],
            'ouvidoria' => ['Paulo (Ouvidoria)', 'ouvidoria@montec.test', ['ombudsman']],
            'ambos' => ['Ana (RH + Ouvidoria)', 'ambos@montec.test', ['hr', 'ombudsman']],
            'admin' => ['Carlos (Admin)', 'admin@montec.test', ['admin']],
        ];

        $users = [];
        foreach ($definitions as $key => [$name, $email, $roles]) {
            $users[$key] = User::create([
                'name' => $name,
                'email' => $email,
                'password' => self::PASSWORD,
                'roles' => $roles,
            ]);
        }

        return $users;
    }

    /**
     * Currículos com conteúdo plausível e ADERÊNCIA VARIADA — é o que permite
     * ver a triagem produzindo notas diferentes em vez de um valor só.
     *
     * @return list<JobApplication>
     */
    private function applications(): array
    {
        $candidates = [
            [
                'Ana Lima', 'Montador/Soldador',
                ['Soldador com 8 anos de experiencia em processos MIG e MAG.',
                    'Certificacao NR-34 e NR-35 com validade em dia.',
                    'Atuou com chapa de 6mm a 1 polegada em estrutura metalica soldada.',
                    'Leitura de desenho tecnico e uso de paquimetro.',
                    'Formacao: Tecnico em Mecanica pelo SENAI.'],
            ],
            [
                'Bruno Costa', 'Montador/Soldador',
                ['Auxiliar de producao com 2 anos de experiencia.',
                    'Apoio em montagem de conjuntos e movimentacao de material.',
                    'Curso basico de solda em andamento.',
                    'Ensino medio completo.'],
            ],
            [
                'Carla Dias', 'Operador de Maquina de Corte',
                ['Operadora de maquina de corte a laser e plasma por 5 anos.',
                    'Programacao de corte CNC e ajuste de parametros por espessura.',
                    'Controle dimensional com paquimetro e relatorio de refugo.',
                    'NR-12 em maquinas e equipamentos.'],
            ],
            [
                'Diego Souza', 'Auxiliar Geral de Linha de Produção',
                ['Experiencia em linha de montagem e organizacao de posto de trabalho.',
                    'Movimentacao de material e apoio a operadores.',
                    'Disponibilidade para turnos.'],
            ],
            [
                'Elisa Rocha', 'Operador de Torno CNC',
                ['Operadora de torno CNC com 6 anos, programacao em codigo G.',
                    'Usinagem de pecas em aco carbono e inox com tolerancia de centesimo.',
                    'Metrologia: paquimetro, micrometro e relogio comparador.',
                    'Tecnico em Mecanica de Precisao pelo SENAI.'],
            ],
            [
                'Fabio Nunes', 'Pintor Industrial',
                ['Pintor industrial com 4 anos em pintura liquida e eletrostatica.',
                    'Preparacao de superficie por jateamento abrasivo.',
                    'Controle de espessura de pelicula e NR-33 para espaco confinado.'],
            ],
        ];

        $applications = [];
        foreach ($candidates as $index => [$name, $opening, $lines]) {
            $slug = Str::slug($name);
            $path = "2026/09/{$slug}.pdf";

            $pdf = PdfBuilder::withText(implode("\n", [
                $name,
                Str::slug($name, '.').'@exemplo.com',
                '(19) 9988-877'.$index,
                'CPF 123.456.789-0'.$index,
                '',
                ...$lines,
            ]));

            Storage::disk(config('montec.resume.disk'))->put($path, $pdf);

            $applications[] = JobApplication::create([
                'name' => $name,
                'email' => "candidato{$index}@exemplo.com",
                'phone' => '1999888776'.$index,
                'job_opening' => $opening,
                'message' => $index % 2 === 0 ? null : 'Tenho disponibilidade imediata.',
                'resume_path' => $path,
                'resume_original_name' => "curriculo-{$slug}.pdf",
                'resume_mime' => 'application/pdf',
                'resume_bytes' => strlen($pdf),
                'consent_accepted_at' => now()->subDays($index),
                'created_at' => now()->subDays($index),
                'updated_at' => now()->subDays($index),
            ]);
        }

        return $applications;
    }

    /**
     * @return list<WhistleblowerReport>
     */
    private function reports(): array
    {
        return [
            WhistleblowerReport::create([
                'is_anonymous' => true,
                'subject' => 'Descarte irregular de solvente',
                'description' => 'No dia 12/08, por volta das 22h, no setor de pintura, presenciei o descarte de solvente diretamente na rede pluvial da planta. Havia dois colaboradores do turno da noite envolvidos.',
            ]),
            WhistleblowerReport::create([
                'is_anonymous' => false,
                'name' => 'Carlos Lima',
                'email' => 'carlos.lima@exemplo.com',
                'subject' => 'Conduta inadequada na expedicao',
                'description' => 'Relato de conduta inadequada do supervisor da expedicao com a equipe durante o turno da noite, de forma recorrente nas ultimas semanas.',
            ]),
            WhistleblowerReport::create([
                'is_anonymous' => true,
                'subject' => 'Uso de EPI nao fiscalizado',
                'description' => 'Na area de solda, a fiscalizacao do uso de mascara de solda e luva tem sido inconsistente no turno da tarde, o que expoe a equipe a risco desnecessario.',
            ]),
        ];
    }

    /**
     * @param  array<string, User>  $users
     * @param  list<JobApplication>  $applications
     * @param  list<WhistleblowerReport>  $reports
     */
    private function auditTrail(array $users, array $applications, array $reports): void
    {
        $entries = [
            [$users['rh'], PortalAccessLog::RESOURCE_RESUME, $applications[0]->id, 'downloaded'],
            [$users['rh'], PortalAccessLog::RESOURCE_RESUME, $applications[4]->id, 'downloaded'],
            [$users['ouvidoria'], PortalAccessLog::RESOURCE_REPORT, $reports[0]->id, 'viewed'],
            [$users['admin'], PortalAccessLog::RESOURCE_RESUME, $applications[2]->id, 'downloaded'],
            [$users['ouvidoria'], PortalAccessLog::RESOURCE_REPORT, $reports[2]->id, 'viewed'],
            [$users['rh'], PortalAccessLog::RESOURCE_RESUME, $applications[1]->id, 'analyzed'],
            [$users['admin'], PortalAccessLog::RESOURCE_REPORT, $reports[1]->id, 'viewed'],
        ];

        foreach ($entries as $index => [$user, $type, $resourceId, $action]) {
            PortalAccessLog::create([
                'user_id' => $user->id,
                'resource_type' => $type,
                'resource_id' => $resourceId,
                'action' => $action,
                'ip_address' => '192.168.1.'.(20 + $index * 7),
            ]);
        }
    }
}
