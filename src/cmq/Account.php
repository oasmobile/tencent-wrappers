<?php


namespace Oasis\Mlib\TencentWrappers\Cmq;


use Exception;

class Account
{

    private $secretId;
    private $secretKey;
    private $cmq_client;
    private $host;

    public function __construct($host, $secretId, $secretKey)
    {

        $this->host = $host;
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->cmq_client = new CMQClient($host, $secretId, $secretKey);
    }

    public function set_sign_method($sign_method = 'sha1')
    {

        try {
            $this->cmq_client->set_sign_method($sign_method);
        } catch (Exception $e) {
        }
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
