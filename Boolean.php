<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 18.02.16 16:06
 */

namespace yii\tools\params;

use yii\tools\helpers\FormatHelper;

/**
 * Class Boolean
 * Shortcut for ActiveParam with type 'boolean'
 * @package yii\tools\params
 */
class Boolean extends ActiveParam
{
    public $type = FormatHelper::TYPE_BOOLEAN;
}
