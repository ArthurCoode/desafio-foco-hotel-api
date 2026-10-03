<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class RoomController extends Controller
{
    #[OA\Get(
        path: '/api/rooms',
        summary: 'Lista os quartos',
        description: 'Retorna a lista paginada de quartos, ordenada por id, com os dados do hotel de cada quarto. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Página desejada.',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Quantidade de registros por página. Atenção: a implementação atual chama paginate() sem ler este parâmetro, então ele ainda não altera o resultado (usa o padrão do Laravel, 15).',
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de quartos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'external_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Quarto Standard'),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                    new OA\Property(
                                        property: 'hotel',
                                        description: 'Presente quando o relacionamento está carregado (sempre no index).',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                            new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                        ]
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'links',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/rooms?page=1'),
                                new OA\Property(property: 'last', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/rooms?page=3'),
                                new OA\Property(property: 'prev', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'next', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/rooms?page=2'),
                            ]
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                                new OA\Property(
                                    property: 'links',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'url', type: 'string', nullable: true),
                                            new OA\Property(property: 'label', type: 'string', example: '1'),
                                            new OA\Property(property: 'active', type: 'boolean', example: true),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'path', type: 'string', example: 'http://127.0.0.1:8000/api/rooms'),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 40),
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
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $rooms = Room::with('hotel')
            ->orderBy('id')
            ->paginate();

        return RoomResource::collection($rooms);
    }

    #[OA\Post(
        path: '/api/rooms',
        summary: 'Cria um quarto',
        description: 'Cria um novo quarto vinculado a um hotel e retorna o quarto criado, com os dados do hotel. O campo quantity é opcional: quando não informado, o sistema utiliza 1. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'external_id', 'name'],
                properties: [
                    new OA\Property(
                        property: 'hotel_id',
                        description: 'ID do hotel ao qual o quarto pertence. O hotel precisa existir.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'external_id',
                        description: 'Identificador do quarto no sistema externo. Deve ser único dentro do mesmo hotel.',
                        type: 'integer',
                        minimum: 1,
                        example: 999
                    ),
                    new OA\Property(
                        property: 'name',
                        description: 'Nome do quarto.',
                        type: 'string',
                        maxLength: 255,
                        example: 'Quarto Executivo'
                    ),
                    new OA\Property(
                        property: 'quantity',
                        description: 'Opcional. Quantidade de unidades do quarto. Quando não informado, o sistema utiliza 1.',
                        type: 'integer',
                        minimum: 1,
                        maximum: 4294967295,
                        default: 1,
                        example: 2
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Quarto criado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                new OA\Property(property: 'external_id', type: 'integer', example: 999),
                                new OA\Property(property: 'name', type: 'string', example: 'Quarto Executivo'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(
                                    property: 'hotel',
                                    description: 'Presente quando o relacionamento está carregado (sempre no store).',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                    ]
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
                response: 422,
                description: 'Dados de entrada inválidos: os dados enviados não passaram pela validação do Laravel (por exemplo, campo obrigatório ausente, hotel inexistente ou external_id já usado neste hotel).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The selected hotel id is invalid.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            ),
                            example: ['hotel_id' => ['The selected hotel id is invalid.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        // refresh() traz do banco valores que o INSERT preencheu sozinho (como o default de quantity).
        $room->refresh()->load('hotel');

        return RoomResource::make($room)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Get(
        path: '/api/rooms/{room}',
        summary: 'Exibe um quarto',
        description: 'Retorna os dados de um quarto, com as informações do hotel ao qual ele pertence. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'room',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                new OA\Property(property: 'external_id', type: 'integer', example: 999),
                                new OA\Property(property: 'name', type: 'string', example: 'Quarto Executivo'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(
                                    property: 'hotel',
                                    description: 'Presente quando o relacionamento está carregado (sempre no show).',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                    ]
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
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\Models\Room] 3'),
                    ]
                )
            ),
        ]
    )]
    public function show(Room $room): RoomResource
    {
        return RoomResource::make($room->load('hotel'));
    }

    #[OA\Put(
        path: '/api/rooms/{room}',
        summary: 'Atualiza um quarto (PUT)',
        description: 'Atualiza os dados de um quarto e retorna o quarto atualizado, com os dados do hotel. Os campos hotel_id, external_id e name são obrigatórios; quantity é opcional. A combinação hotel_id + external_id deve ser única, ignorando o próprio quarto. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'room',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'external_id', 'name'],
                properties: [
                    new OA\Property(
                        property: 'hotel_id',
                        description: 'ID do hotel ao qual o quarto pertence. O hotel precisa existir.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'external_id',
                        description: 'Identificador do quarto no sistema externo. A combinação hotel_id + external_id deve ser única, ignorando o próprio quarto.',
                        type: 'integer',
                        minimum: 1,
                        example: 999
                    ),
                    new OA\Property(
                        property: 'name',
                        description: 'Nome do quarto.',
                        type: 'string',
                        maxLength: 255,
                        example: 'Quarto Executivo'
                    ),
                    new OA\Property(
                        property: 'quantity',
                        description: 'Opcional. Quantidade de unidades do quarto.',
                        type: 'integer',
                        minimum: 1,
                        maximum: 4294967295,
                        example: 2
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto atualizado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                new OA\Property(property: 'external_id', type: 'integer', example: 999),
                                new OA\Property(property: 'name', type: 'string', example: 'Quarto Executivo'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:30:00.000000Z'),
                                new OA\Property(
                                    property: 'hotel',
                                    description: 'Presente quando o relacionamento está carregado (sempre no update).',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                    ]
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
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\Models\Room] 3'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Dados de entrada inválidos: os dados enviados não passaram pela validação do Laravel (por exemplo, campo obrigatório ausente, hotel inexistente ou external_id já usado neste hotel por outro quarto).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The selected hotel id is invalid.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            ),
                            example: ['hotel_id' => ['The selected hotel id is invalid.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    #[OA\Patch(
        path: '/api/rooms/{room}',
        summary: 'Atualiza um quarto (PATCH)',
        description: 'Atualiza os dados de um quarto e retorna o quarto atualizado, com os dados do hotel. Atenção: nesta implementação o PATCH usa as mesmas regras do PUT, então hotel_id, external_id e name continuam obrigatórios; quantity é opcional. A combinação hotel_id + external_id deve ser única, ignorando o próprio quarto. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'room',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'external_id', 'name'],
                properties: [
                    new OA\Property(
                        property: 'hotel_id',
                        description: 'ID do hotel ao qual o quarto pertence. O hotel precisa existir.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'external_id',
                        description: 'Identificador do quarto no sistema externo. A combinação hotel_id + external_id deve ser única, ignorando o próprio quarto.',
                        type: 'integer',
                        minimum: 1,
                        example: 999
                    ),
                    new OA\Property(
                        property: 'name',
                        description: 'Nome do quarto.',
                        type: 'string',
                        maxLength: 255,
                        example: 'Quarto Executivo'
                    ),
                    new OA\Property(
                        property: 'quantity',
                        description: 'Opcional. Quantidade de unidades do quarto.',
                        type: 'integer',
                        minimum: 1,
                        maximum: 4294967295,
                        example: 2
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto atualizado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                                new OA\Property(property: 'external_id', type: 'integer', example: 999),
                                new OA\Property(property: 'name', type: 'string', example: 'Quarto Executivo'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:00:00.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true, example: '2026-10-03T12:30:00.000000Z'),
                                new OA\Property(
                                    property: 'hotel',
                                    description: 'Presente quando o relacionamento está carregado (sempre no update).',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'external_id', type: 'integer', example: 1001),
                                        new OA\Property(property: 'name', type: 'string', example: 'Hotel Exemplo'),
                                    ]
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
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\Models\Room] 3'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Dados de entrada inválidos: os dados enviados não passaram pela validação do Laravel (por exemplo, campo obrigatório ausente, hotel inexistente ou external_id já usado neste hotel por outro quarto).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The selected hotel id is invalid.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(type: 'string')
                            ),
                            example: ['hotel_id' => ['The selected hotel id is invalid.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return RoomResource::make($room->load('hotel'));
    }

    #[OA\Delete(
        path: '/api/rooms/{room}',
        summary: 'Exclui um quarto',
        description: 'Exclui o quarto informado. Em caso de sucesso, retorna 204 No Content, sem corpo na resposta. Exige token Bearer do Laravel Sanctum.',
        tags: ['Quartos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'room',
                in: 'path',
                required: true,
                description: 'ID interno do quarto.',
                schema: new OA\Schema(type: 'integer', example: 3)
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Quarto excluído com sucesso (No Content). A resposta não possui corpo.'
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
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\Models\Room] 3'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Room $room): Response
    {
        $room->delete();

        return response()->noContent();
    }
}
