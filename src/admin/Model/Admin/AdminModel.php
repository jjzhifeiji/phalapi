<?php

namespace Admin\Model\Admin;

use Admin\Common\BaseModel;

class AdminModel extends BaseModel
{

    public function getAdminList( $page, $limit)
    {
        $data = $this->getORM()
            ->limit($limit * ($page - 1), $limit)
            ->order('id desc')
            ->fetchAll();
        $total = $this->getORM()->count();
        $res = array(
            "data" => $data,
            "total" => $total,
            "page" => $page,
            "limit" => $limit
        );
        return $res;
    }

    protected function getTableName($id)
    {
        return 'admin';
    }

    public function getAdminAccount($account)
    {
        return $this->getORM()->select('*')->where('account', $account)->where('status', 1)->fetchOne();
    }

    public function getAdminId($id)
    {
        return $this->getORM()->where('id', $id)->fetchOne();
    }

}
