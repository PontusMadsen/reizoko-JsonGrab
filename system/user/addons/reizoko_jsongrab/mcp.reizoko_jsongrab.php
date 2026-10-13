<?php

use Reizoko\JsonGrab\Model\Importer;

class Reizoko_jsongrab_mcp
{
    // Field types that hold structured/multi-row data (a grid of rows, a
    // matrix, related entries, files, etc.) rather than a single scalar
    // value. A single JSON path can't map into these, so they're left out
    // of the mapping UI entirely instead of offering a mapping that would
    // silently do the wrong thing.
    private $unmappableTypes = [
        'grid', 'matrix', 'fluid_field', 'playa', 'relationship',
        'assets', 'file', 'file_grid', 'low_events', 'simple_grid',
        'simple_table', 'tagger', 'tag', 'store',
    ];

    private $base_url;

    public function __construct()
    {
        $this->base_url = ee('CP/URL')->make('addons/settings/reizoko_jsongrab');
    }

    public function preview()
    {
        $import = new \stdClass();
        $import->url = ee()->input->post('url');
        $import->http_header = ee()->input->post('http_header');
        $import->root_path = ee()->input->post('root_path');
        $import->use_basic_auth = ee()->input->post('use_basic_auth') ? 'y' : 'n';
        $import->basic_auth_user = ee()->input->post('basic_auth_user');
        $import->basic_auth_pass = ee()->input->post('basic_auth_pass');

        // If editing and the password field was left blank, fall back to
        // whatever was already saved so preview works without retyping it.
        $import_id = ee()->input->post('import_id');
        if ($import_id && $import->basic_auth_pass === '') {
            $existing = ee()->db->where('import_id', $import_id)->get('reizoko_jsongrab_imports')->row();
            if ($existing) {
                $import->basic_auth_user = $import->basic_auth_user !== '' ? $import->basic_auth_user : $existing->basic_auth_user;
                $import->basic_auth_pass = $existing->basic_auth_pass;
            }
        }

        if (empty($import->url)) {
            ee()->output->send_ajax_response(['error' => 'Enter a JSON URL first.']);
        }

        try {
            $result = (new Importer())->preview($import);
            ee()->output->send_ajax_response($result);
        } catch (\Exception $e) {
            ee()->output->send_ajax_response(['error' => $e->getMessage()]);
        }
    }

    public function fields()
    {
        $channel_id = (int) ee()->input->get('channel_id');
        ee()->output->send_ajax_response(['tabs' => $this->channelFieldTabs($channel_id)]);
    }

    /**
     * Build a tabs-of-fields structure for a channel that matches its real
     * Publish Layout — same tab names, same order, same grouping a person
     * sees when creating an entry by hand. Falls back to grouping by Field
     * Group if the channel has no configured layout.
     */
    private function channelFieldTabs($channel_id)
    {
        if (!$channel_id) {
            return [];
        }

        $channel = ee('Model')->get('Channel', $channel_id)->first();

        if (!$channel) {
            return [];
        }

        $fieldsById = [];
        foreach ($channel->getAllCustomFields() as $field) {
            $fieldsById[$field->field_id] = $field;
        }

        $layoutRow = ee()->db->where('channel_id', $channel_id)->limit(1)->get('layout_publish')->row();

        if ($layoutRow && $layoutRow->field_layout) {
            $tabs = $this->tabsFromLayout($layoutRow->field_layout, $fieldsById);
            if (!empty($tabs)) {
                return $tabs;
            }
        }

        // Fallback: no layout configured, group by Field Group instead.
        $tabs = [];

        $direct = $channel->CustomFields;
        if ($direct && count($direct) > 0) {
            $fields = $this->fieldSummaries($direct);
            if (!empty($fields)) {
                $tabs[] = ['label' => 'Fields', 'fields' => $fields];
            }
        }

        foreach ($channel->FieldGroups as $group) {
            $groupFields = $group->ChannelFields;
            if ($groupFields && count($groupFields) > 0) {
                $fields = $this->fieldSummaries($groupFields);
                if (!empty($fields)) {
                    $tabs[] = ['label' => $group->group_name, 'fields' => $fields];
                }
            }
        }

        return $tabs;
    }

