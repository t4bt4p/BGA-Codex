# Automatic transaction recovery

`transactions:restore` runs every ten seconds through the existing scheduler.
Recovery also runs before returning transaction history and before financial
operations. This is polling and request-time verification, not an SQL trigger;
direct SQL edits can exist until the next check. Scheduler/RPC/DB outages are not
an immediate-recovery guarantee.

The recovery source is a hash-valid chain present in at least two of three local
Node files. A missing copy is never silently recreated from a single surviving
copy. Recovery holds the ledger write lock, locks transaction rows, and restores
supported fields in one database transaction. It records before/after values in
the application log and does not rewrite Node files, append new blocks, enqueue
jobs, send Polygon transactions, or alter Rental/Topup records.

Supported fields are `User_id`, `Bg_id`, `Rental_id`, `T_cost`, `T_type`, and
`created_at`, matched by `Ts_id = data.transaction_id`. A changed existing
`Chain_payload_hash` is restored from the block hash; a missing hash on a row
marked confirmed is also restored. A null hash on a pending/unanchored row is
allowed because the Polygon worker may not yet have assigned it.

Limitations deliberately require review rather than inventing historical data:

- A deleted/changed `Ts_id` is reported as missing. Rows are neither recreated nor
  deleted because an edited key is indistinguishable from a deletion plus another
  row without more evidence.
- Legacy blocks lacking `transaction_id` are skipped. Transactions not yet written
  to Nodes are not recoverable from those Nodes.
- `updated_at` is preserved, not restored: Node `data.timestamp` represents
  `created_at`, and block `timestamp` represents when the ledger block was made.
- `Chain_network`, `Chain_tx_hash`, `Chain_block_number`, `Chain_status`,
  `Chain_error`, and `Chain_confirmed_at` are not copied into Nodes. Their recovery
  needs independent Polygon/payment evidence; this command does not guess them.
- Missing foreign-key parents, invalid source records, duplicate transaction IDs,
  or no valid majority cannot be repaired by treating the database as the truth.
- Two identically rewritten, rehashed Node files remain outside this local
  majority trust boundary. Polygon is not queried on every recovery.

When a financial request discovers changed history, recovery commits first and
HTTP 409 `transaction_data_restored` prompts the user to retry. No new payment or
rental is made by that request. If the wallet also differs, its separate verified
balance recovery runs on the next attempt. For an already-paid top-up the UI
retries the same payment after acknowledgement, never a second charge.

History returns corrected data and an `X-Transactions-Restored` count for popup
feedback. Background corrections are logged and do not themselves push a popup.
User filtering runs after recovery so altered ownership is corrected before data
is returned to a member.

Inspect without writes:

```bash
docker compose exec laravel.test php artisan transactions:restore --dry-run
```

Check scheduling with `php artisan schedule:list`. The `scheduler` service must
remain running for the ten-second background check.
