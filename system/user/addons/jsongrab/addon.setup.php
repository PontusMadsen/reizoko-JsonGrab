<?php

return array(
    'author'         => 'Pontus Madsen',
    'author_url'     => '',
    'name'           => 'JsonGrab',
    'description'    => 'Import channel entries from a JSON API endpoint.',
    'version'        => '1.0.0',
    'namespace'      => 'Reizoko\JsonGrab',
    'settings_exist' => true,

    'commands' => [
        'jsongrab:run' => Reizoko\JsonGrab\Commands\CommandImportRun::class,
    ],
);
