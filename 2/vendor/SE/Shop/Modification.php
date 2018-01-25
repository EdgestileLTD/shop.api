<?php

namespace SE\Shop;
use SE\DB;

// модификация
class Modification extends Base
{
    protected $tableName = "shop_modifications_group";
    protected $sortBy = "sort";

    // получить натройки
	protected function getSettingsFetch()
	{
		return array(
			"select" => 'smg.*, 
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sgf.id, sf.name, sf.type)) SEPARATOR "\n") `values`',
			"joins" => array(
				array(
					"type" => "left",
					"table" => 'shop_group_feature sgf',
					"condition" => 'smg.id = sgf.id_group'
				),
				array(
					"type" => "left",
					"table" => 'shop_feature sf',
					"condition" => 'sf.id = sgf.id_feature'
				),
				array(
					"type" => "left",
					"table" => 'shop_modifications sm',
					"condition" => 'sm.id_mod_group = smg.id'
				)
			)
		);
	}

	// правильные значения перед извлечением
	protected function correctItemsBeforeFetch($items = array())
	{
		$result = array();
		foreach ($items as $item) {
			if (!empty($item['values'])) {
				$values = array();
				$params = explode("\n", $item['values']);
				foreach ($params as $itemParam) {
					$itemParam = explode("\t", $itemParam);
					$value = array();
					$value['id'] = $itemParam[0];
					$value['idGroup'] = $itemParam[1];
					$value['name'] = $itemParam[2];
					$value['type'] = $itemParam[3];
					$values[] = $value;
				}
				$item['columns'] = $values;
			}
			$result[] = $item;
		}
		return $result;
	}

	// получить значения
	private function getValues()
	{
		$u = new DB('shop_feature', 'sf');
		$u->select('sf.*');
		$u->leftJoin('shop_group_feature sgf', 'sgf.id_feature = sf.id');
		$u->where("sgf.id_group = ?", $this->input['id']);
		return $u->getList();
	}

	// добавить полученную информацию
	protected function getAddInfo()
	{
		return array("values" => $this->getValues());
	}

	// сохранить значения
	private function saveValues()
	{
		try {
			DB::saveManyToMany($this->input["id"], $this->input["values"],
				array("table" => "shop_group_feature", "key" => "id_group", "link" => "id_feature"));
		} catch (Exception $e) {
			$this->error = "Не удаётся сохранить элементы группы!";
			throw new Exception($this->error);
		}
	}

	// сохранить добавленную информацию
	protected function saveAddInfo()
	{
		$this->saveValues();
		return true;
	}

}
