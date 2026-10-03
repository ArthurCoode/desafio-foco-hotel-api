<?php

namespace App\Services\Import;

use App\Models\ImportError;
use SimpleXMLElement;

class ImportErrorRecorder
{
    /**
     * Registra (ou atualiza) um erro de importação. Há no máximo um registro
     * por source + entity + external_id + type, o que mantém a importação idempotente.
     */

    public function record(
        string $source,
        string $entity,
        string $type,
        string $message,
        ?int $externalId = null,
        SimpleXMLElement|array|null $payload = null,
    ): ImportError {
        if ($payload instanceof SimpleXMLElement) {
            $payload = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        return ImportError::updateOrCreate(
            [
                'source' => $source,
                'entity' => $entity,
                'external_id' => $externalId,
                'type' => $type,
            ],
            [
                'message' => $message,
                'payload' => $payload,
            ],
        );
    }
}
