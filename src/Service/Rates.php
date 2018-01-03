<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 9:51 AM
 */

namespace Service;

class Rates
{
    /**
     * @return array
     */
    public function getRates() : array
    {
        return [
            'EUR' => 1,
            'USD' => 1.1497,
            'JPY' => 129.53,
        ];
    }

    /**
     * @return string
     */
    public function getBaseCurrency() : string
    {
        return 'EUR';
    }
}
