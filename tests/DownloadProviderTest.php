<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 21:39
 */

class DownloadProviderTest extends TestCase
{
    public function testDownloadServiceProvider()
    {
        /**
         * @var \App\File\Downloader $downloadProvider
         */
        $downloadProvider = $this->app['App\File\Downloader'];
        $this->assertInstanceOf('App\File\DownloadProviderInterface', $downloadProvider->getDownloader());
    }

    public function testDownloadFileError()
    {
        /**
         * @var \App\File\Downloader $downloadProvider
         */
        $downloadProvider = $this->app['App\File\Downloader'];
        $downloader       = $downloadProvider->getDownloader();
        $file             = $downloader->getFile('dummy', ['bucket']);

        $this->assertFalse($file);
    }
}
