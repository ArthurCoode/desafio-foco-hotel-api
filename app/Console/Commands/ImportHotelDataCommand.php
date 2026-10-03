<?php

namespace App\Console\Commands;

use App\Services\Import\XmlImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportHotelDataCommand extends Command
{
    protected $signature = 'hotel:import
        {--path= : Diretório com os XMLs (padrão: storage/app/imports)}';

    protected $description = 'Importa hotéis, quartos e reservas a partir dos arquivos XML';

    public function handle(XmlImportService $import): int
    {
        $path = rtrim($this->option('path') ?: storage_path('app/imports'), '/');

        try {
            $summary = $import->import([
                'hotels' => "{$path}/hotels.xml",
                'rooms' => "{$path}/rooms.xml",
                'reservations' => "{$path}/reserves.xml",
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Entidade', 'Criados', 'Atualizados', 'Falhas'],
            collect($summary)->map(fn (array $r, string $entity) => [
                $entity, $r['created'], $r['updated'], $r['failed'],
            ])->values()->all(),
        );

        $this->info('Importação concluída. Inconsistências ficam registradas em import_errors.');

        return self::SUCCESS;
    }
}
