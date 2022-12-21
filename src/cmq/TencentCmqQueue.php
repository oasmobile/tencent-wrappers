<?php

namespace Oasis\Mlib\TencentWrappers\Cmq;

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
     * @param $messages
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

        $list=[];
        $messages = $this->queue->batch_receive_message($maxCount, $wait);

        foreach ($messages as $key => $msg) {

            $body = json_decode($msg->getBody(), true);
            if ($body && isset($body[self::SERIALIZATION_FLAG]) && $body[self::SERIALIZATION_FLAG] = 'base64_serialize') {

                $msg->msgBody = unserialize(base64_decode($body['body']));
            }

            if ($body && isset($body["cosBucket"])) {
                $appid = $body["cosBucket"]["appid"];
                $bucketName = $body["cosBucket"]["name"];
                $key = $body["cosObject"]["key"];
                $body['url'] = str_replace("/$appid/$bucketName/", "", $key);
                $msg->msgBody=json_encode($body);
            }

            $list[]=$msg;
        }

        return $list;
    }
}

























