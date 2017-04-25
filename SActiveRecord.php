<?php

namespace vetal06\scalarRelation;

use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;

/**
 * Переопределение ActiveRecord для скалярных связей
 * Скалярной связью будем называть количество сгруппированное по некоему параметру.
 * Например:
 * Есть список пользователей. У каждого пользователя есть количество активных заказов и отмененных. Нам нужно в списке пользователей вывести активные и отмененные заказы.
 * Использование:
 * В моделе, унаследованной от данного класса, прописываем связь, где задаем необходимые условия и поля для группировки (@see self::hasScalar()). Например:
 * <pre>
 * public function getActiveBuyerCount()
  {
    return $this->hasScalar(__FUNCTION__, ['fk_user_id', 'fksm_filial_id'])
        ->select(['count(*)'])
        ->innerJoin('buyer', 'filial_user.fk_user_id = buyer.rieltor_user_id')
        ->where([
            'buyer.archive_flag' => Buyer::ACTIVE_FLAG
        ]);
  }
 * </pre>
 * 
 * @package frontend\components\scalarRelation
 */
class SActiveRecord extends ActiveRecord
{

  /**
   * Массив данных в который будут складываться искомые скалярные величины
   * @var array
   */
  private $scalarValues = [];

  /**
   * Переопределение метода для возвращения собственного ActiveQuery
   * 
   * @see http://www.yiiframework.com/doc-2.0/yii-db-activerecord.html#find()-detail
   * @overriding
   * @return object
   * @throws \yii\base\InvalidConfigException
   */
  public static function find()
  {
    return Yii::createObject(SActiveQuery::className(), [get_called_class()]);
  }

  /**
   * Объявление связи, установка аттрибутов для группировки, возврат объекта SActiveQuery для просчета необходимых параметров
   * Если аттрибуты для группировки пустые - группировка производится по первичному ключу
   *
   * Для вызова просчитанного результата используется $this->getScalarRelation("getMethodName"), где getMethodName соответствующий геттер в моделе наследнике
   *
   * @param string $method название метода от которого вызывается hasScalar()
   * @param array $groupAttributes аттрибуты модели по которым производится группировка
   * @return frontend\components\scalarRelation\SActiveQuery
   * @throws Exception
   */
  public function hasScalar($method, $groupAttributes = null)
  {
    if ($groupAttributes == null) {
      $groupAttributes = $this->primaryKey();
    }
    if (!method_exists($this, $method)) {
      throw new Exception("Add method {$method} in " . self::className());
    }
    $query = self::find();
    $query->setGroupAttributes($groupAttributes);
    return $query;
  }

  /**
   * Получение значения скалярной связи
   * 
   * @param string $methodName название связи (геттера) установленной как метод в моделе наследнике
   * @return string | null | false @see yii\db\Query::scalar()
   */
  public function getScalarRelation($methodName)
  {
    if (!isset($this->scalarValues[$methodName])) {
      $query = call_user_func([$this, $methodName]);
      $primaryKeys = $this->primaryKey();
      $condition = [];
      foreach ($this->getAttributes($primaryKeys) as $attr => $value) {
        $condition[$this->tableName().'.'.$attr] = $value;
      }
      $query->andWhere($condition);
      if (!$query instanceof SActiveQuery) {
        throw new Exception("Method {$methodName} must be instance of SActiveQuery");
      }
      $this->scalarValues[$methodName] = $query->scalar();
    }
    return $this->scalarValues[$methodName];
  }

  /**
   * Присваивание результата выполнения скалярной величины
   * Вызывается в frontend\components\scalarRelation\SActiveQuery::setRelationScalarDataForModel()
   * 
   * @param $methodName название связи (геттера) установленной как метод в моделе наследнике
   * @param null
   */
  public function setScalarRelation($methodName, $value)
  {
    $this->scalarValues[$methodName] = $value;
  }

}
