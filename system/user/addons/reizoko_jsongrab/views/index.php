<style>
.jg-wrap { width: 100%; }
.jg-actions { margin-bottom: 20px; }
.jg-actions a.button { background: #5D63F1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; }
.jg-actions a.button:hover { background: #4a50d4; }
table.jg-table { width: 100%; border-collapse: collapse; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; overflow: hidden; }
table.jg-table th, table.jg-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--ee-panel-border, #dfe0ef); font-size: 14px; vertical-align: top; }
table.jg-table th { font-weight: 600; background: rgba(0,0,0,0.02); }
table.jg-table th:nth-child(n+2), table.jg-table td:nth-child(n+2) { width: 1%; white-space: nowrap; }
table.jg-table td:first-child { overflow-wrap: anywhere; min-width: 180px; }
table.jg-table tr:last-child td { border-bottom: none; }
.jg-summary { font-size: 0.85em; color: #8f90b6; }
.jg-btns { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; white-space: nowrap; }
.jg-btns form { margin: 0; }
.jg-btn { display: inline-block; box-sizing: border-box; font: inherit; font-size: 13px; font-weight: 500; line-height: 1; padding: 7px 14px; border-radius: 4px; border: 1px solid var(--ee-panel-border, #dfe0ef); background: transparent; color: var(--ee-text-primary, #0d0d19); text-decoration: none; cursor: pointer; transition: background-color .15s, border-color .15s; }
.jg-btn:hover { background: rgba(93,99,241,0.08); border-color: #5D63F1; }
.jg-btn-run { background: #5D63F1; border-color: #5D63F1; color: #fff; }
.jg-btn-run:hover { background: #4a50d4; border-color: #4a50d4; }
.jg-btn-run:disabled { opacity: .55; cursor: default; background: #5D63F1; border-color: #5D63F1; }
.jg-btn-delete { color: #c0392b; }
.jg-btn-delete:hover { background: rgba(192,57,43,0.08); border-color: #c0392b; }
.jg-progress { display: flex; align-items: center; gap: 10px; margin-left: 8px; visibility: hidden; }
.jg-progress.is-on { visibility: visible; }
.jg-bar { flex: 0 0 90px; height: 6px; border-radius: 3px; background: rgba(93,99,241,0.18); overflow: hidden; }
.jg-fill { height: 100%; width: 0; background: #5D63F1; transition: width .25s ease-out; }
.jg-count { min-width: 60px; font-size: 12px; font-variant-numeric: tabular-nums; color: #8f90b6; }
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
			<td class="jg-lastrun">
				<?php if ($import->last_run_date): ?>
					<?= ee()->localize->human_time($import->last_run_date) ?>
					<div class="jg-summary"><?= htmlspecialchars($import->last_run_summary ?: '', ENT_QUOTES, 'UTF-8') ?></div>
				<?php else: ?>
					never
				<?php endif; ?>
			</td>
			<td class="jg-row-actions">
				<div class="jg-btns">
					<?= form_open(ee('CP/URL')->make('addons/settings/reizoko_jsongrab/run/' . $import->import_id), [
						'class' => 'jg-run-form',
						'data-chunk-url' => ee('CP/URL')->make('addons/settings/reizoko_jsongrab/run_chunk/' . $import->import_id)->compile(),
					]) ?>
						<button type="submit" class="jg-btn jg-btn-run">Run</button>
					<?= form_close() ?>
					<a class="jg-btn" href="<?= ee('CP/URL')->make('addons/settings/reizoko_jsongrab/edit/' . $import->import_id) ?>">Edit</a>
					<?= form_open(ee('CP/URL')->make('addons/settings/reizoko_jsongrab/delete/' . $import->import_id)) ?>
						<button type="submit" class="jg-btn jg-btn-delete" onclick="return confirm('Delete this import?')">Delete</button>
					<?= form_close() ?>
					<div class="jg-progress">
						<div class="jg-bar"><div class="jg-fill"></div></div>
						<span class="jg-count"></span>
					</div>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</div>

<script>
document.querySelectorAll('form.jg-run-form').forEach(function (form) {
	form.addEventListener('submit', function (e) {
		e.preventDefault();

		var cell = form.closest('td');
		var button = form.querySelector('button');
		var progress = cell.querySelector('.jg-progress');
		var fill = cell.querySelector('.jg-fill');
		var count = cell.querySelector('.jg-count');
		var lastRun = cell.parentNode.querySelector('.jg-lastrun');
		var totals = { created: 0, updated: 0, error_count: 0 };
		var errors = [];

		button.disabled = true;
		fill.style.width = '0';
		count.textContent = 'Starting';
		progress.classList.add('is-on');

		function setLastRun(when, text) {
			lastRun.textContent = '';
			lastRun.appendChild(document.createTextNode(when));
			var note = document.createElement('div');
			note.className = 'jg-summary';
			note.textContent = text;
			lastRun.appendChild(note);
		}

		function fail(message) {
			progress.classList.remove('is-on');
			setLastRun('Failed', message);
			button.disabled = false;
		}

		function step(offset) {
			var data = new FormData(form);
			data.append('offset', offset);
			data.append('created', totals.created);
			data.append('updated', totals.updated);
			data.append('error_count', totals.error_count);

			fetch(form.dataset.chunkUrl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.error) { return fail(res.error); }

					totals.created += res.created;
					totals.updated += res.updated;
					totals.error_count += res.errors.length;
					errors = errors.concat(res.errors);

					var pct = res.total ? Math.round(res.next_offset / res.total * 100) : 100;
					fill.style.width = pct + '%';
					count.textContent = res.next_offset + ' / ' + res.total;

					if (!res.done) { return step(res.next_offset); }

					setLastRun('Just now', 'Created ' + totals.created + ', updated ' + totals.updated + ', ' + totals.error_count + ' error(s).'
						+ (errors.length ? ' ' + errors.slice(0, 5).join(' | ') : ''));
					button.disabled = false;
				})
				.catch(function (err) { fail(err.message); });
		}

		step(0);
	});
});
</script>
