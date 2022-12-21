<?php

namespace Oasis\Mlib\TecentWrappers;

use Exception;
use InvalidArgumentException;
use Oasis\Mlib\AwsWrappers\Contracts\QueueInterface;


class TencentCmqQueue implements QueueInterface
{

    const SERIALIZATION_FLAG = '_serialization';
    /**
     * @var Account
     */
    protected $client;

    /**
     * @var Queue
     */
    protected $queue;

    public function __construct($accessId, $accessKey, $endPoint, $name)
    {

        $account = new Account($endPoint, $accessId, $accessKey);
        $this->queue = $account->get_queue($name);
    }

    public function sendMessage($payroll, $delay = 0, $attributes = [])
    {

        $sentMessages = $this->sendMessages([$payroll], $delay, $attributes);
        if (!$sentMessages) {
            return false;
        } else {
            return $sentMessages[0];
        }
    }

    public function sendMessages(array $payrolls, $delay = 0, array $attributesList = [], $concurrency = 10)
    {

        $messages = [];
        foreach ($payrolls as $payroll) {
            if (!is_string($payroll)) {
                $payroll = json_encode(
                    [
                        self::SERIALIZATION_FLAG => 'base64_serialize',
                        "body" => base64_encode(serialize($payroll)),
                    ]
                );
            }
            $messages[] = new Message($payroll);
        }

        return $this->queue->batch_send_message($messages);
    }

    /**
     * @param null $wait
     * @param null $visibility_timeout
     * @param array $metas
     * @param array $message_attributes
     *
     * @return Message|null
     */
    public function receiveMessage($wait = null, $visibility_timeout = null, $metas = [], $message_attributes = [])
    {

        $ret = $this->receiveMessageBatch(1, $wait);
        if (!$ret) {
            return null;
        } else {
            return $ret[0];
        }
    }

    /**
     * @param      $max_count
     * @param null $wait
     *
     * @return Message[]
     */
    public function receiveMessages($max_count, $wait = null)
    {

        if ($max_count <= 0) {
            return [];
        }

        $buffer = [];
        $one_batch = 10;
        while ($msgs = $this->receiveMessageBatch(
            $one_batch,
            $wait
        )) {
            $buffer = array_merge($buffer, $msgs);
            $one_batch = min(10, $max_count - count($buffer));
            if ($one_batch <= 0) {
                break;
            }
        }

        return $buffer;
    }

    public function deleteMessage($msg)
    {

        $this->deleteMessages([$msg]);
    }

    /**
     * @param Message[] $messages
     */
    public function deleteMessages($messages)
    {

        $total = count($messages);
        if (!$total) {
            return;
        }

        $buffer = [];
        foreach ($messages as $msg) {
            $buffer[] = $msg->receiptHandle;
            if (count($buffer) >= 10) {
                $this->batchDeleteMessage($buffer);
                $buffer = [];
            }
        }
        if ($buffer) {
            $this->batchDeleteMessage($buffer);
        }
    }

    /**
     * @param $buffer
     */
    private function batchDeleteMessage($buffer)
    {

        $this->queue->batch_delete_message($buffer);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getAttribute($name)
    {

        $result = $this->getAttributes([$name]);

        return $result[$name];
    }

    /**
     * @param array $attributeNames
     *
     * @return array
     */
    public function getAttributes(array $attributeNames)
    {

        $queueAttributes = $this->queue->get_attributes();
        $attributes = [
            'ApproximateNumberOfMessages' => $queueAttributes->activeMsgNum,
        ];

        $array = [];
        foreach ($attributeNames as $name) {
            if (isset($attributes[$name])) {
                $array[$name] = $attributes[$name];
            }
        }

        return $array;
    }

    /**
     * @param int $maxCount
     * @param null $wait
     *
     * @return Message[] array
     */
    protected function receiveMessageBatch($maxCount = 1, $wait = null)
    {

        if ($maxCount > 10 || $maxCount < 1) {
            throw new InvalidArgumentException("Max count for queue message receiving is 10");
        }

        return $this->queue->batch_receive_message($maxCount, $wait);
    }
}

class Account
{

    private $secretId;
    private $secretKey;
    private $cmq_client;

    public function __construct($host, $secretId, $secretKey)
    {

        $this->host = $host;
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->cmq_client = new CMQClient($host, $secretId, $secretKey);
    }

