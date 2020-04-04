<?php

declare(strict_types=1);


namespace App\Services\Sync;

use App\Exceptions\ImportException;
use App\Services\Configuration\Configuration;
use App\Services\Sync\JobStatus\ProgressInformation;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use Log;

/**
 * Class GenerateTransactions.
 */
class GenerateTransactions
{
    use ProgressInformation;

    /** @var array */
    private $accounts;
    /** @var Configuration */
    private $configuration;

    /** @var array */
    private $targetAccounts;
    /** @var array */
    private $targetTypes;

    /**
     * GenerateTransactions constructor.
     */
    public function __construct()
    {
        $this->targetAccounts = [];
        $this->targetTypes    = [];
    }

    /**
     * @param array $array
     *
     * @throws ImportException
     * @return array
     */
    public function getTransactions(array $array): array
    {
        $return = [];
        /** @var array $entry */
        foreach ($array as $entry) {
            $return[] = $this->generateTransaction($entry);
        }
        $this->addMessage(0, sprintf('Parsed %d YNAB transactions for further processing.', count($return)));

        return $return;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
    }

    /**
     * @param string $accountId
     *
     * @return int
     */
    private function ffAccountId(string $accountId): int
    {
        foreach ($this->configuration->getAccounts() as $budgetId => $accounts) {
            /**
             * @var string $ynabId
             * @var int    $fireflyId
             */
            foreach ($accounts as $ynabId => $fireflyId) {
                if ($ynabId === $accountId) {
                    return $fireflyId;
                }
            }
        }

        return 0;
    }

    /**
     * @param array $entry
     *
     * @throws ImportException
     * @return array
     */
    private function generateTransaction(array $entry): array
    {
        $return = [
            'apply_rules'             => $this->configuration->isRules(),
            'error_if_duplicate_hash' => true,
            'group_title'             => $entry['memo'],
            'transactions'            => [],
        ];

        $index = 0;
        /** @var array $transaction */
        foreach ($entry['transactions'] as $transaction) {
            $amount                         = $this->positiveAmount($transaction['amount']);
            $return['transactions'][$index] = [
                'amount'           => $amount,
                'type'             => $transaction['amount'] > 0 ? 'deposit' : 'withdrawal',
                'description'      => $transaction['memo'],
                'date'             => $transaction['date'],
                'notes'            => sprintf('Transaction ID: %s', $transaction['transaction_id']),
                'source_id'        => null,
                'source_name'      => $transaction['account_name'],
                'destination_name' => $transaction['payee_name'],
                'destination_id'   => null,
            ];
            // double check source:
            $accountId = $this->ffAccountId($transaction['account_id']);
            if (0 !== $accountId) {
                $return['transactions'][$index]['source_id']   = $accountId;
                $return['transactions'][$index]['source_name'] = null;
            }

            // double check destination:
            $accountId = $this->ffAccountId($transaction['payee_id']);
            if (0 !== $accountId) {
                $return['transactions'][$index]['destination_id']   = $accountId;
                $return['transactions'][$index]['destination_name'] = null;
            }

            // get transfer account:
            if (0 === strpos($transaction['payee_name'], 'Transfer : ')) {
                // payee is one of your own accounts
                $return['transactions'][$index]['destination_id']   = $this->ffAccountId($transaction['transfer_account_id']);
                $return['transactions'][$index]['destination_name'] = null;
                $return['transactions'][$index]['type']             = 'transfer';
            }

            // if the original amount is > 0, then its a deposit and switch accounts.
            if ($transaction['amount'] > 0) {
                // switch!
                $orgSourceId                                        = $return['transactions'][$index]['source_id'];
                $orgSourceName                                      = $return['transactions'][$index]['source_name'];
                $return['transactions'][$index]['source_id']        = $return['transactions'][$index]['destination_id'];
                $return['transactions'][$index]['source_name']      = $return['transactions'][$index]['destination_name'];
                $return['transactions'][$index]['destination_id']   = $orgSourceId;
                $return['transactions'][$index]['destination_name'] = $orgSourceName;
            }
            $index++;
        }
        return $return;
    }

    /**
     * @param int $accountId
     *
     * @throws ApiHttpException
     * @return string
     */
    private function getAccountType(int $accountId): string
    {
        $uri   = (string) config('ynab.uri');
        $token = (string) config('ynab.access_token');
        app('log')->debug(sprintf('Going to download account #%d', $accountId));
        $request = new GetAccountRequest($uri, $token);
        $request->setId($accountId);
        /** @var GetAccountResponse $result */
        $result = $request->get();
        $type   = $result->getAccount()->type;

        app('log')->debug(sprintf('Discovered that account #%d is of type "%s"', $accountId, $type));

        return $type;
    }

    /**
     * @param string $name
     * @param string $iban
     *
     * @return int|null
     */
    private function getMappedId(string $name, string $iban): ?int
    {
        $fullName = $name;
        if ('' !== $iban) {
            $fullName = sprintf('%s (%s)', $name, $iban);
        }
        if (isset($this->configuration->getMapping()[$fullName])) {
            return (int) $this->configuration->getMapping()[$fullName];
        }

        return null;
    }

    /**
     * @param int $mappedId
     *
     * @return string
     */
    private function getMappedType(int $mappedId): string
    {
        if (!isset($this->configuration->getAccountTypes()[$mappedId])) {
            app('log')->warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            $accountType             = $this->getAccountType($mappedId);
            $accountTypes            = $this->configuration->getAccountTypes();
            $accountTypes[$mappedId] = $accountType;
            $this->configuration->setAccountTypes($accountTypes);

            return $accountType;
        }

        return $this->configuration->getAccountTypes()[$mappedId] ?? 'expense';
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @throws ImportException
     * @return string
     */
    private function getTransactionType(string $source, string $destination): string
    {
        $combination = sprintf('%s-%s', $source, $destination);
        switch ($combination) {
            default:
                throw new ImportException(sprintf('Unknown combination: %s and %s', $source, $destination));
            case 'asset-expense':
                return 'withdrawal';
            case 'asset-asset':
                return 'transfer';
            case 'revenue-asset':
                return 'deposit';
        }
    }

    /**
     * @param int $amount
     *
     * @return string
     */
    private function positiveAmount(int $amount): string
    {
        if ($amount < 0) {
            $amount = $amount * -1;
        }

        return (string) ($amount / 100);
    }
}
