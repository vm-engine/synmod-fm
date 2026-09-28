# Strict Types Plan — synmod-fm

> **Status (2026-09-27): done.** Every tracked PHP file declares `strict_types`, and the package's `phpstan.neon` passes level 7 (Larastan, zero ignores, no baseline). Kept for history.

Same pattern as `packages/synapse`.

## Scope

- 24 in-repo PHP files, 10 missing `declare(strict_types=1)` — mostly `routes/*.php` and `lang/*/*.php` (1 each), no test/model concentration like the other modules.
- Smallest, lowest-risk module in this batch: no `src/Services` or `src/Livewire` files in the missing-declare list, so this is close to purely mechanical.

## Steps (mirror the synapse execution)

1. Add `declare(strict_types=1);` to the 10 files above.
2. `php -l` sweep + run this module's Pest suite from main root.
3. `vendor/bin/phpstan analyse packages/synmod-fm --error-format=raw --no-progress` from main root at level 7; fix findings.
4. `vendor/bin/pint packages/synmod-fm --format agent` from main root.
5. Re-run Pest to confirm green.

## Out of scope for this doc

Actually applying the fixes — inventory only. 
