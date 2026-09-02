// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract BGAAnchor {
    address public immutable owner;
    mapping(bytes32 => uint256) public anchoredTransactions;

    event TransactionAnchored(bytes32 indexed digest, uint256 indexed transactionId, uint256 anchoredAt);

    error OnlyOwner();
    error AlreadyAnchored();

    constructor() {
        owner = msg.sender;
    }

    function anchor(bytes32 digest, uint256 transactionId) external {
        if (msg.sender != owner) revert OnlyOwner();
        uint256 existingTransactionId = anchoredTransactions[digest];
        if (existingTransactionId == transactionId) return;
        if (existingTransactionId != 0) revert AlreadyAnchored();

        anchoredTransactions[digest] = transactionId;
        emit TransactionAnchored(digest, transactionId, block.timestamp);
    }
}
