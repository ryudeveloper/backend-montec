<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactRequest;
use App\Models\JobApplication;
use App\Models\WhistleblowerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PurgeExpiredSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private function contact(int $daysAgo): ContactRequest
    {
        $contact = ContactRequest::create([
            'name' => 'Ana', 'email' => 'a@b.com', 'phone' => '19996566767',
            'subject' => 'Orçamento', 'message' => str_repeat('x', 25),
        ]);
        $contact->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $contact;
    }

    private function application(int $daysAgo, string $path): JobApplication
    {
        Storage::disk('resumes')->put($path, '%PDF-1.4');

        $application = JobApplication::create([
            'name' => 'João', 'email' => 'j@b.com', 'phone' => '19996566767',
            'job_opening' => 'Soldador', 'resume_path' => $path,
            'resume_original_name' => 'cv.pdf', 'resume_mime' => 'application/pdf',
            'resume_bytes' => 9,
        ]);
        $application->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $application;
    }

    #[Test]
    public function apaga_registros_alem_do_prazo_e_preserva_os_recentes(): void
    {
        Storage::fake('resumes');

        $this->contact(400);
        $recent = $this->contact(10);

        $this->artisan('montec:purge-expired')->assertSuccessful();

        $this->assertSame(1, ContactRequest::count());
        $this->assertTrue(ContactRequest::whereKey($recent->id)->exists());
    }

    /**
     * O arquivo tem de sair junto do registro: currículo órfão em disco é
     * retenção de dado pessoal sem base legal.
     */
    #[Test]
    public function apaga_o_arquivo_do_curriculo_junto_do_registro(): void
    {
        Storage::fake('resumes');

        $this->application(400, '2024/01/antigo.pdf');
        $this->application(5, '2026/09/recente.pdf');

        $this->artisan('montec:purge-expired')->assertSuccessful();

        Storage::disk('resumes')->assertMissing('2024/01/antigo.pdf');
        Storage::disk('resumes')->assertExists('2026/09/recente.pdf');
        $this->assertSame(1, JobApplication::count());
    }

    #[Test]
    public function dry_run_nao_apaga_nada(): void
    {
        Storage::fake('resumes');

        $this->contact(400);
        $this->application(400, '2024/01/antigo.pdf');

        $this->artisan('montec:purge-expired', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, ContactRequest::count());
        $this->assertSame(1, JobApplication::count());
        Storage::disk('resumes')->assertExists('2024/01/antigo.pdf');
    }

    #[Test]
    public function a_ouvidoria_tem_prazo_mais_longo_que_os_demais(): void
    {
        Storage::fake('resumes');

        $report = WhistleblowerReport::create([
            'is_anonymous' => true, 'subject' => 'Assunto',
            'description' => str_repeat('x', 45),
        ]);
        $report->forceFill(['created_at' => now()->subDays(400)])->save();

        $this->artisan('montec:purge-expired')->assertSuccessful();

        // 400 dias passa do prazo de contato (365) mas não do de ouvidoria (1825).
        $this->assertSame(1, WhistleblowerReport::count());
    }
}
