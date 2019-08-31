<?php

namespace app\index\controller;


use think\Controller;

class IndexController extends Controller
{

	public function index(){
		phpinfo();
	}
	
	public function test(){
		dump('访问成功');
	}
}
