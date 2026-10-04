<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Reservation\PaymentExceedsReservationTotalException;
use App\Services\Reservation\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    required: ['id', 'reservation_id', 'method', 'amount', 'paid_at', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reservation_id', type: 'integer', example: 1),
        new OA\Property(property: 'method', type: 'string', example: 'credit_card'),
        new OA\Property(property: 'amount', type: 'string', example: '100.00'),
        new OA\Property(
            property: 'paid_at',
            type: 'string',
            format: 'date-time',
            example: '2022-12-01T10:00:00.000000Z',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2022-12-01T10:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaymentNotFound',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Pagamento não encontrado.'),
    ]
)]
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    #[OA\Get(
        path: '/api/reservations/{reservation}/payments',
        operationId: 'listReservationPayments',
        summary: 'Lista os pagamentos de uma reserva',
        description: 'Retorna os pagamentos do mais recente para o mais antigo, com o total pago e o saldo restante.',
        security: [['sanctum' => []]],
        tags: ['Pagamentos'],
        parameters: [
            new OA\Parameter(
                name: 'reservation',
                description: 'ID da reserva',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de pagamentos da reserva',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'payments',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Payment')
                        ),
                        new OA\Property(property: 'total_paid', type: 'string', example: '100.00'),
                        new OA\Property(property: 'remaining_balance', type: 'string', example: '200.00'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Reserva não encontrada'),
        ]
    )]
    public function index(Reservation $reservation): JsonResponse
    {
        $payments = $reservation->payments()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $payment): array => $this->payload($payment))
            ->all();

        return response()->json([
            'payments'          => $payments,
            'total_paid'        => $this->paymentService->getTotalPaid($reservation),
            'remaining_balance' => $this->paymentService->getRemainingBalance($reservation),
        ]);
    }

    #[OA\Post(
        path: '/api/reservations/{reservation}/payments',
        operationId: 'storeReservationPayment',
        summary: 'Registra um pagamento para uma reserva',
        description: 'O valor não pode fazer o total pago ultrapassar o total da reserva.',
        security: [['sanctum' => []]],
        tags: ['Pagamentos'],
        parameters: [
            new OA\Parameter(
                name: 'reservation',
                description: 'ID da reserva',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['method', 'amount'],
                properties: [
                    new OA\Property(
                        property: 'method',
                        description: 'Forma de pagamento',
                        type: 'string',
                        maxLength: 50,
                        example: 'credit_card'
                    ),
                    new OA\Property(
                        property: 'amount',
                        description: 'Valor pago, maior que zero, com no máximo 2 casas decimais',
                        type: 'number',
                        format: 'float',
                        example: 100.00
                    ),
                    new OA\Property(
                        property: 'paid_at',
                        description: 'Data do pagamento (opcional)',
                        type: 'string',
                        example: '2022-12-01 10:00:00',
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pagamento registrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Pagamento registrado com sucesso.'
                        ),
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                        new OA\Property(property: 'total_paid', type: 'string', example: '100.00'),
                        new OA\Property(property: 'remaining_balance', type: 'string', example: '200.00'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Reserva não encontrada'),
            new OA\Response(
                response: 422,
                description: 'Dados inválidos ou pagamento superior ao saldo restante',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'O pagamento de 250.00 excede o saldo restante da reserva (200.00).'
                        ),
                        new OA\Property(
                            property: 'errors',
                            description: 'Presente apenas em erros de validação dos campos',
                            type: 'object',
                            example: ['amount' => ['The amount field must be greater than 0.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(StorePaymentRequest $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validated();

        try {
            $payment = $this->paymentService->register(
                $reservation,
                $data['method'],
                $data['amount'],
                $data['paid_at'] ?? null
            );
        } catch (PaymentExceedsReservationTotalException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message'           => 'Pagamento registrado com sucesso.',
            'payment'           => $this->payload($payment),
            'total_paid'        => $this->paymentService->getTotalPaid($reservation),
            'remaining_balance' => $this->paymentService->getRemainingBalance($reservation),
        ], Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/reservations/{reservation}/payments/{payment}',
        operationId: 'showReservationPayment',
        summary: 'Exibe um pagamento de uma reserva',
        security: [['sanctum' => []]],
        tags: ['Pagamentos'],
        parameters: [
            new OA\Parameter(
                name: 'reservation',
                description: 'ID da reserva',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'payment',
                description: 'ID do pagamento',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pagamento encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Pagamento não pertence à reserva informada (ou reserva/pagamento inexistente)',
                content: new OA\JsonContent(ref: '#/components/schemas/PaymentNotFound')
            ),
        ]
    )]
    public function show(Reservation $reservation, Payment $payment): JsonResponse
    {
        if (! $this->belongsToReservation($payment, $reservation)) {
            return $this->notFound();
        }

        return response()->json([
            'payment' => $this->payload($payment),
        ]);
    }

    #[OA\Delete(
        path: '/api/reservations/{reservation}/payments/{payment}',
        operationId: 'deleteReservationPayment',
        summary: 'Exclui um pagamento de uma reserva',
        security: [['sanctum' => []]],
        tags: ['Pagamentos'],
        parameters: [
            new OA\Parameter(
                name: 'reservation',
                description: 'ID da reserva',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'payment',
                description: 'ID do pagamento',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Pagamento excluído (sem conteúdo)'),
            new OA\Response(
                response: 401,
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Pagamento não pertence à reserva informada (ou reserva/pagamento inexistente)',
                content: new OA\JsonContent(ref: '#/components/schemas/PaymentNotFound')
            ),
        ]
    )]
    public function destroy(Reservation $reservation, Payment $payment): JsonResponse|Response
    {
        if (! $this->belongsToReservation($payment, $reservation)) {
            return $this->notFound();
        }

        $payment->delete();

        return response()->noContent();
    }

    private function belongsToReservation(Payment $payment, Reservation $reservation): bool
    {
        return (int) $payment->reservation_id === (int) $reservation->id;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Pagamento não encontrado.',
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Payment $payment): array
    {
        return [
            'id'             => $payment->id,
            'reservation_id' => $payment->reservation_id,
            'method'         => $payment->method,
            'amount'         => $payment->amount,
            'paid_at'        => $payment->paid_at,
            'created_at'     => $payment->created_at,
        ];
    }
}
