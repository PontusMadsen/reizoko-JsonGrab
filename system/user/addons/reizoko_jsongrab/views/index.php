<style>
.jg-wrap { max-width: 1000px; }
.jg-actions { margin-bottom: 20px; }
.jg-actions a.button { background: #5D63F1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; }
.jg-actions a.button:hover { background: #4a50d4; }
table.jg-table { width: 100%; border-collapse: collapse; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; overflow: hidden; }
table.jg-table th, table.jg-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--ee-panel-border, #dfe0ef); font-size: 14px; vertical-align: top; }
table.jg-table th { font-weight: 600; background: rgba(0,0,0,0.02); }
table.jg-table tr:last-child td { border-bottom: none; }
.jg-summary { font-size: 0.85em; color: #8f90b6; }
.jg-row-actions a { margin-right: 12px; font-size: 13px; }
.jg-empty { padding: 24px; text-align: center; color: #8f90b6; }
</style>

<div class="jg-wrap">
	<div class="jg-actions">
		<a class="button" href="<?= ee('CP/URL')->make('addons/settings/reizoko_jsongrab/create') ?>">New Import</a>
	</div>

	<?php if (empty($imports)): ?>
		<div class="jg-empty">No imports configured yet.</div>
	<?php else: ?>
	<table class="jg-table">
		<tr>
			<th>Name</th>
			<th>Channel</th>
			<th>Last Run</th>
			<th></th>
		</tr>
		<?php foreach ($imports as $import): ?>
		<tr>
			<td>
				<?= htmlspecialchars($import->name, ENT_QUOTES, 'UTF-8') ?>
				<div class="jg-summary"><?= htmlspecialchars($import->url, ENT_QUOTES, 'UTF-8') ?></div>
			</td>
			<td><?= htmlspecialchars($channels[$import->channel_id] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
			<td>
				<?php if ($import->last_run_date): ?>
					<?= ee()->localize->human_time($import->last_run_date) ?>
					<div class="jg-summary"><?= htmlspecialchars($import->last_run_summary ?: '', ENT_QUOTES, 'UTF-8') ?></div>
				<?php else: ?>
					never
				<?php endif; ?>
			</td>
			<td class="jg-row-actions">
				<?= form_open(ee('CP/URL')->make('addons/settings/reizoko_jsongrab/run/' . $import->import_id), ['style' => 'display:inline']) ?>
					<button type="submit" style="background:none;border:none;color:#5D63F1;cursor:pointer;padding:0;font-size:13px;">Run</button>
				<?= form_close() ?>
				<a href="<?= ee('CP/URL')->make('addons/settings/reizoko_jsongrab/edit/' . $import->import_id) ?>">Edit</a>
				<?= form_open(ee('CP/URL')->make('addons/settings/reizoko_jsongrab/delete/' . $import->import_id), ['style' => 'display:inline']) ?>
					<button type="submit" onclick="return confirm('Delete this import?')" style="background:none;border:none;color:#c0392b;cursor:pointer;padding:0;font-size:13px;">Delete</button>
				<?= form_close() ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</div>
