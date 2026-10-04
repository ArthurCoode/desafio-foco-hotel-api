<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Reservation\CreateReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class ReservationController extends Controller
{
    #[OA\Post(
        path: '/api/reservations',
        summary: 'Cria uma reserva',
        description: 'Cria uma nova reserva para um quarto de um hotel e retorna a reserva criada, com hotel, quarto, hóspedes e diárias. O valor financeiro da reserva (subtotal, discount, fees e total) é calculado no servidor a partir das diárias e do cupom informado; esses campos não são enviados pelo cliente. As diárias (dailies) devem corresponder exatamente às datas da estadia: uma diária por noite, sem datas duplicadas, com check-in inclusivo e check-out exclusivo. Para check_in 2026-10-10 e check_out 2026-10-13, as diárias esperadas são 2026-10-10, 2026-10-11 e 2026-10-12. Além da validação dos campos, a criação pode falhar por regras de negócio: o quarto não pertence ao hotel informado, não há disponibilidade no período, ou o cupom informado não existe, está inativo, ainda não começou, expirou ou possui configuração inválida. Exige token Bearer do Laravel Sanctum.',
        tags: ['Reservas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'room_id', 'check_in', 'check_out', 'guests', 'dailies'],
                properties: [
                    new OA\Property(
                        property: 'hotel_id',
                        description: 'ID do hotel. O hotel precisa existir.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'room_id',
                        description: 'ID do quarto. O quarto precisa existir e pertencer ao hotel informado.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'check_in',
                        description: 'Data de entrada (inclusiva), no formato YYYY-MM-DD.',
                        type: 'string',
                        format: 'date',
                        example: '2026-10-10'
                    ),
                    new OA\Property(
                        property: 'check_out',
                        description: 'Data de saída (exclusiva), no formato YYYY-MM-DD. Deve ser posterior ao check_in.',
                        type: 'string',
                        format: 'date',
                        example: '2026-10-13'
                    ),
                    new OA\Property(
                        property: 'guests',
                        description: 'Lista de hóspedes. Mínimo de 1.',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['name'],
                            properties: [
                                new OA\Property(
                                    property: 'name',
                                    description: 'Nome do hóspede.',
                                    type: 'string',
                                    maxLength: 255,
                                    example: 'Maria Silva'
                                ),
                                new OA\Property(
                                    property: 'phone',
                                    description: 'Opcional. Telefone do hóspede.',
                                    type: 'string',
                                    maxLength: 30,
                                    example: '+5577999990000'
                                ),
                            ],
                            type: 'object'
                        )
                    ),
                    new OA\Property(
                        property: 'dailies',
                        description: 'Diárias da estadia. Mínimo de 1. Deve haver exatamente uma diária por noite, sem datas duplicadas e sem datas fora do período, com check-in inclusivo e check-out exclusivo. Exemplo: com check_in 2026-10-10 e check_out 2026-10-13, as diárias esperadas são 2026-10-10, 2026-10-11 e 2026-10-12.',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['date', 'amount'],
                            properties: [
                                new OA\Property(
                                    property: 'date',
                                    description: 'Data da diária, no formato YYYY-MM-DD.',
                                    type: 'string',
                                    format: 'date',
                                    example: '2026-10-10'
                                ),
                                new OA\Property(
                                    property: 'amount',
                                    description: 'Valor da diária. Número decimal maior ou igual a zero.',
                                    type: 'number',
                                    format: 'float',
                                    minimum: 0,
                                    example: 250.00
                                ),
                            ],
                            type: 'object'
                        ),
                        example: [
                            ['date' => '2026-10-10', 'amount' => 250.00],
                            ['date' => '2026-10-11', 'amount' => 250.00],
                            ['date' => '2026-10-12', 'amount' => 250.00],
                        ]
                    ),
                    new OA\Property(
                        property: 'coupon_code',
                        description: 'Opcional. Código de cupom de desconto. O desconto é calculado no servidor.',
                        type: 'string',
                        maxLength: 50,
                        nullable: true,
                        example: 'DESCONTO10'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Reserva criada com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'external_id', type: 'integer', nullable: true, example: null),
                                new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                new OA\Property(property: 'room_id', type: 'integer', example: 1),
                                new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-10-10'),
                                new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-10-13'),
                                new OA\Property(property: 'status', type: 'string', description: 'Status inicial da reserva criada: confirmed.', example: 'confirmed'),
                                new OA\Property(property: 'subtotal', type: 'number', format: 'float', description: 'Calculado no servidor: soma das diárias.', example: 750.00),
                                new OA\Property(property: 'discount', type: 'number', format: 'float', description: 'Calculado no servidor a partir do cupom informado.', example: 75.00),
                                new OA\Property(property: 'fees', type: 'number', format: 'float', description: 'Calculado no servidor. Taxas ainda não são implementadas: o valor atual é sempre 0.', example: 0),
                                new OA\Property(property: 'total', type: 'number', format: 'float', description: 'Calculado no servidor.', example: 675.00),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(
                                    property: 'hotel',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'room',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Quarto Standard'),
                                        new OA\Property(property: 'quantity', type: 'integer', example: 1),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'guests',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
                                            new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+5577999990000'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(
                                    property: 'dailies',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-10-10'),
                                            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 250.00),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Usuário não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Recurso não encontrado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Recurso não encontrado.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Quarto indisponível para o período informado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Não há disponibilidade para o quarto 1 no período informado.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Dados inválidos ou regra de negócio violada. Retorna HTTP 422 nos casos de falha de validação do Laravel (campo obrigatório ausente, hotel ou quarto inexistente, check_out não posterior ao check_in, guests ou dailies vazios, ou diárias duplicadas, fora do período ou em quantidade diferente do número de noites) e de regra de negócio na criação da reserva (quarto que não pertence ao hotel informado, ou cupom inexistente, inativo, ainda não iniciado, expirado ou inválido). Falhas de validação trazem os campos message e errors; erros de regra de negócio trazem apenas message. A indisponibilidade do quarto não retorna 422: veja a resposta 409.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'A hospedagem tem 3 noite(s) e exige exatamente uma diária por noite, mas foram enviadas 2.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            ),
                            example: ['dailies' => ['A hospedagem tem 3 noite(s) e exige exatamente uma diária por noite, mas foram enviadas 2.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(
        StoreReservationRequest $request,
        CreateReservationService $createReservation,
    ): JsonResponse {
        $reservation = $createReservation->create($request->validated());

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