    /**
     * Parse a channel's exp_layout_publish.field_layout (a serialized array
     * of tabs, each with a list of field refs like "field_id_10") into our
     * tabs structure, keeping only tabs that actually contain custom fields.
     */
    private function tabsFromLayout($serialized, $fieldsById)
    {
        $layout = @unserialize($serialized, ['allowed_classes' => false]);

        if (!is_array($layout)) {
            return [];
        }

        $tabs = [];

        foreach ($layout as $tabConfig) {
            if (empty($tabConfig['fields'])) {
                continue;
            }

            $fields = [];
            foreach ($tabConfig['fields'] as $fieldRef) {
                $key = $fieldRef['field'] ?? '';
                if (strpos($key, 'field_id_') !== 0) {
                    continue; // native fields (title, date, categories, etc.) — not part of field mapping
                }
                $fieldId = (int) substr($key, strlen('field_id_'));
                if (isset($fieldsById[$fieldId]) && !in_array($fieldsById[$fieldId]->field_type, $this->unmappableTypes, true)) {
                    $field = $fieldsById[$fieldId];
                    $fields[] = [
                        'name' => $field->field_name,
                        'label' => $field->field_label,
                        'type' => $field->field_type,
                    ];
                }
            }

            if (!empty($fields)) {
                $tabs[] = [
                    'label' => $tabConfig['name'] ?? 'Fields',
                    'fields' => $fields,
                ];
            }
        }

        return $tabs;
    }

    private function fieldSummaries($fields)
    {
        $out = [];
        foreach ($fields as $field) {
            if (in_array($field->field_type, $this->unmappableTypes, true)) {
                continue;
            }
            $out[] = [
                'name' => $field->field_name,
                'label' => $field->field_label,
                'type' => $field->field_type,
            ];
        }
        return $out;
    }

    public function index()
    {
        $imports = ee()->db->order_by('name', 'asc')->get('reizoko_jsongrab_imports')->result();
        $channels = $this->channelNames();

        $vars = [
            'imports' => $imports,
            'channels' => $channels,
            'base_url' => $this->base_url,
            'cp_page_title' => 'Reizoko JsonGrab Imports',
        ];

        return [
            'heading' => 'Reizoko JsonGrab Imports',
            'body' => ee('View')->make('reizoko_jsongrab:index')->render($vars),
            'breadcrumb' => [
                $this->base_url->compile() => 'Reizoko JsonGrab',
            ],
        ];
    }

    public function create()
    {
        if (ee()->input->post('save')) {
            return $this->save();
        }

        return $this->form();
    }

    public function edit($import_id = null)
    {
        if (ee()->input->post('save')) {
            return $this->save($import_id);
        }

        return $this->form($import_id);
    }

    private function form($import_id = null)
    {
        $import = null;

        if ($import_id) {
            $import = ee()->db->where('import_id', $import_id)->get('reizoko_jsongrab_imports')->row();

            if (!$import) {
                show_404();
            }
        }

        $fieldMap = [];
        if ($import && $import->field_map) {
            $decoded = json_decode($import->field_map, true);
            if (is_array($decoded)) {
                $fieldMap = $decoded;
            }
        }

        $fieldTabs = $import ? $this->channelFieldTabs($import->channel_id) : [];

        $vars = [
            'import' => $import,
            'field_map' => $fieldMap,
            'field_tabs' => $fieldTabs,
            'channels' => ee('Model')->get('Channel')->order('channel_title', 'asc')->all(),
            'members' => ee('Model')->get('Member')->order('screen_name', 'asc')->all(),
            'base_url' => $this->base_url,
            'form_url' => $import_id
                ? ee('CP/URL')->make('addons/settings/reizoko_jsongrab/edit/' . $import_id)
                : ee('CP/URL')->make('addons/settings/reizoko_jsongrab/create'),
            'cp_page_title' => $import ? 'Edit Import' : 'New Import',
        ];

        return [
            'heading' => $vars['cp_page_title'],
            'body' => ee('View')->make('reizoko_jsongrab:form')->render($vars),
            'breadcrumb' => [
                $this->base_url->compile() => 'Reizoko JsonGrab',
            ],
        ];
    }

    private function save($import_id = null)
    {
        $posted = ee()->input->post('field_map') ?: [];
        $map = [];

        foreach ($posted as $field => $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $map[$field] = $path;
            }
        }

        $data = [
            'name' => ee()->input->post('name'),
            'url' => ee()->input->post('url'),
            'http_header' => ee()->input->post('http_header'),
            'use_basic_auth' => ee()->input->post('use_basic_auth') ? 'y' : 'n',
            'basic_auth_user' => ee()->input->post('basic_auth_user'),
            'basic_auth_pass' => ee()->input->post('basic_auth_pass'),
            'channel_id' => (int) ee()->input->post('channel_id'),
            'root_path' => ee()->input->post('root_path'),
            'key_json_path' => ee()->input->post('key_json_path'),
            'key_field' => ee()->input->post('key_field'),
            'title_json_path' => ee()->input->post('title_json_path'),
            'url_title_json_path' => ee()->input->post('url_title_json_path'),
            'status' => ee()->input->post('status') ?: 'open',
            'author_id' => (int) ee()->input->post('author_id') ?: 1,
            'field_map' => json_encode($map),
        ];

