<?php

declare(strict_types=1);

namespace App;

use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: '3.0.0')]
#[OA\Info(
    version: '1.0.0',
    title: 'Foco Hotel API',
    description: 'API REST para gerenciamento hoteleiro, incluindo quartos, reservas e autenticação.',
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum Token',
)]
final class OpenApi
{
}
