<?php
namespace Oasis\Mlib\TencentWrappers\Cos;
use Qcloud\Cos\Client;

class CosClient extends Client
{
    private $region ="";

    public function __construct($accessKeyId, $accessKeySecret, $region)
    {
        $this->region = $region;

        parent::__construct(
            array(
                'region' => $region,
                'schema' => 'https',
                'credentials'=> array(
                    'secretId'  => $accessKeyId,
                    'secretKey' => $accessKeySecret
                ))
        );
    }

    public function getRegion(){
        return $this->region;
    }
}
