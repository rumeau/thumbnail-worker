<?php
/**
 * Created by PhpStorm.
 * User: rumeau
 * Date: 08/04/2015
 * Time: 18:48
 */

class ThumbnailJobCommandHandlerAWSTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        if (config('work.provider') == 's3') {
            try {
                $disk = $storage->disk('s3')->exists('asset/chewie.jpg');

                $testDir = storage_path() . '/' . config('work.tmp_path') . 'test';
                $dummy = base_path() . '/resources/assets/chewie.jpg';
                if (!is_dir($testDir)) {
                    mkdir($testDir);
                }
                if (!file_exists($testDir . 'cheweie.jpg')) {
                    copy($dummy, $testDir . '/chewie.jpg');
                }
            } catch (\Exception $e) {
                $this->markTestSkipped('No valid S3 Storage configured');
            }
        } else {
            $this->markTestSkipped('Not using the S3 provider');
        }
    }

    public function tearDown()
    {
        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        if (config('work.provider') == 's3') {
            try {
                $disk = $storage->disk('s3')->exists('asset/chewie.jpg');

                $testDir = storage_path() . '/' . config('work.tmp_path') . 'test';
                $testOutputDir = storage_path() . '/' . config('work.tmp_path') . 'output';
                if (file_exists($testDir . '/chewie.jpg')) {
                    unlink($testDir . '/chewie.jpg');
                }
                if (is_dir($testDir)) {
                    rmdir($testDir);
                }

                if (file_exists($testOutputDir . '/chewie_150x150.png')) {
                    //unlink($testOutputDir . '/chewie_150x150.png');
                }
                if (file_exists($testOutputDir . '/chewie_large.jpg')) {
                    //unlink($testOutputDir . '/chewie_large.jpg');
                }

                $scan = scandir(storage_path() . '/' . config('work.tmp_path'));
                foreach ($scan as $object) {
                    if ($object == "." || $object == "..") {
                        continue;
                    }

                    if (strpos($object, '.tmp')) {
                        unlink(storage_path() . '/' . config('work.tmp_path') . $object);
                    }
                }
                reset($scan);

            } catch (\Exception $e) {
                $this->markTestSkipped('No valid S3 Storage configured');
            }
        }

        parent::tearDown();
    }

    public function testCanCreateWorkingCopy()
    {
        $dest  = 'asset/chewie.jpg';

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        $request = new \Illuminate\Http\Request();
        $handler = new \App\Handlers\Commands\ThumbnailJobCommandHandler($storage, $request);

        $tmpFile = $handler->createWorkingCopy($dest);

        $this->assertFileExists($tmpFile);
    }

    public function testCreateThumbnails()
    {
        $testfile  = 'asset/chewie.jpg';

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        $request = new \Illuminate\Http\Request();
        $handler = new \App\Handlers\Commands\ThumbnailJobCommandHandler($storage, $request);

        $tmpFile = $handler->createWorkingCopy($testfile);

        $message = [
            'sizes' => [
                [
                    'name' => 'large',
                    'width' => 100,
                    'height' => 100,
                ],
                [
                    'width' => 150,
                    'height' => 150,
                    'ext' => 'png',
                ]
            ]
        ];

        // Work on the copied file
        $files = $handler->createThumbnails($tmpFile, $testfile, $message['sizes']);

        $this->assertCount(2, $files);
        $this->assertFileExists(storage_path() . '/tmp/chewie_large.jpg');
        $this->assertFileExists(storage_path() . '/tmp/chewie_150x150.png');
    }

    public function testCanUploadThumbnails()
    {
        $testfiles = [
            [
                'source'        => config('work.tmp_path') . 'chewie_150x150.png',
                'destination'   => 'asset/chewie_150x150.png',
                'content_type'  => 'image/png',
            ],
            [
                'source'        => config('work.tmp_path') . 'chewie_large.jpg',
                'destination'   => 'asset/chewie_large.jpg',
                'content_type'  => 'image/jpg',
            ]
        ];

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        $request = new \Illuminate\Http\Request();
        $handler = new \App\Handlers\Commands\ThumbnailJobCommandHandler($storage, $request);

        // Get the original file permission
        $visibility = $storage->disk('s3')->getVisibility('asset/chewie.jpg');
        $handler->uploadFiles($testfiles, $visibility);

        $this->assertTrue($storage->disk('s3')->exists('asset/chewie_large.jpg'));
        $this->assertTrue($storage->disk('s3')->exists('asset/chewie_150x150.png'));

        $this->assertFileNotExists(storage_path() . '/tmp/chewie_large.jpg');
        $this->assertFileNotExists(storage_path() . '/tmp/chewie_150x150.png');
    }
}
