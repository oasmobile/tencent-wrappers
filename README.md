# tencent-wrappers

```
composer require luguoit/tencent-wrappers
```

CMQ

```
$queue = new TencentCmqQueue(
    "x",
    "x",
    "https:cmq-use.public.tencenttdmq.com",
    "mdata-rawlog"
);

$messages = $queue->receiveMessages(5); 
$queue->deleteMessages($messages);

```


COS

```
/** @var CosClient $client */
$client = new  CosClient(
    "x",
    "x",
    "ap-beijing"
);

$list = $client->listObjects(['Bucket' => "mdata-rawlog-test-bj-1300994929"]);
print_r($list);
```




Flysystem COS 
```

$client = new ExtendedFilesystem(
            new ExtendedCosAdapter(
                new CosClient($config["access_key"],$config["access_secret"],$config["region"]),
                $config["bucket"]
            )
);



$client->has("202212081440_000_6");
$client->put("t001.txt","111");
$a = $client->write("t002.txt","111");
$a = $client->writeStream("t_stream.txt",fopen("README.md","r"));
$a = $client->copy("t_stream.txt","t_stream2.txt");
$a = $client->delete( "t_stream2.txt");
$a = $client->read( "t001.txt");
print_r($a);

$fh = $client->readStream( "202212081440_000_6");
while (!feof($fh)) {
    print_r(fgets($fh));
}
 

```
