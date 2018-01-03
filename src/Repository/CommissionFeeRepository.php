<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 9:35 AM
 */

namespace Repository;

use Entity\AbstractUser;
use Entity\CommissionFee;
use Persistence\PersistenceInterface;

class CommissionFeeRepository
{
    /**
     * @var PersistenceInterface
     */
    private $persistence;

    /**
     * @param PersistenceInterface $persistence
     */
    public function __construct(PersistenceInterface $persistence)
    {
        $this->persistence = $persistence;
    }

    /**
     * @param int $userId
     * @param \DateTime $date
     * @return CommissionFee|null
     */
    public function find(int $userId, \DateTime $date) :? CommissionFee
    {
        $discounts = $this->persistence->findAll('discount');

        /**
         * @var CommissionFee $discount
         */
        foreach ($discounts as $discount) {
            if ($discount->getUser()->getId() === $userId && $discount->isInPeriod($date)) {
                return $discount;
            }
        }

        return null;
    }

    /**
     * @param AbstractUser $user
     * @param \DateTime $periodStart
     * @param \DateTime $periodEnd
     * @param int $amount
     */
    public function create(AbstractUser $user, \DateTime $periodStart, \DateTime $periodEnd, int $amount) : void
    {
        $this->persistence->save('discount', new CommissionFee($user, $periodStart, $periodEnd, $amount));
    }
}
