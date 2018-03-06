<?php

namespace SE\Shop;

use SE\DB as seTable;
use SE\Exception;

// ВЕСА (получение единиц веса из БД)
class Measure extends Base {

    // получить веса/объемы
    public function info($id = null)
    {
        $result['weights'] = $this->getWeights();
        $result['volumes'] = $this->getVolumes();

        $this->result = $result;

        return $result;
    }

    // получить веса
    private function getWeights()
    {
        try {
            $u = new seTable('shop_measure_weight', 'smw');
            $u->select('smw.*');
            return $u->getList();
        } catch (Exception $e) {
            $this->result = "Не удаётся получить список мер!";
        }
    }

    // получить объемы
    private function getVolumes()
    {
        try {

            $u = new seTable('shop_measure_volume', 'smv');
            $u->select('smv.*');
            return $u->getList();
        } catch (Exception $e) {
            $this->result = "Не удаётся получить список мер!";
        }
    }


}
