<?php
/**
 * Created by PhpStorm.
 * User: Marius
 * Date: 11/29/2017
 * Time: 9:02 AM
 */

namespace Entity;

class NaturalUser extends AbstractUser
{
    /**
     * @var bool
     */
    protected $isNaturalUser = true;
}
