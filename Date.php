<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 18.02.16 16:06
 */

namespace yii\tools\params;

use yii\tools\params\models\ActiveParam;
use yii\tools\helpers\FormatHelper;

/**
 * Class Date
 * Shortcut for ActiveParam with type 'date'
 * @package yii\tools\params
 */
class Date extends ActiveParam
{
    public $type = FormatHelper::TYPE_DATE;
}
