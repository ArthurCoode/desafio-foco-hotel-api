<?php

namespace App\Services\Import;

use LibXMLError;
use RuntimeException;
use SimpleXMLElement;

class XmlImportService
{
    /** Entidade => elemento raiz esperado no XML. */
    private const ROOT_ELEMENTS = [
        'hotels' => 'Hotels',
        'rooms' => 'Rooms',
        'reservations' => 'Reserves',
    ];

    public function __construct(
        private readonly HotelImportService $hotels,
        private readonly RoomImportService $rooms,
        private readonly ReservationImportService $reservations,
        private readonly ImportErrorRecorder $errors,
    ) {}

    /**
     * @param  array{hotels: string, rooms: string, reservations: string}  $files  Caminhos dos XMLs
     * @return array<string, array{created: int, updated: int, failed: int}>
     *
     * @throws RuntimeException Quando algum arquivo está ausente, inválido ou com raiz inesperada
     */
    public function import(array $files): array
    {
        // Falha rápida: nada é gravado se algum arquivo estiver inválido.
        $documents = [];
        foreach (self::ROOT_ELEMENTS as $entity => $root) {
            $documents[$entity] = $this->loadXml($entity, $files[$entity], $root);
        }

        // A ordem respeita as dependências: quartos precisam dos hotéis,
        // e reservas precisam de hotéis e quartos.
        return [
            'hotels' => $this->hotels->import($documents['hotels'], $files['hotels']),
            'rooms' => $this->rooms->import($documents['rooms'], $files['rooms']),
            'reservations' => $this->reservations->import($documents['reservations'], $files['reservations']),
        ];
    }

    private function loadXml(string $entity, string $path, string $expectedRoot): SimpleXMLElement
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->fail($entity, $path, 'file_not_found', "Arquivo XML não encontrado ou ilegível: {$path}");
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);

        $errors = array_map(
            fn (LibXMLError $error) => trim($error->message)." (linha {$error->line})",
            libxml_get_errors(),
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            $this->fail($entity, $path, 'invalid_xml', "XML inválido: {$path}", ['errors' => $errors]);
        }

        if ($xml->getName() !== $expectedRoot) {
            $this->fail(
                $entity,
                $path,
                'unexpected_root',
                "Elemento raiz <{$xml->getName()}> inesperado em {$path}; esperado <{$expectedRoot}>.",
                ['expected' => $expectedRoot, 'found' => $xml->getName()],
            );
        }

        return $xml;
    }

    /**
     * Registra o erro de forma estruturada e interrompe a importação.
     */
    private function fail(string $entity, string $path, string $type, string $message, array $extra = []): never
    {
        $this->errors->record($path, $entity, $type, $message, null, ['path' => $path] + $extra);

        throw new RuntimeException($message);
    }
}
