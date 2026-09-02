import fs from 'node:fs';
import solc from 'solc';
import { ContractFactory, JsonRpcProvider, Wallet, parseUnits } from 'ethers';

const source = fs.readFileSync(new URL('../contracts/BGAAnchor.sol', import.meta.url), 'utf8');
const input = {
    language: 'Solidity',
    sources: { 'BGAAnchor.sol': { content: source } },
    settings: { outputSelection: { '*': { '*': ['abi', 'evm.bytecode.object'] } } },
};
const output = JSON.parse(solc.compile(JSON.stringify(input)));
const errors = (output.errors || []).filter(error => error.severity === 'error');
if (errors.length) throw new Error(errors.map(error => error.formattedMessage).join('\n'));

const artifact = output.contracts['BGAAnchor.sol'].BGAAnchor;
const provider = new JsonRpcProvider(process.env.POLYGON_RPC_URL);
const network = await provider.getNetwork();
const expectedChainId = BigInt(process.env.POLYGON_CHAIN_ID || '80002');
if (network.chainId !== expectedChainId) throw new Error(`Unexpected chain ID ${network.chainId}`);

const wallet = new Wallet(process.env.POLYGON_PRIVATE_KEY, provider);
const factory = new ContractFactory(artifact.abi, artifact.evm.bytecode.object, wallet);
const contract = await factory.deploy({
    maxPriorityFeePerGas: parseUnits(process.env.POLYGON_PRIORITY_FEE_GWEI || '25', 'gwei'),
    maxFeePerGas: parseUnits(process.env.POLYGON_MAX_FEE_GWEI || '35', 'gwei'),
});
await contract.waitForDeployment();
const deployment = contract.deploymentTransaction();
const receipt = await deployment.wait(1);

process.stdout.write(JSON.stringify({
    contract_address: await contract.getAddress(),
    transaction_hash: deployment.hash,
    block_number: receipt.blockNumber,
    deployer: wallet.address,
    chain_id: network.chainId.toString(),
}));
