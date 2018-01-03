<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 9:51 AM
 */

namespace Service;


class Exchange
{
    /**
     * @var Rates
     */
    private $rates;

    /**
     * @var string
     */
    private $defaultCurrency;

    /**
     * @param Rates $rates
     * @param string $defaultCurrency
     */
    public function __construct(Rates $rates, string $defaultCurrency)
    {
        $this->rates = $rates;
        $this->defaultCurrency = $defaultCurrency;
    }

    /**
     * @param string $currency
     * @return float
     */
    public function getCurrencyRate(string $currency) : float
    {
        $rates = $this->rates->getRates();

        if (isset($rates[$currency])) {
            return $rates[$currency];
        }


    }

    public function calculateRate($amount, $toCurrency, $fromCurrency = null) : float
    {
        if (!isset($fromCurrency)) {
            $fromCurrency = $this->defaultCurrency;
        }

        if ($this->rates->getBaseCurrency() !== $fromCurrency) {
            $amount = $amount / $this->getCurrencyRate($fromCurrency);
        }

        if ($toCurrency === $this->rates->getBaseCurrency()) {
            return $amount;
        }

        return $amount * $this->getCurrencyRate($toCurrency);
    }
}