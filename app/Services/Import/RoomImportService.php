<?php

namespace App\Services\Import;

use App\Models\Hotel;
use App\Models\Room;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class RoomImportService
{
    public function __construct(private readonly ImportErrorRecorder $errors) {}

    /**
     * @return array{created: int, updated: int, failed: int}
     */
    public function import(SimpleXMLElement $xml, string $source): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0];

        // external_id do hotel => id interno, carregado uma única vez.
        $hotelIds = Hotel::pluck('id', 'external_id');

        foreach ($xml->Room as $node) {
            $externalId = (int) $node['id'];
            $hotelCode = (int) $node['hotelCode'];

            try {
                $name = trim((string) $node->Name);

                if ($externalId <= 0 || $hotelCode <= 0 || $name === '') {
                    throw new RuntimeException('Quarto sem id, hotelCode ou Name válidos.');
                }

                $hotelId = $hotelIds->get($hotelCode)
                    ?? throw new RuntimeException("Hotel {$hotelCode} não encontrado para o quarto {$externalId}.");

                // O XML não informa quantity: o campo não é tocado aqui.
                $room = Room::updateOrCreate(
                    ['hotel_id' => $hotelId, 'external_id' => $externalId],
                    ['name' => $name],
                );

                $summary[$room->wasRecentlyCreated ? 'created' : 'updated']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $this->errors->record($source, 'room', 'import_failed', $e->getMessage(), $externalId ?: null, $node);
            }
        }

        return $summary;
    }
}