    public function set_sign_method($sign_method = 'sha1')
    {

        $this->cmq_client->set_sign_method($sign_method);
    }


    public function set_client($host, $secretId = null, $secretKey = null)
    {

        if ($secretId == null) {
            $secretId = $this->secretId;
        }
        if ($secretKey == null) {
            $secretKey = $this->secretKey;
        }
        $this->cmq_client = new CMQClient($host, $secretId, $secretKey);
    }

    public function get_client()
    {

        return $this->cmq_client;
    }

    public function get_queue($queue_name)
    {

        return new Queue($queue_name, $this->cmq_client);
    }
}

class CMQClient
{

    private $host;
    private $secretId;
    private $secretKey;
    private $version;
    private $http;
    private $method;
    private $URISEC = '/v2/index.php';

    public function __construct($host, $secretId, $secretKey, $version = "SDK_PHP_1.3", $method = "POST")
    {

        $this->process_host($host);
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
            throw new CMQServerNetworkException($resp_inter->status, $resp_inter->header, $resp_inter->data);
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

        $this->check_status($resp_inter);

        $ret = json_decode($resp_inter->data, true);

        return $ret['msgInfoList'];
    }

    public function delete_message($params)
    {

        $resp_inter = $this->request('DeleteMessage', $params);
        $this->check_status($resp_inter);
    }

    public function batch_delete_message($params)
    {

        $resp_inter = $this->request('BatchDeleteMessage', $params);
        $this->check_status($resp_inter);
    }


}

class CMQHttp
{

    private $connection_timeout;
    private $keep_alive;
    private $host;

    public function __construct($host, $connection_timeout = 10, $keep_alive = true)
    {

        $this->connection_timeout = $connection_timeout;
        $this->keep_alive = $keep_alive;
        $this->host = $host . "" . "/v2/index.php";
        $this->curl = null;
    }

    public function set_method($method = 'POST')
    {

        $this->method = $method;
    }

    public function set_connection_timeout($connection_timeout)
    {

        $this->connection_timeout = $connection_timeout;
    }

    public function set_keep_alive($keep_alive)
    {

        $this->keep_alive = $keep_alive;
    }

    public function is_keep_alive()
    {

        return $this->keep_alive;
    }

    public function send_request($req_inter, $userTimeout)
    {

        if (!$this->keep_alive) {
            $this->curl = curl_init();
        } else {
            if ($this->curl == null)
                $this->curl = curl_init();
        }

        if ($this->curl == null) {
            throw new Exception("Curl init failed");

            return;
        }

        $url = $this->host;
        if ($req_inter->method == 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, 1);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $req_inter->data);
        } else {
            $url .= $req_inter->uri . '?' . $req_inter->data;
        }

        if (isset($req_inter->header)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $req_inter->header);
        }

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->connection_timeout + $userTimeout);

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        if (false !== strpos($url, "https")) {
            // 证书
            // curl_setopt($ch,CURLOPT_CAINFO,"ca.crt");
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $resultStr = curl_exec($this->curl);
        if (curl_errno($this->curl)) {
            throw new Exception(curl_error($this->curl));
        }
        $info = curl_getinfo($this->curl);
        $resp_inter = new ResponseInternal($info['http_code'], null, $resultStr);

        return $resp_inter;
    }
}

class RequestInternal
{

    public $header;
    public $method;
    public $uri;
    public $data;

    public function __construct($method = "", $uri = "", $header = null, $data = "")
    {

        if ($header == null) {
            $header = [];
        }
        $this->method = $method;
        $this->uri = $uri;
        $this->header = $header;
        $this->data = $data;
    }

    public function __toString()
    {

        $info = ["method" => $this->method,
            "uri" => $this->uri,
            "header" => json_encode($this->header),
            "data" => $this->data];

        return json_encode($info);
    }
}

class ResponseInternal
{

    public $header;
    public $status;
    public $data;

    public function __construct($status = 0, $header = null, $data = "")
    {

        if ($header == null) {
            $header = [];
        }
        $this->status = $status;
        $this->header = $header;
        $this->data = $data;
    }

    public function __toString()
    {

        $info = ["status" => $this->status,
            "header" => json_encode($this->header),
            "data" => $this->data];

        return json_encode($info);
    }
}

