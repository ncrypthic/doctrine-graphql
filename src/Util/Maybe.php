<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Util;

class Maybe
{
    private $val;

    public function __construct($val)
    {
        $this->val = $val;
        $this->isEmpty = $val === null;
    }
    /**
     * Return value
     *
     * @return mixed
     * @throws Exception
     */
    public function value()
    {
        if($this->isEmpty()) {
            throw new \LogicException("Cannot get value of empty option");
        }
        return $this->val;
    }
    /**
     * Return $default if this instance value is empty
     * @param mixed $dflt
     * @return mixed
     */
    public function getOrElse($dflt)
    {
        return $this->isEmpty() ? $dflt : $this->val;
    }
    /**
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->val === null;
    }
    /**
     * Returns a Maybe instance
     *
     * @param mixed $val
     * @return Maybe
     */
    public static function Option($val) {
        return new Maybe($val);
    }
    /**
     * Return non-empty Maybe instance
     *
     * @param mixed $val
     * @return Maybe
     */
    public static function Some($val) {
        return new Maybe($val);
    }
    /**
     * Return empty Maybe instance
     *
     * @return Maybe
     */
    public static function None() {
        return new Maybe(null);
    }
}
