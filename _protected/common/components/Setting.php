<?php
/**
 * @author Fred <mconyango@gmail.com>
 * Date: 2015/11/23
 * Time: 5:03 PM
 *
 * Adopted from https://github.com/dinhtrung/yii2-setting
 */

namespace common\components;


/**
 * Settings Model
 *
 *
 * DATABASE STRUCTURE:
 *
 * CREATE TABLE IF NOT EXISTS `setting` (
 * [[category]] varchar(64) NOT NULL default 'system',
 * [[key]] varchar(255) NOT NULL,
 * [[value]] text NOT NULL,
 * PRIMARY KEY (`id`),
 * KEY `category_key` ([[category]],[[key]])
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
 */
use Yii;
use yii\base\Application;
use yii\base\Component;
use yii\db\Schema;

class Setting extends Component
{
    public $settingTable = 'conf_setting';
    protected $_toBeSave = [];
    protected $_toBeDelete = [];
    protected $_catToBeDel = [];
    protected $_cacheFlush = [];
    protected $_items = [];

    /**
     * Setting::init()
     *
     * @throws \yii\db\Exception
     */
    public function init()
    {
        if (YII_DEBUG && !Yii::$app->getDb()->getTableSchema("{{%" . $this->settingTable . "}}")) {
            Yii::$app->db->createCommand(
                Yii::$app->getDb()->getQueryBuilder()->createTable("{{%" . $this->settingTable . "}}", [
                        'category' => Schema::TYPE_STRING,
                        'key' => Schema::TYPE_STRING,
                        'value' => Schema::TYPE_TEXT,
                    ]
                )
            )->execute();
            Yii::$app->db->createCommand(
                Yii::$app->getDb()->getQueryBuilder()->addPrimaryKey('category_key', "{{%" . $this->settingTable . "}}", 'category,key')
            )->execute();
        }
        $this->on(Application::EVENT_AFTER_REQUEST, [$this, 'commit']);
    }

