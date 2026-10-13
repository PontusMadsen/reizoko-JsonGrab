# JsonGrab

A CP-only ExpressionEngine 7 add-on that imports channel entries from a JSON API endpoint.

Built to pull items from a JSON or OData-style API into an EE channel on a recurring schedule, without the CSV/XML/WordPress import machinery a general-purpose tool like DataGrab carries.

## Features

- One saved "Import" per data source: JSON URL, optional custom HTTP header, optional HTTP Basic Auth
- Root Path with automatic detection of OData-style `{ "value": [...] }` envelopes
- Field mapping UI mirrors the channel's real Publish Layout — same tabs, same order, dropdowns fed by a live "Load Sample Data" preview instead of typed JSON paths
- Structured field types (Grid, Matrix, relationships, files) are intentionally excluded from mapping — a single JSON path can't populate those
- Match-and-update on re-import by Title or URL Title, or always create new entries
- Manual "Run" button in the CP, plus `php eecli.php jsongrab:run --id=<n>` for cron
- Basic Auth credentials can live in `system/user/config/jsongrab.env.php` (kept out of the database and the CP entirely) or, less safely but more convenient, directly on the import

## Install

1. Copy `system/user/addons/jsongrab` into your EE install's `system/user/addons/`
2. Install via the CP (Add-Ons) or `php eecli.php addons:install jsongrab`
3. Add-Ons → JsonGrab → New Import

## Credentials file

For Basic Auth kept out of the database, create `system/user/config/jsongrab.env.php`:

```php
<?php
return [
    'JSONGRAB_API_USER' => 'username',
    'JSONGRAB_API_PASS' => 'password',
];
```

Do not commit this file.

## License

MIT, see [LICENSE](LICENSE).
