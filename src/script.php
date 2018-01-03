<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 10:14 AM
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Persistence\MemoryPersistence;
use Repository\CommissionFeeRepository;
use Repository\OperationRepository;
use Repository\UserRepository;
use Service\CommissionCalculation;
use Service\Exchange;
use Service\Rates;

$memoryPersistence = new MemoryPersistence();

$userRepository = new UserRepository($memoryPersistence);
$operationRepository = new OperationRepository($memoryPersistence);
$commissionFeeRepository = new CommissionFeeRepository($memoryPersistence);

$rates = new Rates();
$exchange = new Exchange($rates, DEFAULT_CURRENCY);
$commissionCalculation = new CommissionCalculation(
    $operationRepository,
    $commissionFeeRepository,
    $exchange
);

$csvRows = array_map('str_getcsv', file($argv[1]));

// Set up and store operation and user entities.
foreach ($csvRows as $index => $csvRow) {
    $user = $userRepository->findOrCreate((int)$csvRow[1], $csvRow[2]);
    $operation = $operationRepository->create(
        $index + 1,
        $csvRow[0],
        $csvRow[3],
        $csvRow[4],
        $csvRow[5],
        $user,
        $commissionFeeRepository
    );
}

$operations = $operationRepository->getAll();

foreach ($operations as $operation) {
    $commissionCalculation->calculate($operation);

    fwrite(STDOUT, $commissionCalculation->getFormattedCommission($operation) . PHP_EOL);
}
