# Polygon Amoy anchoring

The application keeps its existing internal wallet and private ledger. Each new SQL transaction can additionally be anchored to the `BGAAnchor` contract on Polygon Amoy (chain ID `80002`).

## Safety

- Use a dedicated testnet wallet. Never reuse a mainnet private key.
- Amoy POL has no intended monetary value and is used only for gas.
- Never commit `POLYGON_PRIVATE_KEY` to Git.

## Deploy

1. Run `php artisan polygon:wallet-create`. The private key is encrypted with Laravel `APP_KEY` and is never printed.
2. Fund the displayed public address with Amoy test POL.
3. Set `POLYGON_RPC_URL` and `POLYGON_CHAIN_ID=80002` in `.env`. `POLYGON_PRIVATE_KEY` can remain empty when using the encrypted wallet.
4. Run `php artisan polygon:deploy`. It decrypts the key only inside the deployment process and stores the public deployment information locally.
5. `POLYGON_CONTRACT_ADDRESS` may remain empty when using the stored deployment information.
6. Set `POLYGON_ENABLED=true`, then run `php artisan config:clear`.
7. Keep the Laravel queue worker running: `php artisan queue:work --tries=5`.

To anchor transactions created before Polygon was enabled, run `php artisan polygon:anchor-existing --limit=100` after the queue worker is online.

Confirmed transactions expose a PolygonScan link in Admin > Transaction History.
