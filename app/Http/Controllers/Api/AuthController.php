<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * POST /api/login
     */
    #[OA\Post(
        path: '/api/login',
        summary: 'Realiza autenticação na API',
        description: 'Autentica um usuário e retorna um token Bearer do Laravel Sanctum.',
        tags: ['Autenticação']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(
                    property: 'email',
                    type: 'string',
                    format: 'email',
                    example: 'usuario@exemplo.com'
                ),
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'senha-segura-123'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login realizado com sucesso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Login realizado com sucesso.'
                ),
                new OA\Property(
                    property: 'token',
                    type: 'string',
                    example: '1|abcdefghijklmnopqrstuvwxyz0123456789'
                ),
                new OA\Property(
                    property: 'token_type',
                    type: 'string',
                    example: 'Bearer'
                ),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
                        new OA\Property(property: 'email', type: 'string', example: 'usuario@exemplo.com'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Credenciais inválidas',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Credenciais inválidas.'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Dados de entrada inválidos: os dados enviados não passaram pela validação do Laravel.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'The email field is required.'
                ),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                    example: ['email' => ['The email field is required.']]
                ),
            ]
        )
    )]
    public function login(Request $request): JsonResponse
    {
        // Em rotas de API, o Laravel retorna 422 com JSON automaticamente
        // quando a validação falha, desde que o cliente envie
        // "Accept: application/json".
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        // Mensagem genérica e única para os dois cenários (usuário inexistente
        // ou senha incorreta), evitando enumeração de e-mails cadastrados.
        // O "!$user ||" garante curto-circuito: Hash::check() só roda se
        // o usuário existir, então $user->password nunca é acessado em null.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        // O nome do token identifica a origem/dispositivo. Aqui usamos um
        // valor fixo, já que o escopo é apenas autenticação.
        // plainTextToken é a única vez em que o token completo está disponível;
        // no banco fica apenas o hash.
        $token = $user->createToken('api-token')->plainTextToken;

        // Montamos o array do usuário explicitamente (em vez de retornar
        // $user inteiro) para garantir que nenhuma credencial ou campo
        // sensível vaze, mesmo que o model mude no futuro.
        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }
}
