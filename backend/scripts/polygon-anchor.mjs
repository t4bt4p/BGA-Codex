import { Contract, JsonRpcProvider, Wallet, parseUnits } from 'ethers';

const [digest, transactionId] = process.argv.slice(2);
const rpcUrl = process.env.POLYGON_RPC_URL;
const privateKey = process.env.POLYGON_PRIVATE_KEY;
const contractAddress = process.env.POLYGON_CONTRACT_ADDRESS;
async function anchor() {
    if (!digest || !transactionId || !rpcUrl || !privateKey || !contractAddress) {
        throw new Error('Polygon anchor configuration or arguments are incomplete');
    }
    const expectedChainId = BigInt(process.env.POLYGON_CHAIN_ID || '80002');
    const provider = new JsonRpcProvider(rpcUrl);
    try {
        const network = await provider.getNetwork();
        if (network.chainId !== expectedChainId) {
            throw new Error(`Unexpected chain ID ${network.chainId}; expected ${expectedChainId}`);
        }
        const wallet = new Wallet(privateKey, provider);
        const contract = new Contract(contractAddress, [
            'function anchor(bytes32 digest, uint256 transactionId)',
        ], wallet);
        const transaction = await contract.anchor(digest, BigInt(transactionId), {
            maxPriorityFeePerGas: parseUnits(process.env.POLYGON_PRIORITY_FEE_GWEI || '25', 'gwei'),
            maxFeePerGas: parseUnits(process.env.POLYGON_MAX_FEE_GWEI || '35', 'gwei'),
        });
        const receipt = await transaction.wait(Number(process.env.POLYGON_CONFIRMATIONS || '1'));
        process.stdout.write(JSON.stringify({
            transaction_hash: transaction.hash,
            block_number: receipt.blockNumber,
            chain_id: network.chainId.toString(),
            contract_address: contractAddress,
        }));
    } finally {
        provider.destroy();
    }
}

anchor().catch(error => {
    process.stderr.write(`Polygon anchor failed: ${error.shortMessage || error.message || 'Unable to send transaction'}\n`);
    process.exitCode = 1;
});
