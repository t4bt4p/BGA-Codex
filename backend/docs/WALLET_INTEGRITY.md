# Wallet integrity checks

Before creating a payment QR, settling a successful payment, or renting a game,
the server compares the wallet balance with the user's validated ledger history.
The wallet row is locked during the check and balance update. Credits and debits
use the verified balance, not an unchecked balance supplied by the database or client.

The check requires a valid majority of the three local ledger files and compares
the user's database transactions with that same majority snapshot. Before this
check, supported history fields are recovered as described in
[Transaction recovery](TRANSACTION_RECOVERY.md). Unrecoverable or deleted history,
missing ledger entries, unknown transaction types, and negative amounts return
HTTP 409 without creating a new financial
transaction. A wallet-only mismatch is automatically restored from verified history
in a separate locked transaction after rolling back the attempted operation. The
API then returns HTTP 409 with code `wallet_balance_corrected` and the corrected
balance. This preserves the repair without accidentally completing a rental or
creating a payment QR. The UI refreshes the wallet and displays “พบข้อผิดพลาด”. Missing entries may be legitimate queued work; they are not treated
as proof of an attack. Another user's pending entries do not block this user.

A paid top-up blocked by this check remains pending. After acknowledgement, the payment UI retries the same settlement; never ask the customer to pay again for that
same payment. Successful settlement remains idempotent. Returning a game remains
available because it does not debit or credit the wallet.

Wallet mismatches and completed repairs are logged with the user ID and old/new
balances. Wallet repair changes only the balance; transaction recovery is a
separate step and neither rewrites Node files. If history remains inconsistent,
incomplete, or lacks consensus, no wallet repair is performed. Compare the original ledger, payment
records, and Polygon evidence before resolving those cases manually.

This is an application-level check at the next financial action, not a database
trigger or continuous SQL monitor. It cannot stop a privileged database user from
issuing UPDATE, and it does not query Polygon on every payment. Its trust boundary
is the local majority ledger; an attacker controlling both the database and ledger
files is outside this protection. Existing balances require genuine historical
credit/debit evidence; an unexplained opening balance must be reviewed.
