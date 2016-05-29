<?php

/**
 * Author: Pavel Petrov <itnelo@gmail.com>
 * Date: 16.02.16 11:04
 */

namespace yii\tools\params;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\db\BaseActiveRecord;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\base\Component;
use yii\base\Module as YiiModule;
use yii\caching\DbDependency;
use yii\tools\params\models\ActiveParam;
use yii\tools\helpers\FormatHelper;
use yii\tools\interfaces\ParamsHolder;
use yii\tools\params\interfaces\ParamInterface;

/**
 * Class Params
 * For management component params
 * @package yii\tools\params\components\params
 */
class ActiveParams extends Component implements \ArrayAccess, \Iterator, \yii\base\Arrayable
{
    /**
     * Array of records from storage, represents volatile owner's params
     * indexed by param name
     *
     * @var array
     */
    private static $data = [];

    /**
     * Initial owner's params data from config file
     * @var array
     */
    private static $config = [];

    /**
     * @var Module|ParamsHolder
     */
    public $owner;

    /**
     * You can add default param
     * Fallback to default params implementation
     * Represents class which implement \yii\tools\params\interfaces\ParamInterface
     *
     * @var string
     */
    public $staticParamClass = 'yii\tools\params\StaticParam';

    /**
     * Key-value storage definition of component's params
     * Example:
     *
     * ```
     * [
     *     'name' => 'Users Management Module',
     *     'version' => '2.0.0',
     *     'param_from_db' => [
     *         'class' => 'yii\tools\params\String',
     *         'description' => 'My active parameter',
     *     ],
     * ]
     * ```
     *
     * @var array
     */
    public $params = [];

    /**
     * @var string
     */
    public $defaultType = FormatHelper::TYPE_STRING;

    /**
     * Will load actual active param data from storage only after GET param call
     * This means what on loading stage no select/cacheGet queries performs
     *
     * @var bool
     */
    public $lazyLoading = true;

    /**
     * Will cache active params by `updated_at` timestamp field
     * Note: local cache is enabled if $caching is false
     * true mean what third-party storage used to save data state between requests
     *
     * @var bool
     */
    public $caching = true;

    /**
     * Will insert active param record in storage if not exists
     *
     * Note: deleting, updating records structure not supported, only value field will be tracked
     *
     * @var bool
     */
    public $persistence = true;

    /**
     * Will insert active param record in storage (if not exists) on GET operations
     * This means what on loading stage no insert queries performs
     * If active param value really required in code, query will be executed
     *
     * Note: works only if $persistence is true
     *
     * @var bool
     */
    public $lazyPersistence = true;

    /**
     * Unique id of owner, used as category in storage
     * @var string
     */
    protected $uniqueId;

