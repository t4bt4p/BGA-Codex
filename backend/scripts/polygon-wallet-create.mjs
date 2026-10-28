import { Wallet } from 'ethers';

const wallet = Wallet.createRandom();
process.stdout.write(JSON.stringify({ address: wallet.address, private_key: wallet.privateKey }));
