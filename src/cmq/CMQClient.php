<?php

namespace Oasis\Mlib\TencentWrappers\Cmq;

use Exception;

class CMQClient
{

    private $host;
    private $secretId;
    private $secretKey;
    private $version;
    private $http;
    private $method;
    private $URISEC = '/v2/index.php';

    private $sign_method;

    public function __construct($host, $secretId, $secretKey, $version = "SDK_PHP_1.3", $method = "POST")
    {

        try {
            $this->process_host($host);
        } catch (Exception $e) {
        }
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->version = $version;
        $this->method = $method;
        $this->sign_method = 'HmacSHA1';
        $this->http = new CMQHttp($host);
    }

    protected function process_host($host)
    {

        if (strpos($host, "http://") === 0) {
            $_host = substr($host, 7, strlen($host) - 7);
        } elseif (strpos($host, "https://") === 0) {
            $_host = substr($host, 8, strlen($host) - 8);
        } else {
            throw new Exception("Only support http(s) prototol. Invalid endpoint:" . $host);
        }
        if ($_host[strlen($_host) - 1] == "/") {
            $this->host = substr($_host, 0, strlen($_host) - 1);
        } else {
            $this->host = $_host;
        }
    }

    public function set_sign_method($sign_method = 'sha1')
    {

        if ($sign_method == 'sha1' || $sign_method == 'HmacSHA256')
            $this->sign_method = 'HmacSHA1';
        elseif ($sign_method == 'sha256')
            $this->sign_method = 'HmacSHA256';
        else
            throw new Exception('Only support sign method HmasSHA256 or HmacSHA1 . Invalid sign method:'
                . $sign_method);
    }

    public function set_method($method = 'POST')
    {

        $this->method = $method;
    }

    public function set_connection_timeout($connection_timeout)
    {

        $this->http->set_connection_timeout($connection_timeout);
    }

    public function set_keep_alive($keep_alive)
    {

        $this->http->set_keep_alive($keep_alive);
    }

    protected function build_req_inter($action, $params, &$req_inter)
    {

        $_params = $params;
        $_params['Action'] = ucfirst($action);
        $_params['RequestClient'] = $this->version;

        if (!isset($_params['SecretId']))
            $_params['SecretId'] = $this->secretId;

        if (!isset($_params['Nonce']))
            $_params['Nonce'] = rand(1, 65535);

        if (!isset($_params['Timestamp']))
            $_params['Timestamp'] = time();

        if (!isset($_params['SignatureMethod']))
            $_params['SignatureMethod'] = $this->sign_method;

        $plainText = Signature::makeSignPlainText($_params,
            $this->method, $this->host, $req_inter->uri);
        $_params['Signature'] = Signature::sign($plainText, $this->secretKey, $this->sign_method);

        $req_inter->data = http_build_query($_params);
        $this->build_header($req_inter);
    }

    protected function build_header(&$req_inter)
    {

        if ($this->http->is_keep_alive()) {
            $req_inter->header["Connection"] = "Keep-Alive";
        }
    }

    protected function check_status($resp_inter)
    {
        if ($resp_inter->status != 200) {
            throw new Exception($resp_inter->status);
        }
    }

    protected function request($action, $params)
    {

        // make request internal
        $req_inter = new RequestInternal($this->method, $this->URISEC);
        $this->build_req_inter($action, $params, $req_inter);

        $iTimeout = 0;

        if (array_key_exists("UserpollingWaitSeconds", $params)) {
            $iTimeout = (int)$params['UserpollingWaitSeconds'];
        }
        // send request
        $resp_inter = $this->http->send_request($req_inter, $iTimeout);

        return $resp_inter;
    }


    public function get_queue_attributes($params)
    {

        $resp_inter = $this->request('GetQueueAttributes', $params);
        $this->check_status($resp_inter);

        $ret = json_decode($resp_inter->data, true);

        return $ret;
    }

    public function send_message($params)
    {

        $resp_inter = $this->request('SendMessage', $params);
        $this->check_status($resp_inter);

        $ret = json_decode($resp_inter->data, true);

        return $ret['msgId'];
    }

    public function batch_send_message($params)
    {

        $resp_inter = $this->request('BatchSendMessage', $params);
        $this->check_status($resp_inter);

        $ret = json_decode($resp_inter->data, true);

        return $ret['msgList'];
    }

    public function receive_message($params)
    {

        $resp_inter = $this->request('ReceiveMessage', $params);
        $this->check_status($resp_inter);

        $ret = json_decode($resp_inter->data, true);

        return $ret;
    }

    public function batch_receive_message($params)
    {
        $resp_inter = $this->request('BatchReceiveMessage', $params);
        $ret = json_decode($resp_inter->data, true);
        return isset($ret['msgInfoList'])?$ret['msgInfoList']:[];
    }

    public function delete_message($params)
    {

        $resp_inter = $this->request('DeleteMessage', $params);
        $this->check_status($resp_inter);
    }

    public function batch_delete_message($params)
    {
        $this->request('BatchDeleteMessage', $params);
    }


}