class CMQExceptionBase extends Exception
{


    public $code;
    public $message;
    public $data;

    public function __construct($message, $code = -1, $data = [])
    {

        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function __toString()
    {

        return "CMQExceptionBase  " . $this->get_info();
    }

    public function get_info()
    {

        $info = ["code" => $this->code,
            "data" => json_encode($this->data),
            "message" => $this->message];

        return json_encode($info);
    }
}

class CMQClientException extends CMQExceptionBase
{

    public function __construct($message, $code = -1, $data = [])
    {

        parent::__construct($message, $code, $data);
    }

    public function __toString()
    {

        return "CMQClientException  " . $this->get_info();
    }
}

class CMQClientNetworkException extends CMQClientException
{

    public function __construct($message, $code = -1, $data = [])
    {

        parent::__construct($message, $code, $data);
    }

    public function __toString()
    {

        return "CMQClientNetworkException  " . $this->get_info();
    }
}

class CMQClientParameterException extends CMQClientException
{

    public function __construct($message, $code = -1, $data = [])
    {

        parent::__construct($message, $code, $data);
    }

    public function __toString()
    {

        return "CMQClientParameterException  " . $this->get_info();
    }
}

class CMQServerNetworkException extends CMQExceptionBase
{

    public $status;
    public $header;
    public $data;

    public function __construct($status = 200, $header = null, $data = "")
    {

        if ($header == null) {
            $header = [];
        }
        $this->status = $status;
        $this->header = $header;
        $this->data = $data;
    }

    public function __toString()
    {

        $info = ["status" => $this->status,
            "header" => json_encode($this->header),
            "data" => $this->data];

        return "CMQServerNetworkException  " . json_encode($info);
    }
}

class CMQServerException extends CMQExceptionBase
{


    public $request_id;

    public function __construct($message, $request_id, $code = -1, $data = [])
    {

        parent::__construct($message, $code, $data);
        $this->request_id = $request_id;
    }

    public function __toString()
    {

        return "CMQServerException  " . $this->get_info() . ", RequestID:" . $this->request_id;
    }
}

class Signature
{

    /**
     * sign
     * 生成签名
     *
     * @param string $srcStr 拼接签名源文字符串
     * @param string $secretKey secretKey
     * @param string $method 请求方法
     *
     * @return
     */
    public static function sign($srcStr, $secretKey, $method = 'HmacSHA1')
    {

        switch ($method) {
            case 'HmacSHA1':
                $retStr = base64_encode(hash_hmac('sha1', $srcStr, $secretKey, true));
                break;
            case 'HmacSHA256':
                $retStr = base64_encode(hash_hmac('sha256', $srcStr, $secretKey, true));
                break;
            default:
                throw new Exception($method . ' is not a supported encrypt method');

                return false;
                break;
        }

        return $retStr;
    }

    /**
     * makeSignPlainText
     * 生成拼接签名源文字符串
     *
     * @param array $requestParams 请求参数
     * @param string $requestMethod 请求方法
     * @param string $requestHost 接口域名
     * @param string $requestPath url路径
     *
     * @return
     */
    public static function makeSignPlainText(
        $requestParams,
        $requestMethod = 'POST', $requestHost = YUNAPI_URL,
        $requestPath = '/v2/index.php'
    )
    {

        $url = $requestHost . $requestPath;

        // 取出所有的参数
        $paramStr = self::_buildParamStr($requestParams, $requestMethod);

        $plainText = $requestMethod . $url . $paramStr;

        return $plainText;
    }

    /**
     * _buildParamStr
     * 拼接参数
     *
     * @param array $requestParams 请求参数
     * @param string $requestMethod 请求方法
     *
     * @return
     */
    protected static function _buildParamStr($requestParams, $requestMethod = 'POST')
    {

        $paramStr = '';
        ksort($requestParams);
        $i = 0;
        foreach ($requestParams as $key => $value) {
            if ($key == 'Signature') {
                continue;
            }
            // 排除上传文件的参数
            if ($requestMethod == 'POST' && substr($value, 0, 1) == '@') {
                continue;
            }
            // 把 参数中的 _ 替换成 .
            if (strpos($key, '_')) {
                $key = str_replace('_', '.', $key);
            }

            if ($i == 0) {
                $paramStr .= '?';
            } else {
                $paramStr .= '&';
            }
            $paramStr .= $key . '=' . $value;
            ++$i;
        }

        return $paramStr;
    }

}

class Queue
{

