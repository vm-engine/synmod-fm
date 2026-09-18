# Strict Types Plan — synmod-fm

Same pattern as `packages/synapse` (done — see that package's `phpstan.neon`, now level 6, 0 errors, 503 tests green). Not implemented here yet — inventory only.

## Scope

- 24 in-repo PHP files, 10 missing `declare(strict_types=1)` — mostly `routes/*.php` and `lang/*/*.php` (1 each), no test/model concentration like the other modules.
- No `phpstan.neon` in this package — run from the main Laravel project root against its `phpstan.neon` (level 7, already scans `packages/`).
- Smallest, lowest-risk module in this batch: no `src/Services` or `src/Livewire` files in the missing-declare list, so this is close to purely mechanical.

## Steps (mirror the synapse execution)

1. Add `declare(strict_types=1);` to the 10 files above.
2. `php -l` sweep + run this module's Pest suite from main root.
3. `vendor/bin/phpstan analyse packages/synmod-fm --error-format=raw --no-progress` from main root at level 7; fix findings.
4. `vendor/bin/pint packages/synmod-fm --format agent` from main root.
5. Re-run Pest to confirm green.

## Out of scope for this doc

Actually applying the fixes — inventory only. (See also `docs/SEMANTIC_CSS_PLAN.md` in this module for the still-pending CSS follow-up on `file-manager.blade.php`/`file-picker.blade.php`, unrelated to this plan.)
