<?php

namespace Oasis\Mlib\TencentWrappers\Test;

use Oasis\Mlib\TencentWrappers\Cmq\TencentCmqQueue;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class CmqTest extends TestCase
{
    static $client;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $dataSourceFile = __DIR__ . "/config.yml";
        $config = Yaml::parse(file_get_contents($dataSourceFile));
        $dp = new ArrayDataProvider($config);
        $config = $dp->getMandatory('tencent', DataProviderInterface::ARRAY_TYPE);


        self::$client = new TencentCmqQueue(
            $config["access_key"],
            $config["access_secret"],
            $config["end_point"],
            $config["queue"]
        );
    }

    public function testQueue()
    {
        self::$client->sendMessage("message");
        $message = self::$client->receiveMessage();
        if ($message) {
            $this->assertEquals("message", $message->getBody());
            self::$client->deleteMessage($message);
        }
    }

    public function testMessages()
    {
        self::$client->sendMessage(["message"]);
        $message = self::$client->receiveMessage();

        if ($message) {
            $this->assertEquals(["message"], $message->getBody());
            self::$client->deleteMessage($message);
        }
    }
}