    private $queue_name;
    private $cmq_client;
    private $encoding;

    public function __construct($queue_name, $cmq_client, $encoding = false)
    {

        $this->queue_name = $queue_name;
        $this->cmq_client = $cmq_client;
        $this->encoding = $encoding;
    }

    public function set_encoding($encoding)
    {

        $this->encoding = $encoding;
    }

    public function create($queue_meta)
    {

        $params = [
            'queueName' => $this->queue_name,
            'pollingWaitSeconds' => $queue_meta->pollingWaitSeconds,
            'visibilityTimeout' => $queue_meta->visibilityTimeout,
            'maxMsgSize' => $queue_meta->maxMsgSize,
            'msgRetentionSeconds' => $queue_meta->msgRetentionSeconds,
            'rewindSeconds' => $queue_meta->rewindSeconds,
        ];
        if ($queue_meta->maxMsgHeapNum > 0) {
            $params['maxMsgHeapNum'] = $queue_meta->maxMsgHeapNum;
        }
        $this->cmq_client->create_queue($params);
    }

    /**
     * @return \Oasis\UserDataCollector\Common\Tencent\QueueMeta
     */
    public function get_attributes()
    {

        $params = [
            'queueName' => $this->queue_name,
        ];
        $resp = $this->cmq_client->get_queue_attributes($params);
        $queue_meta = new QueueMeta();
        $queue_meta->queueName = $this->queue_name;
        $this->__resp2meta__($queue_meta, $resp);

        return $queue_meta;
    }

    public function set_attributes($queue_meta)
    {

        $params = [
            'queueName' => $this->queue_name,
            'pollingWaitSeconds' => $queue_meta->pollingWaitSeconds,
            'visibilityTimeout' => $queue_meta->visibilityTimeout,
            'maxMsgSize' => $queue_meta->maxMsgSize,
            'msgRetentionSeconds' => $queue_meta->msgRetentionSeconds,
            'rewindSeconds' => $queue_meta->rewindSeconds,
        ];
        if ($queue_meta->maxMsgHeapNum > 0) {
            $params['maxMsgHeapNum'] = $queue_meta->maxMsgHeapNum;
        }

        $this->cmq_client->set_queue_attributes($params);
    }

    public function rewindQueue($backTrackingTime)
    {

        $params = [
            'queueName' => $this->queue_name,
            'startConsumeTime' => $backTrackingTime,
        ];
        $this->cmq_client->rewindQueue($params);
    }

    public function delete()
    {

        $params = ['queueName' => $this->queue_name];
        $this->cmq_client->delete_queue($params);
    }

    public function send_message($message, $delayTime = 0)
    {

        if ($this->encoding) {
            $msgBody = base64_encode($message->msgBody);
        } else {
            $msgBody = $message->msgBody;
        }
        $params = [
            'queueName' => $this->queue_name,
            'msgBody' => $msgBody,
            'delaySeconds' => $delayTime,
        ];
        $msgId = $this->cmq_client->send_message($params);
        $retmsg = new Message();
        $retmsg->msgId = $msgId;

        return $retmsg;
    }


    public function batch_send_message($messages, $delayTime = 0)
    {

        $params = [
            'queueName' => $this->queue_name,
            'delaySeconds' => $delayTime,
        ];
        $n = 1;
        foreach ($messages as $message) {
            $key = 'msgBody.' . $n;
            if ($this->encoding) {
                $params[$key] = base64_encode($message->msgBody);
            } else {
                $params[$key] = $message->msgBody;
            }
            $n += 1;
        }
        $msgList = $this->cmq_client->batch_send_message($params);
        $retMessageList = [];
        foreach ($msgList as $msg) {
            $retmsg = new Message();
            $retmsg->msgId = $msg['msgId'];
            $retMessageList [] = $retmsg;
        }

        return $retMessageList;
    }


