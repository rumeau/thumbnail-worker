<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 21:26
 */

namespace App\File;

use Illuminate\Log\Writer;

/**
 * Class FileProvider
 * @package App\File
 */
class FileProvider
{
    /**
     * @var \Log
     */
    protected $log;

    /**
     * @var AbstractFileProvider
     */
    protected $provider;

    /**
     * Constructor
     *
     * Attempts to initialize the configured file provider
     *
     * @param \Illuminate\Log\Writer $logger
     */
    public function __construct(Writer $logger)
    {
        $this->log = $logger;

        $provider = config('download.provider');
        if (!empty($provider)) {
            $class = __NAMESPACE__ . '\\' . ucfirst($provider) . 'Provider';

            if (class_exists($class)) {
                $options = config('download.' . $provider, null);
                $this->provider = new $class($options);
                $this->provider->setLog($this->log);
            }
        }
    }

    /**
     * Returns the download provider object
     *
     * @return null|AbstractFileProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }
}
