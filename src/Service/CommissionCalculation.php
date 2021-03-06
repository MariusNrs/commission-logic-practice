<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 9:52 AM
 */

namespace Service;

use Entity\AbstractOperation;
use Entity\CommissionFee;
use Repository\CommissionFeeRepository;
use Repository\OperationRepository;

class CommissionCalculation
{
    const MAXIMUM_CASH_IN_COMMISSION_AMOUNT = 500;
    const MINIMUM_CASH_OUT_COMMISSION_AMOUNT = 50;

    const OPERATION_CASH_OUT_COMMISSION_PERCENTAGE = 0.3;
    const OPERATION_CASH_IN_COMMISSION_PERCENTAGE = 0.03;

    const WEEKLY_OPERATION_LIMIT_FOR_DISCOUNT = 3;

    /**
     * @var OperationRepository
     */
    protected $operationRepository;

    /**
     * @var CommissionFeeRepository
     */
    protected $discountRepository;

    /**
     * @var Exchange
     */
    protected $exchangeService;

    /**
     * @var int
     */
    private $commission;

    /**
     * @param OperationRepository $operationRepository
     * @param CommissionFeeRepository $discountRepository
     * @param Exchange $exchangeService
     */
    public function __construct(
        OperationRepository $operationRepository,
        CommissionFeeRepository $discountRepository,
        Exchange $exchangeService
    ) {
        $this->discountRepository = $discountRepository;
        $this->operationRepository = $operationRepository;
        $this->exchangeService = $exchangeService;
    }

    /**
     * @param AbstractOperation $operation
     */
    public function calculate(AbstractOperation $operation) : void
    {
        $this->calculateForCashIn($operation);
        $this->calculateForCashOut($operation);
    }

    /**
     * @param AbstractOperation $operation
     * @return string
     */
    public function getFormattedCommission(AbstractOperation $operation) : string
    {
        return number_format(
            $this->commission / 100,
            $operation->getAmountPrecise(),
            '.',
            ''
        );
    }

    /**
     * @param AbstractOperation $operation
     */
    protected function calculateForCashIn(AbstractOperation $operation) : void
    {
        if (!$operation->isCashInOperation()) {
            return;
        }

        $commission = $operation->getAmount() / 100 * self::OPERATION_CASH_IN_COMMISSION_PERCENTAGE;
        $commissionInEur = $this->exchangeService->calculateRate($commission, DEFAULT_CURRENCY);

        if ($commissionInEur > self::MAXIMUM_CASH_IN_COMMISSION_AMOUNT) {
            $commission = $this->exchangeService->calculateRate(
                self::MAXIMUM_CASH_IN_COMMISSION_AMOUNT,
                $operation->getCurrency()
            );
        }

        $this->commission = $commission;
    }

    /**
     * @param AbstractOperation $operation
     */
    protected function calculateForCashOut(AbstractOperation $operation) : void
    {
        if (!$operation->isCashOutOperation()) {
            return;
        }

        $this->calculateForCashOutLegalUser($operation);
        $this->calculateForCashOutNaturalUser($operation);
    }

    /**
     * @param AbstractOperation $operation
     */
    protected function calculateForCashOutLegalUser(AbstractOperation $operation) : void
    {
        if (!($operation->getUser())->isLegalUser()) {
            return;
        }

        $commission = $operation->getAmount() / 100 * self::OPERATION_CASH_OUT_COMMISSION_PERCENTAGE;
        $commissionInEur = $this->exchangeService->calculateRate($commission, DEFAULT_CURRENCY);

        if ($commissionInEur <= self::MINIMUM_CASH_OUT_COMMISSION_AMOUNT) {
            $commission = $this->exchangeService->calculateRate(
                self::MINIMUM_CASH_OUT_COMMISSION_AMOUNT,
                $operation->getCurrency()
            );
        }

        $this->commission = $commission;
    }

    /**
     * @param AbstractOperation $operation
     */
    protected function calculateForCashOutNaturalUser(AbstractOperation $operation) : void
    {
        if (!($operation->getUser())->isNaturalUser()) {
            return;
        }

        $weekOperationsCounter = $this->operationRepository->getWeekOperationsCounter(
            $operation->getDate(),
            $operation->getUser()->getId(),
            $operation->getId()
        );

        $this->maybeApplyDiscount($weekOperationsCounter, $operation);
        $this->maybeApplyRegularCommission($weekOperationsCounter, $operation);
    }

    /**
     * Improved ceil() alternative with precision support.
     *
     * @param $value
     * @param int $precision
     * @return float
     */
    private function ceiling($value, int $precision = 0) : float
    {
        return ceil($value * pow(10, $precision)) / pow(10, $precision);
    }

    /**
     * @param int $weekOperationsCounter
     * @param AbstractOperation $operation
     */
    private function maybeApplyDiscount(int $weekOperationsCounter, AbstractOperation $operation) : void
    {
        if ($weekOperationsCounter > self::WEEKLY_OPERATION_LIMIT_FOR_DISCOUNT) {
            return;
        }

        $discount = $this->discountRepository->find(
            $operation->getUser()->getId(),
            $operation->getDate()
        );

        $this->maybeUserHasDiscount($discount, $operation);
        $this->maybeUserHasNotDiscount($discount, $operation);
    }

    /**
     * @param int $weekOperationsCounter
     * @param AbstractOperation $operation
     */
    private function maybeApplyRegularCommission(int $weekOperationsCounter, AbstractOperation $operation) : void
    {
        if ($weekOperationsCounter <= self::WEEKLY_OPERATION_LIMIT_FOR_DISCOUNT) {
            return;
        }

        $commission = $this->exchangeService->calculateRate(
            $operation->getAmount() / 100,
            $operation->getCurrency()
        );

        $this->commission = $commission * self::OPERATION_CASH_OUT_COMMISSION_PERCENTAGE;
    }

    /**
     * @param CommissionFee $discount
     * @param AbstractOperation $operation
     */
    private function maybeUserHasDiscount(CommissionFee $discount, AbstractOperation $operation) : void
    {
        if (is_null($discount)) {
            return;
        }

        $convertedAmountFloat = $this->exchangeService->calculateRate(
            $operation->getAmount() / 100,
            DEFAULT_CURRENCY,
            $operation->getCurrency()
        );

        $convertedAmountInt = $this->ceiling($convertedAmountFloat, 2) * 100;
        $unusedAmount = $discount->useDiscount($convertedAmountInt);

        if ($unusedAmount === 0) {
            $this->commission = 0;
        } else {
            $commission = $this->exchangeService->calculateRate(
                $unusedAmount / 100,
                $operation->getCurrency(),
                DEFAULT_CURRENCY
            );

            $this->commission = $commission * self::OPERATION_CASH_OUT_COMMISSION_PERCENTAGE;
        }
    }

    /**
     * @param CommissionFee $discount
     * @param AbstractOperation $operation
     */
    private function maybeUserHasNotDiscount(CommissionFee $discount, AbstractOperation $operation) : void
    {
        if (!is_null($discount)) {
            return;
        }

        $this->commission = $this->exchangeService->calculateRate(
                $operation->getAmount() / 100,
                $operation->getCurrency(),
                DEFAULT_CURRENCY
            ) * self::OPERATION_CASH_OUT_COMMISSION_PERCENTAGE;
    }
}
