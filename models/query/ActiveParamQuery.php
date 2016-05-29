<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 08.02.2016 17:50
 * via Gii Model Generator
 */

namespace yii\tools\params\models\query;

/**
 * This is the ActiveQuery class for [[ActiveParam]].
 *
 * @see ActiveParam
 */
class ActiveParamQuery extends \yii\db\ActiveQuery
{
    public function category($category)
    {
        $this->andWhere(['=', 'category', $category]);

        return $this;
    }

    public function name($name)
    {
        $this->andWhere(['=', 'name', $name]);

        return $this;
    }

    public function names($names)
    {
        $this->andWhere(['in', 'name', $names]);

        return $this;
    }

    public function value($value)
    {
        $this->andWhere(['=', 'value', $value]);

        return $this;
    }

    /**
     * @inheritdoc
     * @return \yii\tools\interfaces\ParamInterface[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return \yii\tools\interfaces\ParamInterface|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
