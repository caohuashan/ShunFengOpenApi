<?php
    class Service_Shunfeng{

    //域名配置
    protected $domian;

    //配置Id
    protected $sf_appid;

    //配置key
    protected $sf_appkey;

    //access_token
    protected  $access_token;

    //月结CUSTID
    protected $cust_id = '';

//    注意事项：接口数据的日志记录操作；access_token的数据缓存操作；底层函数返回结果封装；
    public function __construct()
    {
        $shunfeng = 
        array(
            'domian'=>'url',//顺丰接口链接
            'appid'=>'appid',//申请的顺丰的appid
            'appkey'=>'appkey',//申请的顺丰的appkey
            'custid'=>'custid',//申请的顺丰月结卡号
        );
        if(!$shunfeng){
            exit('shunfeng config error');
        }
        $this->domian = $shunfeng['domian'];
        $this->sf_appid = $shunfeng['appid'];
        $this->sf_appkey = $shunfeng['appkey'];
        $this->cust_id = $shunfeng['custid'];

        //先查询缓存是否有该值，没有则查询接口，若查询接口失败，则申请信息的并保存在缓存中
        $this->access_token = $this->redis->get(md5($this->sf_appid));//redis缓存access_token，没有redis的可以写入文件
        if(!$this->access_token){
            $this->access_token = $this->applyAccessToken();
            if(!$this->access_token){

                $this->access_token = $this->queryAccessToken();
                if(!$this->access_token){
                    return false;
                }
                $this->redis->SETEX(md5($this->sf_appid), 3000, $this->access_token);

            }
            $this->redis->SETEX(md5($this->sf_appid), 3000, $this->access_token);
        }
    }
    //申请accessToken
    public function applyAccessToken(){
        $baseUrl = '/public/v1.0/security/access_token/';
        $url = $this->getUrl($baseUrl);
        $body = array();
        $transType = 301;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        return $res['accessToken'];
    }

    //查询accessToken 需要有缓存
    public function queryAccessToken(){
        $baseUrl = '/public/v1.0/security/access_token/query/';

        $url = $this->getUrl($baseUrl);
        $body = array();
        $transType = 300;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        return $res['accessToken'];
    }

    //快速下单order
    public function placeOrder($datas){
        $baseUrl = '/rest/v1.0/order/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);
        $body = array();

        //必传参数校验
        $params = array(
            'cargo'=>'',//* 商品名称
            'insurePrice'=>'',

            'consiProvince'=>'',//*到件方所在省份 “省”不能省略
            'consiCity'=>'',//* 到件方所属城市名称， “市”字不能省略
            'consiCounty'=>'',//* 到件方所在县区
            'consiAddress'=>'',//* 到件方详细地址
            'consiCompany'=>'',//* 到件方公司名称
            'consiContact'=>'',//* 到件方联系人
            'consiTel'=>'',//*到件方联系电话

            'deliverProvince'=>'',//*寄件方省份 “省”字不能省略
            'deliverCity'=>'',//*寄件方所属城市名称，“市”不能省略
            'deliverCounty'=>'',//*寄件方所在县区 “区字”不能省略
            'deliverAddress'=>'',//*寄件方详细地址
            'deliverContact'=>'',//*寄件方联系人
            'deliverTel'=>'',//*寄件方手机

            'expressType'=>'',//*1标准快件（基本都是）；2顺丰特惠；3电商特惠；5顺丰次晨；6顺丰即日；7电商速配；15生鲜速配
            'orderId'=>'',//*客户订单号，最大长度为56，不允许重复提交
            'payMethod'=>'',//*付款方式  1寄方付款、寄方月结（默认）； 2收方付；3第三方月结卡号支付
            'remark'=>'',//备注 最大长度为30个汉字
        );

        //参数值校验
        foreach($params as $k=>$v){
            $param = $datas[$k];
            if(!$param){
                if($k == 'remark'){
                    continue;
                }

                if($k == 'insurePrice'){
                    continue;
                }
                return 'param '.$k.' missing';
            }
            $params[$k] = $param;
        }

        if($params['insurePrice']){
            $body['addedServices'][0]['name']   = 'INSURE';//保价
            $body['addedServices'][0]['value']  = $params['insurePrice'];
        }

        $body['cargoInfo']['cargo']         = $params['cargo'];//* 商品名称
        $body['consigneeInfo']['province']  = $params['consiProvince'];
        $body['consigneeInfo']['city']      = $params['consiCity'];
        $body['consigneeInfo']['county']    = $params['consiCounty'];
        $body['consigneeInfo']['address']   = $params['consiAddress'];
        $body['consigneeInfo']['company']   = $params['consiCompany'];
        $body['consigneeInfo']['contact']   = $params['deliverContact'];
        $body['consigneeInfo']['tel']       = $params['consiTel'];

        $body['deliverInfo']['province']    = $params['deliverProvince'];
        $body['deliverInfo']['city']        = $params['deliverCity'];
        $body['deliverInfo']['county']      = $params['deliverCounty'];
        $body['deliverInfo']['address']     = $params['deliverAddress'];
        $body['deliverInfo']['contact']     = $params['deliverContact'];
        $body['deliverInfo']['tel']         = $params['deliverTel'];

        $body['custId']       = $this->cust_id;
        $body['expressType']  = $params['expressType'];
        $body['orderId']      = $params['orderId'];
        $body['payMethod']    = $params['payMethod'];
        $body['remark']       = $params['remark'];
        //是否通知快递员：0不通知（默认）、1通知
        if( isset($datas['isDocall']) && $datas['isDocall']){
            $body['isDocall'] = 1;
        }

        $transType = 200;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        if( isset($res['orderId']) && ($res['orderId'] == $datas['orderId']) ){
            return true;
        }else{
            return false;
        }
    }

    //订单筛选接口(提供接口查询客户的订单是否在顺丰的收派范围内)
    public function orderFilter(){
        $baseUrl = '/rest/v1.0/filter/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);

        $body['consigneeProvince'] = trim($this->getPost('consigneeProvince'));
        if(!$body['consigneeProvince']){
            return 'param consigneeProvince missing';//省
        }

        $body['consigneeCity'] = trim($this->getPost('consigneeCity'));
        if(!$body['consigneeCity']){
            return 'param consigneeCity missing';//市
        }

        $body['consigneeCounty'] = trim($this->getPost('consigneeCounty'));
        if(!$body['consigneeCounty']){
            return 'param consigneeCounty missing';//区
        }

        $body['consigneeAddress'] = trim($this->getPost('consigneeAddress'));
        if(!$body['consigneeAddress']){
            return 'param consigneeAddress missing';//详细地址
        }

        $body['consigneeTel'] = trim($this->getPost('consigneeTel'));//电话
        if(!$body['consigneeTel']){
            return 'param consigneeTel missing';
        }

        $body['consigneeCountry'] = "中华人民共和国";//国家
        $body['filterType'] = 1;
        $transType = 204;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        return $res;
    }

    //路由查询接口
    public function queryRoute($trackingNumber){
        $baseUrl = '/rest/v1.0/route/query/access_token/'.$this->access_token.'/';

        $url = $this->getUrl($baseUrl);
        $body['trackingNumber'] = trim($trackingNumber);
        if(!$body['trackingNumber']){
            return 'param trackingNumber missing';
        }
        $body['trackingType'] = 1;
        $body['methodType'] = 2;

        $transType = 501;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        return $res;
    }

    //路由增量信息申请接口
    public function applyRouteIncrement(){
        $baseUrl = '/rest/v1.0/route/push/apply/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);
        $body['orderId'] = trim($this->getPost('orderId'));
        if(!$body['orderId']){
            return 'param orderId missing';
        }

        $body['mailNo'] = trim($this->getPost('mailNo'));
        if(!$body['mailNo']){
            return 'param mailNo missing';
        }
        $body['status'] = 1;

        $transType = 503;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return 'request fail';
        }
        return $res;
    }

    //路由增量查询
    public function queryRouteIncrement(){
        $baseUrl = '/rest/v1.0/route/inc/query/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);
        $body['orderId'] = trim($this->getPost('orderId'));
        if(!$body['orderId']){
            return 'param orderId missing';
        }
        $transType = 504;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        return $res;
    }

    //订单结果查询接口
    public function queryOrderResult($expressOrderId){
        $baseUrl = '/rest/v1.0/order/query/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);
        $body['orderId'] = $expressOrderId;
        if(!$body['orderId']){
            return 'param orderId missing';
        }
        $transType = 203;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        if( isset($res['filterResult']) && $res['filterResult'] == 3){
            return false;
        }
        if( isset($res['mailNo']) && $res['mailNo']){
            return $res; //处理结果 如果不可达，则返回错误信息
        }else{
            return false;
        }
    }

    //电子运单图片下载接口
    public function downloadExpressImg(){
        $baseUrl = '/rest/v1.0/waybill/image/access_token/'.$this->access_token.'/';
        $url = $this->getUrl($baseUrl);
        $body['orderId'] = trim($this->getPost('orderId'));
        if(!$body['orderId']){
            return 'param orderId missing';
        }
        $transType = 205;
        $res = $this->dealRequest($url, $body, $transType);
        if(!$res){
            return false;
        }
        $images = array();
        if( isset($res['images']) && is_array($res['images']) ){
            foreach($res['images'] as $k=>$v){
                if($v){
                    $images[] = 'data:image/png;base64,'.$v;
                }
            }
        }
        if(empty($images)){
            return 'getimg fail';
        }else{
            return $images;
        }
    }

    //封装请求url
    public function getUrl($baseUrl){
        $url = $this->domian.$baseUrl.'sf_appid/'.$this->sf_appid.'/sf_appkey/'.$this->sf_appkey;
        return $url;
    }

    //生成TransMessageId
    public function getTransMessageId(){
        return date('YmdHis').rand(10000,99999);
    }

    //处理请求数据和请求结果数据
    public function dealRequest($url, $body, $transType){
        if(!$transType){
            return array();
        }
        $header['transType'] = $transType;
        $header['transMessageId'] = $this->getTransMessageId();
        $data['head'] = $header;
        if($body){
            $data['body'] = $body;
        }
        $res = json_decode($this->doCurl($url,json_encode($data)), true);
        $info = array(
            'transType'=>$transType,
            'createTime'=>date('Y-m-d H:i:s'),
            'params'=> json_encode($data),
            'url'=>$url
        );
        if( isset($res['head']['code']) && $res['head']['code'] == 'EX_CODE_OPENAPI_0200'){
            $info['state'] = 1;
            $info['result'] = json_encode($res['body']);
            $this->db->insert('express_api_logs', $info);
            return $res['body'];
        }else{
            //将错误请求写入日志 没有响应的sql,可以删除写入操作
            $info['state'] = 0;
            $info['result'] = json_encode($res['body']);
            $this->db->insert('express_api_logs', $info);
            return false;
        }
    }

    //发送curl请求
    public function doCurl($url,$data){
        $ch=curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER,1);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch,CURLOPT_POST, true);
        $header = $this->FormatHeader($url,$data);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_HEADER, 0);//返回response头部信息
        $rinfo = curl_exec($ch);
        return $rinfo;
    }


    //封装header头
    public function FormatHeader($url,$data){
        $temp = parse_url($url);
        $query = isset($temp['query']) ? $temp['query'] : '';
        $path = isset($temp['path']) ? $temp['path'] : '/';
        $header = array (
            "POST {$path}?{$query} HTTP/1.1",
            "Host: {$temp['host']}",
            "Content-Type: application/json",
            "Content-length: ".strlen($data),
            "Connection: Close"
        );
        return $header;
    }
}

