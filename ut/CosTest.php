<?php

namespace Oasis\Mlib\TencentWrappers\Test;

use Oasis\Mlib\FlysystemWrappers\ExtendedFilesystem;
use Oasis\Mlib\TencentWrappers\Cos\CosClient;
use Oasis\Mlib\TencentWrappers\Cos\ExtendedCosAdapter;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class CosTest extends TestCase
{
    static $client;
    static $adapter;
    static $fs;
    static $path="tt005.txt";
    static $path_copy="tt005_copy.txt";

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $dataSourceFile = __DIR__ . "/config.yml";
        $config = Yaml::parse(file_get_contents($dataSourceFile));
        $dp = new ArrayDataProvider($config);
        $config = $dp->getMandatory('tencent', DataProviderInterface::ARRAY_TYPE);


        self::$client = new CosClient(
            $config["access_key"],
            $config["access_secret"],
            $config["region"]
        );

        self::$adapter = new ExtendedCosAdapter(
            self::$client,
            $config["bucket"]
        );

        self::$fs = new ExtendedFilesystem(
            self::$adapter
        );


    }

    public function testPut()
    {
        self::$fs->put(self::$path, "test");
        $ret = self::$fs->has(self::$path);
        $this->assertEquals(true, $ret);


        $content = self::$fs->read(self::$path);
        $this->assertEquals("test", $content);

        self::$fs->delete(self::$path);
    }


    public function testWrite()
    {
        self::$fs->write(self::$path, "test");
        $ret = self::$fs->has(self::$path);
        $this->assertEquals(true, $ret);


        $content = self::$fs->read(self::$path);
        $this->assertEquals("test", $content);

        self::$fs->delete(self::$path);
    }

    public function testCopy()
    {
        self::$fs->write(self::$path, "test");
        $ret = self::$fs->has(self::$path);
        $this->assertEquals(true, $ret);


        self::$fs->copy(self::$path,self::$path_copy);
        $ret = self::$fs->has(self::$path_copy);
        $this->assertEquals(true, $ret);

        self::$fs->delete(self::$path);
        self::$fs->delete(self::$path_copy);
    }


    public function testStream()
    {
        self::$fs->writeStream(self::$path, fopen("ut/cos.txt","r"));
        $ret = self::$fs->has(self::$path);
        $this->assertEquals(true, $ret);


        $fh = self::$fs->readStream( self::$path);
        $lines = [];
        while (!feof($fh)) {
            $lines[]=trim(fgets($fh),"\n");
        }

        $this->assertEquals(6, count($lines));
        $this->assertEquals("1111", $lines[0]);
        $this->assertEquals("2222", $lines[1]);
        $this->assertEquals("5555", $lines[4]);

        self::$fs->delete(self::$path);
    }

}
