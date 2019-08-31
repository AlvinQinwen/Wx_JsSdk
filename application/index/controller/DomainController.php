<?php


namespace app\index\controller;


use app\index\model\Domain;
use think\Cache;
use think\Db;
use think\Request;
use think\Controller;

class DomainController extends Controller
{

    //入口第一次请求
    public function firstRequest(Request $request)
    {

        if ($request->isPost()){
            //接收入口出的传递过来的SID
            $sid = $request->only('sid');
            if (empty($sid)){
                return json(['msg'=>'请传递sid'],405);
            }
            //转换为string
            $sid = $sid['sid'];

            //先从数据库中查询到可用的domain
            $datas = Db::table('domain')->where(['status'=>1])->select();
            $domain = [];
            foreach ($datas as $k=>$data){
                $domain[$k]['domain'] = $data['domain'];
            }
            $item = array_rand($domain);
//            dump($domain[$item]['domain']);
//            die();

            $url = $domain[$item]['domain'];    //随机出来的url
            $url  = $url.'/'.$sid;

            //将Domain 与 SID 拼接在一起返回给入口的同时将其存入redis
            Cache::remember('domain',$url);

            //拼接domain + SID 两个形成url返回给前端
            return json(['msg'=>'SID生成成功','url'=>$url],200);


        }
        return json(['msg' => '请使用post请求方式'],405);

    }

    //入口文件第二次请求 主要用作验证
    public function secondRequest(Request $request)
    {
        if ($request->isPost()){
            //从redis中查询到第一次请求存入的SID 和 Domain 拼接的数据

            $url = $request->only('url');
            $url = $url['url'];
            if (Cache::has('domain')){
//              dump(Cache::get('domain') === $url);
//              die();
                if ($url === Cache::get('domain')){
                    //对比成功 -> 删除redis中的数据
                    Cache::rm('domain');
                    //跳转到内容页面

                    return "<script>alert('跳转到内容页面了')</script>";
                }
                //对比失败 没有从redis中找到这个domain让这个url跳转到其他页面
                return "<script>alert('跳转到其余页面了')</script>";

            }
            return json(['msg'=>'redis中不存在domain数据'],400);

        }
    }

    //展示
    public function show()
    {
        $data = Domain::all()->toArray();
        if (!empty($data)){
            return json(['msg'=>'查询成功','data'=>$data],200);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $domain = new Domain();
        $where = input('post.');
        if (empty($where)){
            return json(['msg'=>'参数错误，没有传递id'],405);
        }
        $result = $domain->where($where)->delete();
        if ($result){
            return json(['msg'=>'删除成功']);
        }
        return json(['msg'=>'删除失败']);
    }
    //编辑
    public function edit(Request $request)
    {
      if ($request->isPost()){

          $domainModel = new Domain();
          $id = input('id');
          $domain = input('domain');
          $status = input('status');
          if (empty($status)){
              $status = 1;
          }
          if (empty($id) || empty($domain)){
              return json(['msg'=>'参数缺失，请核对参数'],400);
          }
          $data = ['id'=>$id,'domain'=>$domain,'status'=>$status];
          $result = $domainModel->isUpdate(true)->allowField(true)->save($data);

          if ($result){
              return json(['msg'=>'更新数据成功'],200);
          }
          return json(['msg'=>'更新数据失败'],400);
      }
      return json(['msg'=>'请使用post请求'],400);
    }
    //添加
    public function add(Request $request)
    {
        if ($request->isPost()) {
            $domainModel = new Domain();
            $data = input('post.');
            $status = input('status');
            if (empty($status)){
                //默认为可用domain
                $status = 1;
            }
//            dump($data);
//            die();
            if (empty($data)){
                return json(['msg'=>'请传递插入数据'],405);
            }
            $result = $domainModel->allowField(true)->save($data);

            if ($result){
                return json(['msg'=>'添加数据成功'],201);
            }
            return json(['msg'=>'添加数据失败'],400);
        }
        return json(['msg'=>'请使用post方式请求'],400);
    }

    public function getAccessToken()
    {
        $http = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".config('wx.app_id')."&secret=".config('wx.secret');
        $result = json_decode(file_get_contents($http));

        $jsApi = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$result->access_token.'&type=jsapi';
        $ticket = json_decode(file_get_contents($jsApi));
        $ticket = $ticket->ticket;
//        dump($ticket);
//        die();

        $timestamp = time();
        $nonceStr = $this->createNonceStr();


        // 注意 URL 建议动态获取(也可以写死).
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; // 调用JSSDK的页面地址
        //$url = $_SERVER['HTTP_REFERER']; // 前后端分离的, 获取请求地址(此值不准确时可以通过其他方式解决)
        $str = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $sha_str = sha1($str);
//        dump(config('wx.app_id'));
//        dump($nonceStr);
//        dump($timestamp);
//        dump($sha_str);
//        die();
        $signPackage = [
            "appId"     => config('wx.app_id'),
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "signature" => $sha_str,
        ];
//        dump($signPackage);
//        die();

        return $signPackage;


    }

    //随机字符串
    public function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function view()
    {
        $wxConfig = $this->getAccessToken();

        $this->assign('wxConfig',$wxConfig);

        return $this->fetch();
    }
}
