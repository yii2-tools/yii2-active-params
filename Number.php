<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 18.02.16 16:06
 */

namespace yii\tools\params;

use yii\tools\params\models\ActiveParam;
use yii\tools\helpers\FormatHelper;

/**
 * Class Number
 * Shortcut for ActiveParam with type 'number'
 * @package yii\tools\params
 */
class Number extends ActiveParam
{
    public $type = FormatHelper::TYPE_NUMBER;
}
