<?php

namespace App\Services\Import;

use App\Models\Hotel;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class HotelImportService
{
    public function __construct(private readonly ImportErrorRecorder $errors) {}

    /**
     * @return array{created: int, updated: int, failed: int}
     */
    public function import(SimpleXMLElement $xml, string $source): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($xml->Hotel as $node) {
            $externalId = (int) $node['id'];

            try {
                $name = trim((string) $node->Name);

                if ($externalId <= 0 || $name === '') {
                    throw new RuntimeException('Hotel sem id ou sem Name válidos.');
                }

                // updateOrCreate é uma única operação: já é atômica, sem transação extra.
                $hotel = Hotel::updateOrCreate(
                    ['external_id' => $externalId],
                    ['name' => $name],
                );

                $summary[$hotel->wasRecentlyCreated ? 'created' : 'updated']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                $this->errors->record($source, 'hotel', 'import_failed', $e->getMessage(), $externalId ?: null, $node);
            }
        }

        return $summary;
    }
}
