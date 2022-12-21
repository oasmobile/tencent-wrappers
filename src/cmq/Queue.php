<?php


namespace Oasis\Mlib\TencentWrappers\Cmq;


class Queue
{

    private $queue_name;

    /**
     * @var CMQClient
     */
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


    /**
     * @param $num_of_msg
     * @param null $polling_wait_seconds
     * @return Message[] array
     */
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
