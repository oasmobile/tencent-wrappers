<?php

namespace Oasis\UserDataCollector\Common\Tencent\Cos;

use Exception;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Config;
use Oasis\Mlib\FlysystemWrappers\FindableAdapterInterface;
use Symfony\Component\Finder\Finder;

class ExtendedCosAdapter extends AbstractAdapter implements FindableAdapterInterface
{

    use StreamedTrait;
    use NotSupportingVisibilityTrait;

    /**
     * @var CosClient
     */
    protected $client;

    /**
     * bucket name.
     *
     * @var string
     */
    protected $bucket;

    /**
     * @var array
     */
    protected $options = [];


    /**
     * protocol => registering adapter
     *
     * @var array
     */
    protected static $registeredWrappers = [];

    /**
     *
     * @param CosClient $client
     * @param                $bucket
     * @param null $prefix
     * @param array $options
     */
    public function __construct(CosClient $client, $bucket, $prefix = null, array $options = [])
    {

        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get the Aliyun Oss Client bucket.
     *
     * @return string
     */
    public function getBucket()
    {

        return $this->bucket;
    }

    /**
     * @return CosClient
     */
    public function getClient()
    {

        return $this->client;
    }

    /**
     * @param                          $path
     * @param                          $localFilePath
     * @param Config $config
     *
     * @return array|false
     */
    public function putFile($path, $localFilePath, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig();
        $fh = fopen($localFilePath, "rb");
        $this->client->upload($this->bucket, $object, $fh, $options);

        $type = 'file';
        return compact('type', 'path');
    }

    /**
     * @param string                   @$path
     * @param string                   @$contents
     * @param Config $config
     *
     * @return array|false
     */
    public function write($path, $contents, Config $config)
    {

        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig();


        $this->client->upload(
            $this->bucket, $object, $contents, $options
        );

        $type = 'file';
        return compact('type', 'path', 'contents');
    }

    /**
     * Update a file.
     *
     * @param string @$path
     * @param string @$contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {

        return $this->write($path, $contents, $config);
    }

    /**
     * Rename a file.
     *
     * @param string @$path
     * @param string @$newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {

        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string @$path
     * @param string @$newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {

        $object = $this->applyPathPrefix($path);
        $newobject = $this->applyPathPrefix($newpath);

        $this->client->copy(
            $this->bucket,
            $newobject,
            [
                "Bucket" => $this->bucket,
                "Region" => $this->client->getRegion(),
                "Key" => $object
            ]
        );

        return true;
    }

    /**
     * Delete a file.
     *
     * @param string @$path
     *
     * @return bool
     */
    public function delete($path)
    {

        $object = $this->applyPathPrefix($path);

        $this->client->deleteObject([
            "Bucket" => $this->bucket,
            "Key" => $object
        ]);

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string @$dirname
     *
     * @return bool
     * @throws OssException
     */
    public function deleteDir($dirname)
    {

        $list = $this->listContents($dirname, true);

        $objects = [];
        foreach ($list as $val) {
            if ($val['type'] === 'file') {
                $objects["Key"] = $this->applyPathPrefix($val['path']);
            }
        }

        $this->client->deleteObjects(
            [
                'Bucket' => $this->bucket,
                'Objects' => $objects
            ]
        );

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string @$dirname directory name
     * @param Config $config
     *
     * @return void
     * @throws Exception
     */
    public function createDir($dirname, Config $config)
    {
        throw new Exception("not supported");
    }

    /**
     * Check whether a file exists.
     *
     * @param string @$path
     *
     * @return bool
     */
    public function has($path)
    {

        $object = $this->applyPathPrefix($path);

        return $this->client->doesObjectExist($this->bucket, $object);
    }

    /**
     * Read a file.
     *
     * @param string @$path
     *
     * @return array|false
     */
    public function read($path)
    {
        $object = $this->applyPathPrefix($path);
        $tmpFile = "/tmp/" . tmpfile() . getmypid();
        $this->client->getObject(
            [
                'Bucket' => $this->bucket,
                'Key' => $object,
                'SaveAs' => $tmpFile
            ]
        );
        $contents = file_get_contents($tmpFile);

        return compact('contents', 'path');
    }

    public function readStream($path)
    {

        $object = $this->applyPathPrefix($path);
        $tmpFile = "/tmp/" . tmpfile() . getmypid();
        $this->client->getObject(
            [
                'Bucket' => $this->bucket,
                'Key' => $object,
                'SaveAs' => $tmpFile
            ]
        );
        $stream = fopen($tmpFile,"r");

        return compact('stream', 'path');
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     * @throws OssException
     */
    public function listContents($directory = '', $recursive = false)
    {

        return [];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string @$path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {


        return [];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string @$path
     *
     * @return array|false
     */
    public function getSize($path)
    {

        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string @$path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {

        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string @$path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {

        return $this->getMetadata($path);
    }

    /**
     * Get the signed download url of a file.
     *
     * @param string @$path
     * @param int $expires
     * @param string $host_name
     * @param bool $use_ssl
     *
     * @return string
     * @throws OssException
     */
    public function getSignedDownloadUrl($path, $expires = 3600, $host_name = '', $use_ssl = false)
    {

        $object = $this->applyPathPrefix($path);
        $url = $this->client->getPresignedUrl($this->bucket, $object, $expires);

        if (!empty($host_name) || $use_ssl) {
            $parse_url = parse_url($url);
            if (!empty($host_name)) {
                $parse_url['host'] = $this->bucket . '.' . $host_name;
            }
            if ($use_ssl) {
                $parse_url['scheme'] = 'https';
            }

            $url = (isset($parse_url['scheme']) ? $parse_url['scheme'] . '://' : '')
                . (
                isset($parse_url['user']) ?
                    $parse_url['user'] . (isset($parse_url['pass']) ? ':' . $parse_url['pass'] : '') . '@'
                    : ''
                )
                . (isset($parse_url['host']) ? $parse_url['host'] : '')
                . (isset($parse_url['port']) ? ':' . $parse_url['port'] : '')
                . (isset($parse_url['path']) ? $parse_url['path'] : '')
                . (isset($parse_url['query']) ? '?' . $parse_url['query'] : '');
        }

        return $url;
    }

    /**
     * Get options from the config.
     *
     * @return array
     */
    protected function getOptionsFromConfig()
    {
        return [];
    }

    public function getRealpath($path)
    {

        $path = $this->applyPathPrefix($path);

        return sprintf("cos://%s/%s", $this->getBucket(), $path);
    }

    public function getFinder($path = '')
    {

        if (($protocol = array_search($this, self::$registeredWrappers))
            === false
        ) {
            $protocol = $this->registerStreamWrapper(null);
        }

        $path = sprintf(
            "%s://%s/%s",
            $protocol,
            $this->getBucket(),
            $this->applyPathPrefix($path)
        );
        $finder = new Finder();
        $finder->in($path);

        return $finder;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function registerStreamWrapper($protocol = "s3")
    {

        throw new Exception("Not implement yet: registerStreamWrapper()");
    }
}

