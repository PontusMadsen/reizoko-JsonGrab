<?php

namespace Reizoko\JsonGrab\Commands;

use ExpressionEngine\Cli\Cli;
use Reizoko\JsonGrab\Model\Importer;

class CommandImportRun extends Cli
{
    public $name = 'Run JsonGrab Import';
    public $signature = 'jsongrab:run';
    public $usage = 'php eecli.php jsongrab:run --id=<import_id>';
    public $commandOptions = ['--id'];

    public function handle()
    {
        $importId = (int) $this->getOptionOrAsk('--id', 'Which import id do you want to run?', '', true);

        $row = ee()->db->where('import_id', $importId)->get('jsongrab_imports')->row();

        if (!$row) {
            $this->fail("No import found with id {$importId}");
            return;
        }

        $importer = new Importer();

        try {
            $summary = $importer->run($row);
        } catch (\Exception $e) {
            $this->fail('Import failed: ' . $e->getMessage());
            return;
        }

        ee()->db->where('import_id', $importId)->update('jsongrab_imports', [
            'last_run_date' => time(),
            'last_run_summary' => sprintf(
                'Created %d, updated %d, %d error(s)',
                $summary['created'],
                $summary['updated'],
                count($summary['errors'])
            ),
        ]);

        $this->output->outln(sprintf(
            'Created %d, updated %d, %d error(s)',
            $summary['created'],
            $summary['updated'],
            count($summary['errors'])
        ));

        foreach ($summary['errors'] as $error) {
            $this->output->outln('  - ' . $error);
        }
    }
}
