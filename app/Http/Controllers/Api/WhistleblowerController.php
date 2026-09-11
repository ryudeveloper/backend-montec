<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Whistleblower\WhistleblowerReportService;
use App\Http\Requests\Api\StoreWhistleblowerReportRequest;
use App\Mail\WhistleblowerReportReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Canal de ouvidoria.
 *
 * Este controller não toca em `$request->ip()`, não escreve log e não emite
 * telemetria. Não é esquecimento: para uma denúncia marcada como anônima,
 * qualquer um dos três quebraria a promessa feita na interface.
 */
final class WhistleblowerController
{
    public function __construct(private WhistleblowerReportService $service) {}

    public function store(StoreWhistleblowerReportRequest $request): JsonResponse
    {
        $anonymous = $request->isAnonymous();

        $report = $this->service->record(
            $request->toAttributes(),
            $anonymous,
            $anonymous ? null : $request->reporterName(),
            $anonymous ? null : $request->reporterEmail(),
        );

        Mail::to(config('montec.recipients.whistleblower'))
            ->send(new WhistleblowerReportReceived($report));

        return response()->json([
            'message' => 'Denúncia registrada. Obrigado por contribuir com a integridade da Montec.',
            'protocolo' => $report->id,
        ], 201);
    }
}
