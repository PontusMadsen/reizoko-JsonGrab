<?php

namespace Reizoko\JsonGrab\Model;

class Importer
{
    /**
     * Run a saved import config against its JSON endpoint.
     *
     * @param object $import Row from exp_reizoko_jsongrab_imports (as stdClass/array-ish with property access)
     * @return array Summary: ['created' => int, 'updated' => int, 'errors' => array]
     */
    public function run($import)
    {
        $useBasicAuth = !empty($import->use_basic_auth) && $import->use_basic_auth === 'y';
        $json = $this->fetch($import->url, $import->http_header, $useBasicAuth, $import);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }

        $items = $this->resolveItems($data, $import->root_path);

        if (!is_array($items)) {
            throw new \RuntimeException('Root path "' . $import->root_path . '" did not resolve to a list of items.');
        }

        $fieldMap = $import->field_map ? json_decode($import->field_map, true) : [];
        $fieldMap = is_array($fieldMap) ? $fieldMap : [];
        $fieldMap = $this->mapToFieldIds($fieldMap);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $result = $this->importItem($import, $item, $fieldMap);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Item ' . $index . ': ' . $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Fetch a live sample item so the CP can show real field names instead
     * of making the user guess at the JSON shape.
     *
     * @return array ['sample' => first item, 'count' => total items found]
     */
    public function preview($import)
    {
        $useBasicAuth = !empty($import->use_basic_auth) && $import->use_basic_auth === 'y';
        $json = $this->fetch($import->url, $import->http_header, $useBasicAuth, $import);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }

        $items = $this->resolveItems($data, $import->root_path);

        if (!is_array($items)) {
            throw new \RuntimeException('Root path "' . $import->root_path . '" did not resolve to a list of items.');
        }

        if (empty($items)) {
            throw new \RuntimeException('The response contained zero items.');
        }

        return [
            'sample' => reset($items),
            'count' => count($items),
        ];
    }

    /**
     * ChannelEntry / ContentModel addresses custom fields internally as
     * "field_id_{N}", never by their short name — even though the CP shows
     * and the field_map is saved using short names. Translate here, once
     * per run, rather than making every set() silently no-op.
     */
    private function mapToFieldIds(array $fieldMap)
    {
        if (empty($fieldMap)) {
            return [];
        }

        $fields = ee('Model')->get('ChannelField')
            ->filter('field_name', 'IN', array_keys($fieldMap))
            ->all();

        $out = [];
        foreach ($fields as $field) {
            if (isset($fieldMap[$field->field_name])) {
                $out['field_id_' . $field->field_id] = $fieldMap[$field->field_name];
            }
        }

        return $out;
    }

    /**
     * Resolve the list of items to import. If no Root Path was given and the
     * response looks like an OData-style envelope (an object with a "value"
     * array in it), use that automatically instead
     * of treating the whole envelope as the item list.
     */
    private function resolveItems($data, $rootPath)
    {
        if ($rootPath) {
            return $this->dotGet($data, $rootPath);
        }

        if (is_array($data) && isset($data['value']) && is_array($data['value']) && array_is_list($data['value'])) {
            return $data['value'];
        }

        return $data;
    }

    private function importItem($import, $item, array $fieldMap)
    {
        $title = $import->title_json_path ? $this->dotGet($item, $import->title_json_path) : null;

        if (empty($title)) {
            throw new \RuntimeException('No title found at path "' . $import->title_json_path . '"');
        }

        $urlTitle = $import->url_title_json_path
            ? $this->dotGet($item, $import->url_title_json_path)
            : ee('Format')->make('Text', $title)->urlSlug()->compile();

        $key = $import->key_json_path ? $this->dotGet($item, $import->key_json_path) : null;

        // key_field is restricted (in the CP form) to 'title' or 'url_title' —
        // both real columns on the entry itself, so filter() works directly.
        // Matching against an arbitrary custom field isn't supported here.
        $entry = null;
        if ($import->key_field && $key !== null && $key !== '') {
            $entry = ee('Model')->get('ChannelEntry')
                ->filter('channel_id', $import->channel_id)
                ->filter($import->key_field, (string) $key)
                ->first();
        }

        $data = [
            'author_id' => $import->author_id ?: 1,
            'status' => $import->status ?: 'open',
            'title' => (string) $title,
            'url_title' => (string) $urlTitle,
        ];

        foreach ($fieldMap as $fieldName => $path) {
            if ($fieldName === '' || $path === '') {
                continue;
            }
            $data[$fieldName] = $this->dotGet($item, $path);
        }

        $isNew = !$entry;

        if ($isNew) {
            $entry = ee('Model')->make('ChannelEntry');
            $data['entry_date'] = time();
            $data['site_id'] = 1;
        }

        // channel_id must be assigned before set() is called with the rest
        // of the data — ChannelEntry only knows which custom fields exist
        // once it can look up its channel, and set() checks that at the
        // very start of the call, before applying anything from $data.
        // Without this, a brand-new entry's channel_id and its custom
        // field values would land in the same set() call, and every
        // mapped field would silently be dropped as unrecognized.
        $entry->channel_id = $import->channel_id;

        $entry->set($data);
        $entry->save();

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Fetch a URL, optionally with a single "Header-Name: value" line and/or
     * HTTP Basic Auth using credentials from reizoko_jsongrab.env.php.
     */
    private function fetch($url, $header = null, $useBasicAuth = false, $import = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$header]);
        }

        if ($useBasicAuth) {
            $creds = $this->basicAuthCredentials($import);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $creds['user'] . ':' . $creds['pass']);
        }

        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Request failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Request returned HTTP ' . $status);
        }

        return $body;
    }

    /**
     * Resolve Basic Auth credentials. Per-import credentials saved on the
     * import row take priority (less safe — stored in the DB — but kept as
     * an option); otherwise falls back to system/user/config/reizoko_jsongrab.env.php.
     */
    private function basicAuthCredentials($import = null)
    {
        if ($import && !empty($import->basic_auth_user) && !empty($import->basic_auth_pass)) {
            return ['user' => trim($import->basic_auth_user), 'pass' => trim($import->basic_auth_pass)];
        }

        $path = SYSPATH . 'user/config/reizoko_jsongrab.env.php';

        if (!file_exists($path)) {
            throw new \RuntimeException('Basic Auth requested but no credentials were saved on the import and ' . $path . ' does not exist.');
        }

        $env = include $path;
        $user = trim($env['JSONGRAB_API_USER'] ?? '');
        $pass = trim($env['JSONGRAB_API_PASS'] ?? '');

        if ($user === '' || $pass === '') {
            throw new \RuntimeException('No credentials saved on the import, and JSONGRAB_API_USER / JSONGRAB_API_PASS are not set in reizoko_jsongrab.env.php.');
        }

        return ['user' => $user, 'pass' => $pass];
    }

    /**
     * Resolve a dot-notated path (with optional numeric array indices) against
     * a decoded JSON structure. e.g. "data.items.0.name"
     */
    private function dotGet($data, $path)
    {
        if ($path === null || $path === '') {
            return $data;
        }

        $segments = explode('.', $path);
        $value = $data;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return $value;
    }
}
