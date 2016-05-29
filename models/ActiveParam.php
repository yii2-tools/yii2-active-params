<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 08.02.2016 17:51
 * via Gii Model Generator
 */

namespace yii\tools\params\models;

use Yii;
use yii\helpers\VarDumper;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\tools\params\interfaces\ParamInterface;
use yii\tools\components\TypeValidator;
use yii\tools\params\models\query\ActiveParamQuery;

/**
 * This is the model class for table "{{%engine_params}}".
 *
 * @property string $name
 * @property string $value
 * @property string $category
 * @property integer $created_at
 * @property integer $updated_at
 */
class ActiveParam extends ActiveRecord implements ParamInterface
{
    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';

    const READ_ONLY = 1;
    const COOKIE = 2;

    const CACHE_DEPENDENCY = <<<SQL
        SELECT MAX([[updated_at]])
        FROM {{%engine_params}}
        WHERE [[category]] = :category AND [[updated_at]] > 0
SQL;

    public $type;
    public $description;
    public $flags;
    public $lazyPersistence = true;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%engine_params}}';
    }

    public function formName()
    {
        return 'ActiveParam';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return array_merge(parent::scenarios(), [
            static::SCENARIO_CREATE => ['value'],
            static::SCENARIO_UPDATE => ['value'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'category', 'value', 'created_at', 'updated_at'], 'required'],
            [['created_at', 'updated_at'], 'integer'],
            [['value'], TypeValidator::className(), 'on' => [static::SCENARIO_CREATE, static::SCENARIO_UPDATE]],
            [['name', 'category'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('app', 'Name'),
            'value' => Yii::t('app', 'Value'),
            'category' => Yii::t('app', 'Category'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
        ];
    }

    /**
     * Return name of this param
     * @return string
     */
    public function name()
    {
        return $this->getAttribute('name');
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @throws \LogicException
     */
    public function set($value)
    {
        if ($this->get() == $value) {
            return;
        }

        $this->value = $value;

        $this->setScenario($this->isNewRecord ? static::SCENARIO_CREATE : static::SCENARIO_UPDATE);
        if (!$this->save()) {
            $extraInfo = $this->hasErrors()
                ? count($e = $this->getErrors()) > 1 ? VarDumper::dumpAsString($e) : $e['value'][0]
                : 'not a validation error!';
            throw new \LogicException("Can't save ActiveParam '{$this->name}' in storage: " . PHP_EOL . $extraInfo);
        }
        $this->setScenario(static::SCENARIO_DEFAULT);
    }

    /**
     * @return mixed
     */
    public function isReadOnly()
    {
        return $this->flags & static::READ_ONLY;
    }

    /**
     * @inheritdoc
     * @return ActiveParamQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ActiveParamQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return ($name === 'name') ? $this->name() : parent::__get($name);
    }
}
