<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 18.02.16 16:06
 */

namespace yii\tools\params;

use yii\tools\helpers\FormatHelper;

/**
 * Class String
 * Shortcut for ActiveParam with type 'string'
 * @package yii\tools\params
 */
class String extends ActiveParam
{
    public $type = FormatHelper::TYPE_STRING;
}
