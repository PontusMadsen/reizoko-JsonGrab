<style>
.jg-form { max-width: 900px; }
.jg-section { margin-bottom: 24px; padding: 20px; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; }
.jg-section h3 { margin: 0 0 16px; font-size: 1.1em; font-weight: 600; }
.jg-row { margin-bottom: 16px; }
.jg-row label { display: block; font-weight: 500; margin-bottom: 4px; }
.jg-row .jg-desc { font-size: 0.85em; color: #8f90b6; margin-bottom: 6px; }
.jg-row input[type="text"],
.jg-row input[type="url"],
.jg-row input[type="password"],
.jg-row select { width: 100%; padding: 8px 12px; border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 4px; font-size: 14px; }
.jg-actions button { background: #5D63F1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; }
.jg-actions button:hover { background: #4a50d4; }
.jg-preview-btn { background: none; border: 1px solid var(--ee-panel-border, #dfe0ef); color: #5D63F1; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; }
.jg-preview-status { font-size: 0.85em; color: #8f90b6; margin-top: 8px; }
.jg-preview-error { color: #c0392b; margin-top: 8px; font-size: 0.9em; }

/* Tabbed field mapping, styled like an entry publish form */
.jg-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--ee-panel-border, #dfe0ef); margin-bottom: 16px; flex-wrap: wrap; }
.jg-tab-btn { background: none; border: none; padding: 10px 16px; font-size: 14px; cursor: pointer; color: #8f90b6; border-bottom: 2px solid transparent; margin-bottom: -1px; }
.jg-tab-btn.active { color: #5D63F1; border-bottom-color: #5D63F1; font-weight: 500; }
.jg-tab-panel { display: none; }
.jg-tab-panel.active { display: block; }
.jg-field-row { display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--ee-panel-border, #dfe0ef); }
.jg-field-row:last-child { border-bottom: none; }
.jg-field-label { font-weight: 500; font-size: 14px; }
.jg-field-type { display: block; font-weight: 400; font-size: 0.8em; color: #8f90b6; }
.jg-empty-tabs { padding: 16px 0; color: #8f90b6; font-size: 0.9em; }
</style>

<div class="jg-form">
	<?= form_open($form_url) ?>
	<input type="hidden" name="import_id" value="<?= htmlspecialchars($import->import_id ?? '', ENT_QUOTES, 'UTF-8') ?>">

	<div class="jg-section">
		<h3>Source</h3>

		<div class="jg-row">
			<label>Name</label>
			<input type="text" name="name" value="<?= htmlspecialchars($import->name ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
		</div>

		<div class="jg-row">
			<label>JSON URL</label>
			<input type="url" name="url" value="<?= htmlspecialchars($import->url ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
		</div>

		<div class="jg-row">
			<label>HTTP Header <span class="jg-desc">(optional, e.g. <code>Authorization: Bearer xxxx</code>)</span></label>
			<input type="text" name="http_header" value="<?= htmlspecialchars($import->http_header ?? '', ENT_QUOTES, 'UTF-8') ?>">
		</div>

		<div class="jg-row">
			<label>Use HTTP Basic Auth</label>
			<select name="use_basic_auth">
				<option value="">No</option>
				<option value="1" <?= (isset($import->use_basic_auth) && $import->use_basic_auth == 'y') ? 'selected' : '' ?>>Yes</option>
			</select>
		</div>

		<div class="jg-row">
			<label>Basic Auth Username <span class="jg-desc">optional — leave both blank to use <code>system/user/config/jsongrab.env.php</code> instead. Filling these in is less safe (stored in the database) but saves working in two places.</span></label>
			<input type="text" name="basic_auth_user" value="<?= htmlspecialchars($import->basic_auth_user ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
		</div>

		<div class="jg-row">
			<label>Basic Auth Password <span class="jg-desc"><?= (isset($import->basic_auth_pass) && $import->basic_auth_pass !== '') ? 'Already saved — leave blank to keep it.' : 'optional' ?></span></label>
			<input type="password" name="basic_auth_pass" value="" autocomplete="new-password">
		</div>

		<div class="jg-row">
			<label>Root Path <span class="jg-desc">Dot path to the list of items in the response, e.g. <code>data.items</code>. Leave blank if the response itself is a JSON array — OData-style <code>{ "value": [...] }</code> envelopes are detected automatically.</span></label>
			<input type="text" name="root_path" value="<?= htmlspecialchars($import->root_path ?? '', ENT_QUOTES, 'UTF-8') ?>">
		</div>

		<div class="jg-row">
			<button type="button" class="jg-preview-btn" onclick="jgPreview()">Load Sample Data</button>
			<div id="jg-preview-status" class="jg-preview-status">Load a live sample first — every dropdown below gets filled in from it.</div>
			<div id="jg-preview-error" class="jg-preview-error"></div>
		</div>
	</div>

	<div class="jg-section">
		<h3>Entry</h3>

		<div class="jg-row">
			<label>Channel</label>
			<select name="channel_id" id="jg-channel-select" required>
				<option value="">—</option>
				<?php foreach ($channels as $channel): ?>
				<option value="<?= $channel->channel_id ?>" <?= (isset($import->channel_id) && $import->channel_id == $channel->channel_id) ? 'selected' : '' ?>>
					<?= htmlspecialchars($channel->channel_title, ENT_QUOTES, 'UTF-8') ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="jg-row">
			<label>Title <span class="jg-desc">Which field from the sample data becomes the entry title</span></label>
			<select id="jg-title-path" name="title_json_path" required></select>
		</div>

		<div class="jg-row">
			<label>URL Title <span class="jg-desc">optional — leave as "auto-generate" to build it from the title</span></label>
			<select id="jg-url-title-path" name="url_title_json_path"></select>
		</div>

		<div class="jg-row">
			<label>Status</label>
			<input type="text" name="status" value="<?= htmlspecialchars($import->status ?? 'open', ENT_QUOTES, 'UTF-8') ?>">
		</div>

		<div class="jg-row">
			<label>Author</label>
			<select name="author_id">
				<?php foreach ($members as $member): ?>
				<option value="<?= $member->member_id ?>" <?= (isset($import->author_id) && $import->author_id == $member->member_id) ? 'selected' : '' ?>>
					<?= htmlspecialchars($member->screen_name, ENT_QUOTES, 'UTF-8') ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="jg-section">
		<h3>Matching Existing Entries</h3>
		<p class="jg-desc">To update entries on re-import instead of always creating new ones, match on the entry's title or URL title against a value in the JSON.</p>

		<div class="jg-row">
			<label>Match Field</label>
			<select name="key_field">
				<option value="">Always create new entries</option>
				<option value="title" <?= (isset($import->key_field) && $import->key_field == 'title') ? 'selected' : '' ?>>Title</option>
				<option value="url_title" <?= (isset($import->key_field) && $import->key_field == 'url_title') ? 'selected' : '' ?>>URL Title</option>
			</select>
		</div>

		<div class="jg-row">
			<label>Match Against</label>
			<select id="jg-key-path" name="key_json_path"></select>
		</div>
	</div>

	<div class="jg-section">
		<h3>Field Mapping</h3>
		<p class="jg-desc">Just like filling in the entry by hand — pick which piece of the sample data goes into each field. Load the sample data above first. Grid, Matrix, relationship, and file fields aren't shown here — a single value can't fill those in, so they're left out on purpose rather than offering a mapping that wouldn't work.</p>

		<div id="jg-tabs" class="jg-tabs"></div>
		<div id="jg-tab-panels"></div>
	</div>

	<div class="jg-actions">
		<button type="submit" name="save" value="1">Save Import</button>
	</div>

	<?= form_close() ?>
</div>

<script type="application/json" id="jg-bootstrap">
<?= json_encode([
	'tabs' => $field_tabs,
	'savedMap' => $field_map,
	'savedTitle' => $import->title_json_path ?? '',
	'savedUrlTitle' => $import->url_title_json_path ?? '',
	'savedKey' => $import->key_json_path ?? '',
	'fieldsUrl' => ee('CP/URL')->make('addons/settings/jsongrab/fields')->compile(),
	'previewUrl' => ee('CP/URL')->make('addons/settings/jsongrab/preview')->compile(),
]) ?>
</script>

<script>
(function () {
	var bootstrap = JSON.parse(document.getElementById('jg-bootstrap').textContent);
	var jgFieldTabs = bootstrap.tabs || [];
	var jgFlatRows = []; // [[path, value], ...] populated after Load Sample Data

	// Flattens the sample item into rows that keep track of which top-level
	// branch each path belongs to and how deeply nested it is, so the
	// dropdown can group and indent instead of showing one flat list.
	function jgFlatten(obj, prefix, depth, top, out) {
		prefix = prefix || '';
		depth = depth || 0;
		out = out || [];
		if (obj !== null && typeof obj === 'object') {
			var keys = Object.keys(obj);
			if (keys.length === 0) {
				out.push({ path: prefix, value: Array.isArray(obj) ? '[]' : '{}', top: top || prefix, depth: depth });
			}
			keys.forEach(function (key) {
				var path = prefix ? prefix + '.' + key : key;
				jgFlatten(obj[key], path, depth + 1, top || key, out);
			});
		} else {
			out.push({ path: prefix, value: obj === null ? 'null' : String(obj), top: top || prefix, depth: depth });
		}
		return out;
	}

	function jgFillSelect(selectEl, savedValue, includeAutoOption) {
		selectEl.innerHTML = '';

		if (includeAutoOption) {
			var autoOpt = document.createElement('option');
			autoOpt.value = '';
			autoOpt.textContent = '— auto-generate from title —';
			selectEl.appendChild(autoOpt);
		} else {
			var blankOpt = document.createElement('option');
			blankOpt.value = '';
			blankOpt.textContent = jgFlatRows.length ? '— choose —' : '— load sample data first —';
			selectEl.appendChild(blankOpt);
		}

		var found = false;
		var groups = {};
		var groupOrder = [];

		jgFlatRows.forEach(function (row) {
			if (!groups[row.top]) {
				groups[row.top] = [];
				groupOrder.push(row.top);
			}
			groups[row.top].push(row);
		});

		groupOrder.forEach(function (top) {
			var rows = groups[top];
			// A single top-level scalar doesn't need its own group — put it
			// straight in the list instead of a group of one.
			var container = (rows.length === 1 && rows[0].path === top)
				? selectEl
				: (function () {
					var og = document.createElement('optgroup');
					og.label = top;
					selectEl.appendChild(og);
					return og;
				})();

			rows.forEach(function (row) {
				var opt = document.createElement('option');
				opt.value = row.path;
				var indent = container === selectEl ? '' : '  '.repeat(Math.max(row.depth - 1, 0));
				var label = row.path === top ? row.path : row.path.slice(top.length + 1);
				opt.textContent = indent + label + '  —  ' + row.value.substring(0, 30);
				if (row.path === savedValue) {
					opt.selected = true;
					found = true;
				}
				container.appendChild(opt);
			});
		});

		if (savedValue && !found) {
			var savedOpt = document.createElement('option');
			savedOpt.value = savedValue;
			savedOpt.textContent = savedValue + '  (saved — not in this sample)';
			savedOpt.selected = true;
			selectEl.appendChild(savedOpt);
		}
	}

	function jgRenderFieldTabs(tabs, savedMap) {
		var tabsEl = document.getElementById('jg-tabs');
		var panelsEl = document.getElementById('jg-tab-panels');
		tabsEl.innerHTML = '';
		panelsEl.innerHTML = '';

		if (!tabs.length) {
			panelsEl.innerHTML = '<div class="jg-empty-tabs">Choose a channel above to see its fields.</div>';
			return;
		}

		tabs.forEach(function (tab, i) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'jg-tab-btn' + (i === 0 ? ' active' : '');
			btn.textContent = tab.label;
			btn.addEventListener('click', function () {
				tabsEl.querySelectorAll('.jg-tab-btn').forEach(function (b) { b.classList.remove('active'); });
				panelsEl.querySelectorAll('.jg-tab-panel').forEach(function (p) { p.classList.remove('active'); });
				btn.classList.add('active');
				panel.classList.add('active');
			});
			tabsEl.appendChild(btn);

			var panel = document.createElement('div');
			panel.className = 'jg-tab-panel' + (i === 0 ? ' active' : '');

			tab.fields.forEach(function (field) {
				var row = document.createElement('div');
				row.className = 'jg-field-row';

				var label = document.createElement('div');
				label.className = 'jg-field-label';
				label.textContent = field.label;
				var typeEl = document.createElement('span');
				typeEl.className = 'jg-field-type';
				typeEl.textContent = field.type;
				label.appendChild(typeEl);

				var select = document.createElement('select');
				select.name = 'field_map[' + field.name + ']';
				select.className = 'jg-field-select';
				jgFillSelect(select, savedMap[field.name] || '', false);

				row.appendChild(label);
				row.appendChild(select);
				panel.appendChild(row);
			});

			panelsEl.appendChild(panel);
		});
	}

	function jgRefreshAllSelects() {
		jgFillSelect(document.getElementById('jg-title-path'), bootstrap.savedTitle, false);
		jgFillSelect(document.getElementById('jg-url-title-path'), bootstrap.savedUrlTitle, true);
		jgFillSelect(document.getElementById('jg-key-path'), bootstrap.savedKey, false);
		document.querySelectorAll('.jg-field-select').forEach(function (sel) {
			var current = sel.value;
			jgFillSelect(sel, current, false);
		});
	}

	window.jgPreview = function () {
		var statusEl = document.getElementById('jg-preview-status');
		var errorEl = document.getElementById('jg-preview-error');
		statusEl.textContent = 'Fetching…';
		errorEl.textContent = '';

		var form = document.querySelector('.jg-form form') || document.querySelector('form');
		var data = new FormData(form);

		fetch(bootstrap.previewUrl, { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json.error) {
					statusEl.textContent = '';
					errorEl.textContent = json.error;
					return;
				}
				jgFlatRows = jgFlatten(json.sample);
				statusEl.textContent = json.count + ' item(s) found. Dropdowns below are now filled in from the first one.';
				jgRefreshAllSelects();
			})
			.catch(function (err) {
				statusEl.textContent = '';
				errorEl.textContent = 'Request failed: ' + err.message;
			});
	};

	document.getElementById('jg-channel-select').addEventListener('change', function () {
		var channelId = this.value;
		if (!channelId) {
			jgRenderFieldTabs([], {});
			return;
		}
		fetch(bootstrap.fieldsUrl + '?channel_id=' + encodeURIComponent(channelId), { credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				jgFieldTabs = json.tabs || [];
				jgRenderFieldTabs(jgFieldTabs, {});
			});
	});

	jgRenderFieldTabs(jgFieldTabs, bootstrap.savedMap);
	jgRefreshAllSelects();
})();
</script>
