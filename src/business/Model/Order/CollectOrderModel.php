<?php

namespace Business\Model\Order;

use Business\Common\BaseModel;

class CollectOrderModel extends BaseModel
{

    public function getCollectOrderList(array $file, $offset, $limit)
    {
        $data = $this->getORM()->where($file)->limit($limit * ($offset - 1), $limit)->fetchAll();
        $allnum = $this->getORM()->where($file)->count();
        $res = array(
            "data" => $data,
            "allnum" => $allnum,
            "offset" => $offset,
            "limit" => $limit
        );
        return $res;
    }

    public function getCollectOrder($id)
    {
        $file = array(
            'id' => $id
        );
        return $this->getORM()->where($file)->fetchOne();
    }

    protected function getTableName($id)
    {
        return 'collect_order';
    }

}