    public function receive_message($polling_wait_seconds = null)
    {

        $params = ['queueName' => $this->queue_name];
        if ($polling_wait_seconds != null) {
            $params['UserpollingWaitSeconds'] = $polling_wait_seconds;
            $params['pollingWaitSeconds'] = $polling_wait_seconds;
        } else {
            $params['UserpollingWaitSeconds'] = 30;
        }
        $resp = $this->cmq_client->receive_message($params);
        $msg = new Message();
        if ($this->encoding) {
            $msg->msgBody = base64_decode($resp['msgBody']);
        } else {
            $msg->msgBody = $resp['msgBody'];
        }
        $msg->msgId = $resp['msgId'];
        $msg->receiptHandle = $resp['receiptHandle'];
        $msg->enqueueTime = $resp['enqueueTime'];
        $msg->nextVisibleTime = $resp['nextVisibleTime'];
        $msg->dequeueCount = $resp['dequeueCount'];
        $msg->firstDequeueTime = $resp['firstDequeueTime'];

        return $msg;
    }


    public function batch_receive_message($num_of_msg, $polling_wait_seconds = null)
    {

        $params = ['queueName' => $this->queue_name, 'numOfMsg' => $num_of_msg];
        if ($polling_wait_seconds != null) {
            $params['UserpollingWaitSeconds'] = $polling_wait_seconds;
            $params['pollingWaitSeconds'] = $polling_wait_seconds;
        } else {
            $params['UserpollingWaitSeconds'] = 30;
        }
        $msgInfoList = $this->cmq_client->batch_receive_message($params);
        $retMessageList = [];
        foreach ($msgInfoList as $msg) {

            $retmsg = new Message();
            if ($this->encoding) {
                $retmsg->msgBody = base64_decode($msg['msgBody']);
            } else {
                $retmsg->msgBody = $msg['msgBody'];
            }

            $event = json_decode($retmsg->msgBody, true);
            if ($event) {
                $appid = $event["cosBucket"]["appid"];
                $bucketName = $event["cosBucket"]["name"];
                $key = $event["cosObject"]["key"];
                $retmsg->url = str_replace("/$appid/$bucketName/", "", $key);
            }

            $retmsg->msgId = $msg['msgId'];
            $retmsg->receiptHandle = $msg['receiptHandle'];
            $retmsg->enqueueTime = $msg['enqueueTime'];
            $retmsg->nextVisibleTime = $msg['nextVisibleTime'];
            $retmsg->dequeueCount = $msg['dequeueCount'];
            $retmsg->firstDequeueTime = $msg['firstDequeueTime'];
            $retMessageList [] = $retmsg;
        }

        return $retMessageList;
    }

    public function delete_message($receipt_handle)
    {

        $params = ['queueName' => $this->queue_name, 'receiptHandle' => $receipt_handle];
        $this->cmq_client->delete_message($params);
    }

    public function batch_delete_message($receipt_handle_list)
    {

        $params = ['queueName' => $this->queue_name];
        $n = 1;
        foreach ($receipt_handle_list as $receipt_handle) {
            $key = 'receiptHandle.' . $n;
            $params[$key] = $receipt_handle;
            $n += 1;
        }
        $this->cmq_client->batch_delete_message($params);
    }

    protected function __resp2meta__($queue_meta, $resp)
    {

        if (isset($resp['queueName'])) {
            $queue_meta->queueName = $resp['queueName'];
        }
        if (isset($resp['maxMsgHeapNum'])) {
            $queue_meta->maxMsgHeapNum = $resp['maxMsgHeapNum'];
        }
        if (isset($resp['pollingWaitSeconds'])) {
            $queue_meta->pollingWaitSeconds = $resp['pollingWaitSeconds'];
        }
        if (isset($resp['visibilityTimeout'])) {
            $queue_meta->visibilityTimeout = $resp['visibilityTimeout'];
        }
        if (isset($resp['maxMsgSize'])) {
            $queue_meta->maxMsgSize = $resp['maxMsgSize'];
        }
        if (isset($resp['msgRetentionSeconds'])) {
            $queue_meta->msgRetentionSeconds = $resp['msgRetentionSeconds'];
        }
        if (isset($resp['createTime'])) {
            $queue_meta->createTime = $resp['createTime'];
        }
        if (isset($resp['lastModifyTime'])) {
            $queue_meta->lastModifyTime = $resp['lastModifyTime'];
        }
        if (isset($resp['activeMsgNum'])) {
            $queue_meta->activeMsgNum = $resp['activeMsgNum'];
        }
        if (isset($resp['rewindSeconds'])) {
            $queue_meta->rewindSeconds = $resp['rewindSeconds'];
        }
        if (isset($resp['inactiveMsgNum'])) {
            $queue_meta->inactiveMsgNum = $resp['inactiveMsgNum'];
        }
        if (isset($resp['rewindmsgNum'])) {
            $queue_meta->rewindmsgNum = $resp['rewindmsgNum'];
        }
        if (isset($resp['minMsgTime'])) {
            $queue_meta->minMsgTime = $resp['minMsgTime'];
        }
        if (isset($resp['delayMsgNum'])) {
            $queue_meta->delayMsgNum = $resp['delayMsgNum'];
        }
    }
}

class QueueMeta
{

