<?php


namespace Oasis\Mlib\TencentWrappers\Cmq;



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
