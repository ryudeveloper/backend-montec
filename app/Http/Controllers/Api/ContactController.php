<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreContactRequest;
use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

final class ContactController
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contactRequest = ContactRequest::create($request->toAttributes());

        Mail::to(config('montec.recipients.sales'))
            ->send(new ContactRequestReceived($contactRequest));

        // O cliente valida a resposta com Zod e lê `message` (apiAckSchema).
        return response()->json([
            'message' => 'Mensagem recebida. Nossa equipe entrará em contato em breve.',
            'protocolo' => $contactRequest->id,
        ], 201);
    }
}