    public $queueName;
    public $maxMsgHeapNum;
    public $pollingWaitSeconds;
    public $visibilityTimeout;
    public $maxMsgSize;
    public $msgRetentionSeconds;
    public $createTime;
    public $lastModifyTime;
    public $activeMsgNum;
    public $inactiveMsgNum;
    public $rewindSeconds;
    public $rewindmsgNum;
    public $minMsgTime;
    public $delayMsgNum;

    public function __construct()
    {

        $this->queueName = "";
        $this->maxMsgHeapNum = -1;
        $this->pollingWaitSeconds = 0;
        $this->visibilityTimeout = 30;
        $this->maxMsgSize = 65536;
        $this->msgRetentionSeconds = 345600;
        $this->createTime = -1;
        $this->lastModifyTime = -1;
        $this->activeMsgNum = -1;
        $this->inactiveMsgNum = -1;
        $this->rewindSeconds = 0;
        $this->rewindmsgNum = 0;
        $this->minMsgTime = 0;
        $this->delayMsgNum = 0;
    }

    public function __toString()
    {

        $info = ["visibilityTimeout" => $this->visibilityTimeout,
            "maxMsgHeapNum" => $this->maxMsgHeapNum,
            "maxMsgSize" => $this->maxMsgSize,
            "msgRetentionSeconds" => $this->msgRetentionSeconds,
            "pollingWaitSeconds" => $this->pollingWaitSeconds,
            "activeMsgNum" => $this->activeMsgNum,
            "inactiveMsgNum" => $this->inactiveMsgNum,
            "createTime" => date("Y-m-d H:i:s", $this->createTime),
            "lastModifyTime" => date("Y-m-d H:i:s", $this->lastModifyTime),
            "QueueName" => $this->queueName,
            "rewindSeconds" => $this->rewindSeconds,
            "rewindmsgNum" => $this->rewindmsgNum,
            "minMsgTime" => $this->minMsgTime,
            "delayMsgNum" => $this->delayMsgNum];

        return json_encode($info);
    }
}

class Message
{

    public $msgBody;
    public $msgId;
    public $enqueueTime;
    public $receiptHandle;


    public function __construct($message_body = "")
    {

        $this->msgBody = $message_body;
        $this->msgId = "";
        $this->enqueueTime = -1;
        $this->receiptHandle = "";
        $this->nextVisibleTime = -1;
        $this->dequeueCount = -1;
        $this->firstDequeueTime = -1;
        $this->url = "";
    }

    public function __toString()
    {

        $info = [
            "msgBody" => $this->msgBody,
            "url" => $this->url,
            "msgId" => $this->msgId,
            "enqueueTime" => date("Y-m-d H:i:s", $this->enqueueTime),
            "nextVisibleTime" => date("Y-m-d H:i:s", $this->nextVisibleTime),
            "firstDequeueTime" => date("Y-m-d H:i:s", $this->firstDequeueTime),
            "dequeueCount" => $this->dequeueCount,
            "receiptHandle" => $this->receiptHandle];

        return json_encode($info);
    }

    public function getBody()
    {

        return json_encode([
            "msgBody" => $this->msgBody,
            "url" => $this->url,
            "msgId" => $this->msgId,
            "enqueueTime" => date("Y-m-d H:i:s", $this->enqueueTime),
            "nextVisibleTime" => date("Y-m-d H:i:s", $this->nextVisibleTime),
            "firstDequeueTime" => date("Y-m-d H:i:s", $this->firstDequeueTime),
            "dequeueCount" => $this->dequeueCount,
            "receiptHandle" => $this->receiptHandle
        ]);

    }
}


