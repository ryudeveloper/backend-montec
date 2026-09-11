<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\JobApplication\InvalidResumeException;
use App\Domain\JobApplication\JobApplicationService;
use App\Http\Requests\Api\StoreJobApplicationRequest;
use App\Mail\JobApplicationReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class JobApplicationController
{
    public function __construct(private JobApplicationService $service) {}

    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        try {
            $application = $this->service->record(
                $request->toAttributes(),
                $request->resumeSource(),
            );
        } catch (InvalidResumeException $exception) {
            // Vira erro de validação no campo do arquivo: o cliente já sabe
            // exibir `message`, e 422 é a semântica correta para entrada inválida.
            throw ValidationException::withMessages([
                'curriculo' => $exception->getMessage(),
            ]);
        }

        Mail::to(config('montec.recipients.careers'))
            ->send(new JobApplicationReceived($application));

        return response()->json([
            'message' => 'Currículo recebido. Boa sorte no processo!',
            'protocolo' => $application->id,
        ], 201);
    }
}