        // Leave the password field blank on edit to keep the saved value.
        if ($import_id && $data['basic_auth_pass'] === '') {
            $existing = ee()->db->where('import_id', $import_id)->get('reizoko_jsongrab_imports')->row();
            if ($existing) {
                $data['basic_auth_user'] = $data['basic_auth_user'] !== '' ? $data['basic_auth_user'] : $existing->basic_auth_user;
                $data['basic_auth_pass'] = $existing->basic_auth_pass;
            }
        }

        if (empty($data['name']) || empty($data['url']) || empty($data['channel_id']) || empty($data['title_json_path'])) {
            ee('CP/Alert')->makeBanner('reizoko_jsongrab')
                ->asIssue()
                ->withTitle('Missing required fields')
                ->addToBody('Name, URL, Channel, and Title JSON Path are all required.')
                ->now();

            return $this->form($import_id);
        }

        if ($import_id) {
            ee()->db->where('import_id', $import_id)->update('reizoko_jsongrab_imports', $data);
        } else {
            ee()->db->insert('reizoko_jsongrab_imports', $data);
        }

        ee('CP/Alert')->makeBanner('reizoko_jsongrab')
            ->asSuccess()
            ->withTitle('Saved')
            ->addToBody('Import configuration saved.')
            ->defer();

        ee()->functions->redirect($this->base_url);
    }

    public function delete($import_id)
    {
        ee()->db->where('import_id', $import_id)->delete('reizoko_jsongrab_imports');

        ee('CP/Alert')->makeBanner('reizoko_jsongrab')
            ->asSuccess()
            ->withTitle('Deleted')
            ->addToBody('Import configuration deleted.')
            ->defer();

        ee()->functions->redirect($this->base_url);
    }

    public function run($import_id)
    {
        $import = ee()->db->where('import_id', $import_id)->get('reizoko_jsongrab_imports')->row();

        if (!$import) {
            show_404();
        }

        $importer = new Importer();

        try {
            $summary = $importer->run($import);

            $summaryText = sprintf(
                'Created %d, updated %d, %d error(s).',
                $summary['created'],
                $summary['updated'],
                count($summary['errors'])
            );

            ee()->db->where('import_id', $import_id)->update('reizoko_jsongrab_imports', [
                'last_run_date' => time(),
                'last_run_summary' => $summaryText,
            ]);

            $alert = ee('CP/Alert')->makeBanner('reizoko_jsongrab')
                ->asSuccess()
                ->withTitle('Import complete')
                ->addToBody($summaryText);

            foreach (array_slice($summary['errors'], 0, 10) as $error) {
                $alert->addToBody($error);
            }

            $alert->defer();
        } catch (\Exception $e) {
            ee('CP/Alert')->makeBanner('reizoko_jsongrab')
                ->asIssue()
                ->withTitle('Import failed')
                ->addToBody($e->getMessage())
                ->defer();
        }

        ee()->functions->redirect($this->base_url);
    }

    /**
     * AJAX: save one chunk of an import. The CP posts the running totals
     * back so the last-run summary can be written when the final chunk lands.
     */
    public function run_chunk($import_id)
    {
        $import = ee()->db->where('import_id', $import_id)->get('reizoko_jsongrab_imports')->row();

        if (!$import) {
            ee()->output->send_ajax_response(['error' => 'Import not found.']);
        }

        $offset = max(0, (int) ee()->input->post('offset'));

        try {
            $result = (new Importer())->run($import, $offset, Importer::CHUNK_SIZE);
        } catch (\Exception $e) {
            ee()->output->send_ajax_response(['error' => $e->getMessage()]);
        }

        if ($result['done']) {
            ee()->db->where('import_id', $import_id)->update('reizoko_jsongrab_imports', [
                'last_run_date' => time(),
                'last_run_summary' => sprintf(
                    'Created %d, updated %d, %d error(s).',
                    (int) ee()->input->post('created') + $result['created'],
                    (int) ee()->input->post('updated') + $result['updated'],
                    (int) ee()->input->post('error_count') + count($result['errors'])
                ),
            ]);
        }

        ee()->output->send_ajax_response($result);
    }

    private function channelNames()
    {
        $channels = ee('Model')->get('Channel')->all();
        $names = [];
        foreach ($channels as $channel) {
            $names[$channel->channel_id] = $channel->channel_title;
        }
        return $names;
    }
}
