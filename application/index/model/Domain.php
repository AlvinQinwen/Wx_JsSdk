<?php


namespace app\index\model;


use think\Model;

class Domain extends Model
{
    protected $autoWriteTimestamp=false;
    protected $pk = 'id';
    // 设置当前模型对应的完整数据表名称
    protected $table = 'domain';
}