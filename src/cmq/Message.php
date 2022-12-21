<?php


namespace Oasis\Mlib\TencentWrappers\Cmq;


use Oasis\Mlib\AwsWrappers\Contracts\QueueMessageInterface;

class Message implements QueueMessageInterface
{

    public $msgBody;
    public $msgId;
    public $enqueueTime;
    public $receiptHandle;
    public $url;
    public $nextVisibleTime;
    public $firstDequeueTime;
    public $dequeueCount;


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

        return $this->msgBody;

    }
}
