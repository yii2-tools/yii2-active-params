<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 17.02.16 0:56
 */

namespace yii\tools\params;

use yii\base\NotSupportedException;
use yii\base\Component;
use yii\tools\params\interfaces\ParamInterface;

class StaticParam extends Component implements ParamInterface
{
    /**
     * Param value
     * @var mixed
     */
    public $name;

    /**
     * Param value
     * @var mixed
     */
    public $value;

    /**
     * Return name of this param
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function set($value)
    {
        throw new NotSupportedException('Trying to change static param');
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function isReadOnly()
    {
        return true;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->get();
    }
}
