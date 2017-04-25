<?php

namespace vetal06\scalarrelation;

use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;

/**
 * Класс ActiveQuery для получения скалярных связей
 * После отработки self::populate() происходит заполнение SActiveRecord данными с результатами связей withScalar(), классов которые наследуются от SActiveRecord
 * 
 * @package frontend\components\scalarRelation
 */
class SActiveQuery extends ActiveQuery
{

  /**
   * Массив связей с которым работает данный объект query
   * @see yii\db\ActiveQueryTrait::with
   * @var array
   */
  private $withScalarData;

  /**
   * Атрибуты модели по которым будет происходить групировка и идентификация модели
   * @var array
   */
  private $groupAttributes;

  /**
   * Данные моделей групирируемых аттрибутов
   * @var
   */
  private $groupAttributesModelsData;

  /**
   * Метод аналог обычного with для скалярных связей
   * @see yii\db\ActiveQueryTrait::with()
   * @param array $data массив связей
   */
  public function withScalar(array $data)
  {
    $this->withScalarData = $data;
    return $this;
  }

  /**
   * Установка аттрибутов для групировки для скалярных связей
   * @param array $attributes массив аттрибутов для группировки
   * @throws Exception
   */
  public function setGroupAttributes(array $attributes)
  {
    if (empty($attributes)) {
      throw new Exception('Group attributes for relation not found');
    }
    $this->groupAttributes = $attributes;
  }

  /**
   * Переопределение метода который возвращает данные после выполнения запроса
   * @param array $rows
   */
  public function populate($rows)
  {
    $models = parent::populate($rows);
    if (!empty($models) && $models[0] instanceof SActiveRecord) {
      $this->scalarRelation($models);
    }

    return $models;
  }

  /**
   * Логика получения и присваивания данных скалярной связи
   * 
   * @param array $models результирующий набор моделей
   * @return void
   * @throws Exception
   */
  private function scalarRelation($models)
  {
    if ($this->withScalarData != null && $models != null) {
      $model = $models[0];

      foreach ($this->withScalarData as $relationName) {
        $relationsQuery = $this->getScalarRelationQuery($model, $relationName);
        $groupAttributes = $relationsQuery->getGroupAttributes();
        $select = $groupAttributes;
        $select[$relationName] = array_shift($relationsQuery->select);
        $relationsQuery->select($select);
        if ($relationsQuery->groupBy != null) {
          $groupAttributes = array_merge($relationsQuery->groupBy, $groupAttributes);
        }
        $relationsQuery->groupBy($groupAttributes);

        $this->setRelationScalarDataForModel($models, $relationsQuery, $relationName);
      }
    }
  }

  /**
   * Получение SActiveQuery для скалярной связи
   * 
   * @param SActiveRecord $model интстенс модели с набором данных
   * @param string $relationName название связи (геттера) установленной как метод в моделе унаследованной от SActiveRecord
   * 
   * @return SActiveQuery объект запроса для нахождение значения по параметру, который группируется
   * 
   * @throws Exception
   */
  private function getScalarRelationQuery(SActiveRecord $model, $relationName)
  {
    $methodName = $relationName;
    if (!method_exists($model, $methodName)) {
      $modelClass = get_class($model);
      throw new Exception("Method {$methodName} for relation {$relationName} not found in {$modelClass}");
    }
    $query = call_user_func([$model, $methodName]);
    if (!$query instanceof SActiveQuery) {
      throw new Exception('Relation not instanceof class SActiveQuery');
    }
    if ($query->select == null || count($query->select) > 1) {
      throw new Exception('Set only one select in you relation SActiveQuery');
    }

    return $query;
  }

  /**
   * Установка скалярных данных для каждой модели результата моиска
   * @param array $models результирующий набор моделей
   * @param SActiveQuery $relationsQuery объект запроса для нахождения значения по параметру, который группируется
   * @param string $relationName название связи
   */
  private function setRelationScalarDataForModel($models, $relationsQuery, $relationName)
  {
    foreach ($relationsQuery->getGroupAttributes() as $attr) {
      if (!isset($this->groupAttributesModelsData[$attr])) {
        $this->groupAttributesModelsData[$attr] = ArrayHelper::getColumn($models, preg_replace('/^.+\./', '', $attr));
      }
      if (!empty($this->groupAttributesModelsData[$attr])) {
        $relationsQuery->andWhere([$attr => array_unique($this->groupAttributesModelsData[$attr])]);
      }
    }

    $data = $relationsQuery->asArray()->all();

    foreach ($models as $model) {
      $find = !empty($data);
      foreach ($data as $row) {
        $find = true;
        foreach ($row as $attribute => $val) {
          if (isset($model->{$attribute}) && $model->{$attribute} != $val) {
            $find = false;
            break;
          }
        }
        if ($find)
          break;
      }
      if ($find) {
        $relationValue = $row[$relationName];
        $model->setScalarRelation($relationName, $relationValue);
      } else {
        $model->setScalarRelation($relationName, 0);
      }
    }
  }

  /**
   * Получение аттрибутов групировки для скалярных связей вида tableName.fieldName
   * @return array аттрибуты для группировки вида ['filial_user.fk_user_id', 'filial_user.fk_user_id2']
   */
  private function getGroupAttributes()
  {
    $res = [];
    $modelClass = $this->modelClass;
    $tableName = $modelClass::tableName();
    foreach ($this->groupAttributes as $attr) {
      $res[] = "{$tableName}.{$attr}";
    }
    return $res;
  }

}
