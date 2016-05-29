<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 16.02.16 11:32
 */

namespace yii\tools\params\interfaces;

/**
 * Interface ParamInterface
 * @package yii\tools\params\interfaces
 */
interface ParamInterface
{
    const EVENT_GET = 'doGet';
    const EVENT_SET = 'doSet';

    /**
     * Return name of this param
     * @return string
     */
    public function name();

    /**
     * @param mixed $value
     * @return void
     */
    public function set($value);

    /**
     * @return mixed
     */
    public function get();

    /**
     * NOTE: this means what end-user can't change this param
     * Engine still can change this param by statement like:
     * $model->params['paramName']->set('newValue');
     * @return mixed
     */
    public function isReadOnly();

    /**
     * @return string
     */
    public function __toString();
}
