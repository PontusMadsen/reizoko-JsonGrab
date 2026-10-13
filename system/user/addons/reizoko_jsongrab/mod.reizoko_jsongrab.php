<?php

use Reizoko\JsonGrab\Model\Importer;

/**
 * JsonGrab has no front-end template tags. This class exists for the
 * "run" action, which lets a cron job trigger an import by URL:
 *
 *   https://example.com/?ACT=<action id>&id=<import id>&key=<secret>
 *
 * The secret is the "Cron Secret" saved on the import itself. An import
 * with no secret can't be triggered this way.
 */
class Reizoko_jsongrab
{
    public function run()
    {
        $importId = (int) ee()->input->get('id');
        $given = (string) ee()->input->get('key');

        $import = ee()->db->where('import_id', $importId)->get('reizoko_jsongrab_imports')->row();

        // Same answer for unknown id, no secret set, and wrong secret, so
        // the response doesn't reveal which imports exist.
        $secret = $import ? trim((string) $import->cron_secret) : '';

        if ($secret === '' || !hash_equals($secret, $given)) {
            $this->respond(403, 'Forbidden');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $summary = (new Importer())->run($import);
        } catch (\Exception $e) {
            $this->respond(500, 'Import failed: ' . $e->getMessage());
        }

        $text = sprintf(
            'Created %d, updated %d, %d error(s).',
            $summary['created'],
            $summary['updated'],
            count($summary['errors'])
        );

        ee()->db->where('import_id', $importId)->update('reizoko_jsongrab_imports', [
            'last_run_date' => time(),
            'last_run_summary' => $text,
        ]);

        foreach ($summary['errors'] as $error) {
            $text .= "\n  - " . $error;
        }

        $this->respond(200, $text);
    }

    private function respond($status, $text)
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text . "\n";
        exit;
    }
}