    /**
     * Setting::set()
     *
     * @param string $category
     * @param mixed $key
     * @param string $value
     * @param bool $toDatabase
     */
    public function set($category = 'system', $key, $value = '', $toDatabase = true)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v)
                $this->set($category, $k, $v, $toDatabase);
        } else {
            if ($toDatabase)
                $this->_toBeSave [$category] [$key] = $value;
            $this->_items [$category] [$key] = $value;
        }
    }

    /**
     * Setting::get()
     *
     * @param string $category
     * @param string $key
     * @param string $default
     * @return null|string
     */
    public function get($category = 'system', $key = '', $default = '')
    {
        if (!isset ($this->_items [$category]))
            $this->load($category);
        if (empty ($key) && empty ($default) && !empty ($category))
            return isset ($this->_items [$category]) ? $this->_items [$category] : null;
        if (isset ($this->_items [$category] [$key]))
            return $this->_items [$category] [$key];
        return !empty ($default) ? $default : null;
    }

    /**
     * Setting::delete()
     *
     * @param string $category
     * @param string $key
     */
    public function delete($category = 'system', $key = '')
    {
        if (!empty ($category) && empty ($key)) {
            $this->_catToBeDel [] = $category;
            return;
        }
        if (is_array($key)) {
            foreach ($key as $k)
                $this->delete($category, $k);
        } else {
            if (isset ($this->_items [$category] [$key])) {
                unset ($this->_items [$category] [$key]);
                $this->_toBeDelete [$category] [] = $key;
            }
        }
    }

    /**
     * @param $category
     * @return string
     */
    public static function getCacheKey($category)
    {
        return $category . '-settings';
    }

    /**
     * Setting::load()
     *
     * @param mixed $category
     * @return array|mixed|void
     */
    public function load($category)
    {
        $cache_key = static::getCacheKey($category);
        $items = Yii::$app->cache->get($cache_key);
        if (!$items) {
            $result = Yii::$app->getDb()->createCommand(
                "SELECT * FROM {{%" . $this->settingTable . "}} WHERE [[category]]=:cat", [
                ':cat' => $category,
            ])->queryAll();
            if (empty ($result)) {
                $this->set($category, '{empty}', '{empty}', false);
                return false;
            }
            $items = [];
            foreach ($result as $row)
                $items [$row ['key']] = @unserialize($row ['value']);
            Yii::$app->cache->add($cache_key, $items, 60);
        }
        $this->set($category, $items, '', false);
        return $items;
    }

    /**
     * Setting::toArray()
     * @return array
     */
    public function toArray()
    {
        return $this->_items;
    }

    /**
     * Setting::addDbItem()
     *
     * @param string $category
     * @param mixed $key
     * @param mixed $value
     * @throws \yii\db\Exception
     */
    private function addDbItem($category = 'system', $key, $value)
    {
        $result = Yii::$app->getDb()->createCommand(
            "SELECT * FROM {{%" . $this->settingTable . "}} WHERE [[category]]=:cat AND [[key]]=:key LIMIT 1", [
            ':cat' => $category,
            ':key' => $key
        ])->queryOne();
        $_value = @serialize($value);
        if (!$result) {
            Yii::$app->getDb()->createCommand(
                "INSERT INTO {{%" . $this->settingTable . "}} ([[category]], [[key]], [[value]]) VALUES(:cat,:key,:value)",
                [
                    ':cat' => $category,
                    ':key' => $key,
                    ':value' => $_value
                ])->execute();
        } else {
            Yii::$app->getDb()->createCommand(
                "UPDATE {{%" . $this->settingTable . "}} SET [[value]]=:value WHERE [[category]]=:cat AND [[key]]=:key",
                [
                    ':cat' => $category,
                    ':key' => $key,
                    ':value' => $_value
                ])->execute();
        }
    }

    /**
     * @throws \yii\db\Exception
     */
    public function commit()
    {
        $this->_cacheFlush = [];
        if (count($this->_catToBeDel) > 0) {
            foreach ($this->_catToBeDel as $catName) {
                Yii::$app->getDb()->createCommand(
                    "DELETE FROM {{%" . $this->settingTable . "}} WHERE [[category]]=:cat", [
                    ':cat' => $catName
                ])->execute();
                $this->_cacheFlush [] = $catName;
                if (isset ($this->_toBeDelete [$catName]))
                    unset ($this->_toBeDelete [$catName]);
                if (isset ($this->_toBeSave [$catName]))
                    unset ($this->_toBeSave [$catName]);
            }
        }
        if (count($this->_toBeDelete) > 0) {
            foreach ($this->_toBeDelete as $catName => $keys) {
                $params = [];
                $i = 0;
                foreach ($keys as $v) {
                    if (isset ($this->_toBeSave [$catName] [$v]))
                        unset ($this->_toBeSave [$catName] [$v]);
                    $params [':p' . $i] = $v;
                    ++$i;
                }
                $names = implode(',', array_keys($params));
                $command = Yii::$app->getDb()->createCommand(
                    "DELETE FROM {{%" . $this->settingTable . "}} WHERE [[category]]=:cat AND [[key]] IN ($names)", [
                    ':cat' => $catName
                ]);
                foreach ($params as $key => $value)
                    $command->bindParam($key, $value);
                $command->execute();
                $this->_cacheFlush [] = $catName;
            }
        }
        /** @FIXME: Switch to batch mode... * */
        if (count($this->_toBeSave) > 0) {
            foreach ($this->_toBeSave as $catName => $keyValues) {
                foreach ($keyValues as $k => $v)
                    $this->addDbItem($catName, $k, $v);
                $this->_cacheFlush [] = $catName;
            }
        }
        if (count($this->_cacheFlush) > 0) {
            foreach ($this->_cacheFlush as $catName)
                Yii::$app->cache->delete(static::getCacheKey($catName));
        }
    }
}