    /**
     * For caching params (1 cache record per owner)
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * Internal cache for object, not actual cache
     * @var bool
     */
    private $cacheValid = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->owner instanceof YiiModule && !$this->owner instanceof ParamsHolder) {
            throw new InvalidConfigException("Property 'owner' must be instanceof \\yii\\base\\Module"
                . " or implement interface ParamsHolder");
        }

        if ($this->owner instanceof BaseActiveRecord) {
            $this->owner->on(BaseActiveRecord::EVENT_AFTER_FIND, [$this, 'ownerInstanceChanged']);
            $this->owner->on(BaseActiveRecord::EVENT_AFTER_DELETE, [$this, 'ownerInstanceDeleted']);
        }

        $this->reset();

        $this->ensureParamsConfig();
        $this->params = [];

        if (!$this->lazyLoading) {
            $this->load();
        }

        parent::init();
    }

    /**
     * @return int
     */
    public function activeCount()
    {
        $this->ensureLoaded();

        return isset(static::$data[$this->cacheKey]) ? count(self::$data[$this->cacheKey]) : 0;
    }

    /**
     * Owner's active params count with flag READ_ONLY false
     * @return int
     */
    public function safeActiveCount()
    {
        $this->ensureLoaded();
        $count = 0;
        foreach ($this->params as $param) {
            if (!$param->isReadOnly()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return int
     */
    public function staticCount()
    {
        return count($this->params) - $this->activeCount();
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return isset(static::$config[$this->cacheKey][$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \InvalidArgumentException("Cannot get param '$offset' of " . get_class($this->owner)
                . " via ArrayAccess interface. Param doesn't exists");
        }

        $this->ensureLoaded();
        $this->ensureParam($offset);

        return $this->params[$offset]->get();
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        Yii::trace("Set param call ('$offset', '$value')"
            . ' for component ' . get_class($this->owner) . " '" . $this->uniqueId . "'", __METHOD__);

        if (!$this->offsetExists($offset)) {
            throw new \InvalidArgumentException('Cannot set param of ' . get_class($this->owner)
                . " via ArrayAccess interface. Param doesn't exists");
        }

        $this->ensureLoaded();
        $this->ensureParam($offset);

        $this->params[$offset]->set($value);
        $this->cacheValid = false;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        throw new NotSupportedException('Cannot unset param of ' . get_class($this->owner)
            . ' via ArrayAccess interface.');
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        $this->ensureLoaded();

        return reset($this->params);
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return current($this->params);
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return key($this->params);
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        return next($this->params);
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        return key($this->params) !== null;
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return array_keys($this->params);
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [];
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $this->ensureLoaded();
        return $this->params;
    }

    protected function ensureParamsConfig()
    {
        if (isset($this->owner->params)) {
            static::$config[$this->cacheKey] = $this->owner->params;

            return;
        }

        static::$config[$this->cacheKey] = [];

        if (!isset($this->params)) {
            Yii::warning('Component ' . get_class($this->owner)
                . " '{$this->uniqueId}' has no params, missing 'params' config file/property?", __METHOD__);

            return;
        }

        static::$config[$this->cacheKey] = array_replace_recursive(static::$config[$this->cacheKey], $this->params);
    }

    protected function ensureLoaded()
    {
        if (!$this->loaded) {
            $this->load();
        }
    }

    protected function ownerInstanceChanged()
    {
        $this->reset();

        if (!$this->lazyLoading) {
            $this->ensureLoaded();
        }
    }

    protected function ownerInstanceDeleted()
    {
        $params = ActiveParam::findAll(['=', 'category', $this->uniqueId]);

        foreach ($params as $param) {
            $param->delete();
        }

        $this->loaded = false;
    }

    protected function reset()
    {
        $oldUniqueId = $this->uniqueId;
        $oldCacheKey = $this->cacheKey;
        if (!$this->cacheValid) {
            unset(static::$data[$oldCacheKey]);
            $this->cacheValid = true;
        }
        $this->updateUniqueId();
        $this->updateCacheKey();
        if (!empty($oldUniqueId)) {
            Yii::info('Reset params for component ' . get_class($this->owner) . ' performed'
                . PHP_EOL . "Instance uniqueId changed from '$oldUniqueId' to "
                . "'" . $this->uniqueId . "'", __METHOD__);
            static::$config[$this->cacheKey] = static::$config[$oldCacheKey];
        }
        $this->loaded = false;
    }

    /**
     * @param $uniqueId
     */
    protected function setUniqueId($uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }

    protected function updateUniqueId()
    {
        $this->setUniqueId($this->owner->getUniqueId());
    }

    protected function updateCacheKey()
    {
        $this->cacheKey = ActiveParam::tableName() . $this->uniqueId;
    }

    /**
     * Initialization of internal params array, will receive data from database, if needed
     *
     * @throws \UnexpectedValueException
     * @return void
     */
    protected function load()
    {
        Yii::trace('Initializing params for component ' . get_class($this->owner) . " '" . $this->uniqueId . "'"
            . PHP_EOL . 'Lazy loading: ' . VarDumper::dumpAsString($this->lazyLoading)
            . PHP_EOL . 'Lazy persistence: ' . VarDumper::dumpAsString($this->lazyPersistence), __METHOD__);
        $this->params = [];

        if (empty(static::$config[$this->cacheKey])) {
            return;
        }

        foreach (static::$config[$this->cacheKey] as $name => $config) {
            $this->params[$name] = is_array($config) && isset($config['class'])
                ? $this->loadActiveParam($name, $config)
                : $this->loadStaticParam($name, $config);
        }

        $this->loaded = true;

        Yii::info('Params for component ' . get_class($this->owner)
            . " '{$this->uniqueId}' configured and ready-to-use"
            . PHP_EOL . VarDumper::dumpAsString($this->params, 3), __METHOD__);
    }

    /**
     * @param $name
     * @param $value
     * @return object
     */
    protected function loadStaticParam($name, $value)
    {
        return Yii::createObject([
            'class' => $this->staticParamClass,
            'name' => $name,
            'value' => $value,
        ]);
    }

    /**
     * @param $name
     * @param $config
     * @return object
     * @throws \UnexpectedValueException
     */
    protected function loadActiveParam($name, $config)
    {
        // Volatile param from storage.
        $this->ensureActiveParams();

        if (!isset($config['name'])) {
            $config['name'] = $name;
        }

        if (!isset(static::$data[$this->cacheKey][$name])) {
            if (!$this->persistence) {
                throw new \UnexpectedValueException("Active param '$name' of component " . get_class($this->owner)
                    . " '{$this->uniqueId}' defined as active but doesn't exists in storage");
            }
            $param = $this->createActiveParam($name, $config);
            static::$data[$this->cacheKey][$name] = $param->toArray();

            return $param;
        }

        ActiveParam::populateRecord($param = Yii::createObject($config), static::$data[$this->cacheKey][$name]);
        $param->afterFind();

        return $param;
    }

    /**
     * @return void
     */
    protected function ensureActiveParams()
    {
        if ($this->caching && $this->cacheExists()) {
            return;
        }

        static::$data[$this->cacheKey] = ArrayHelper::index(
            ActiveParam::find()->category($this->uniqueId)->orderBy('name')->asArray()->all(),
            'name'
        );

        Yii::info('Active params for component ' . get_class($this->owner)
            . " '{$this->uniqueId}' selected from database", __METHOD__);

        if ($this->caching) {
            $this->updateCache();
        }
    }

    public function ensureParam($name)
    {
        if (!$this->params[$name] instanceof ParamInterface) {
            throw new \LogicException("Can't get param '$name' of '" . get_class($this->owner) . "'"
                . ", param doesn't have implemented ParamInterface");
        }
    }

    /**
     * @return bool
     */
    protected function cacheExists()
    {
        if (isset(static::$data[$this->cacheKey])) {
            return true;
        }

        if ((static::$data[$this->cacheKey] = Yii::$app->cache->get($this->cacheKey)) !== false) {
            Yii::info('Active params for component ' . get_class($this->owner)
                . " '{$this->uniqueId}' served from cache", __METHOD__);
            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    protected function updateCache()
    {
        if (!isset(static::$data[$this->cacheKey])) {
            static::$data[$this->cacheKey] = [];
        }

        $dependency = Yii::$container->get(DbDependency::className(), [], [
            'sql' => ActiveParam::CACHE_DEPENDENCY,
            'params' => [':category' => $this->uniqueId],
            'reusable' => true
        ]);

        if (Yii::$app->cache->set($this->cacheKey, static::$data[$this->cacheKey], 0, $dependency)) {
            Yii::info('Active params for component ' . get_class($this->owner)
                . " '{$this->uniqueId}' cached successfully", __METHOD__);
            return;
        }

        Yii::warning('Caching active params for component ' . get_class($this->owner)
            . " '{$this->uniqueId}' failed", __METHOD__);
    }

    /**
     * @param $name
     * @param $config
     * @return object
     * @throws \LogicException
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function createActiveParam($name, $config)
    {
        $param = Yii::createObject($config);

        if (empty($param->description)) {
            $param->description = 'Active param';
        }

        if (empty($param->value)) {
            $param->value = Yii::$app->formatter->format('', FormatHelper::typeToFormat($param->type));
        }

        if (empty($param->category)) {
            $param->category = $this->uniqueId;
        }

        Yii::info("Creating active param '$name' with config "
            . VarDumper::dumpAsString($param->getAttributes()), __METHOD__);

        if (!$this->lazyPersistence || !$param->lazyPersistence) {
            $param->set($param->value);
            $this->cacheValid = false;
        } else {
            Yii::info("Skipping add query to storage for param '$name' due to lazy persistence", __METHOD__);
        }

        return $param;
    }
}
