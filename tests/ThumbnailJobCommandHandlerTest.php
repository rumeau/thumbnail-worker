<?php
/**
 * Created by PhpStorm.
 * User: rumea
 * Date: 08/04/2015
 * Time: 18:48
 */

class ThumbnailJobCommandHandlerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        if (config('work.provider') == 'local') {
            $testDir = storage_path() . '/' . config('work.tmp_path') . 'test';
            $dummy = base_path() . '/resources/assets/chewie.jpg';
            if (!is_dir($testDir)) {
                mkdir($testDir);
            }
            if (!file_exists($testDir . 'cheweie.jpg')) {
                copy($dummy, $testDir . '/chewie.jpg');
            }
        } else {
            $this->markTestSkipped('Not using the local provider');
        }
    }

    public function tearDown()
    {
        if (config('work.provider') == 'local') {
            $testDir = storage_path() . '/' . config('work.tmp_path') . 'test';
            $testOutputDir = storage_path() . '/' . config('work.tmp_path') . 'output';
            if (file_exists($testDir . '/chewie.jpg')) {
                unlink($testDir . '/chewie.jpg');
            }
            if (is_dir($testDir)) {
                rmdir($testDir);
            }

            if (file_exists($testOutputDir . '/chewie_150x150.png')) {
                unlink($testOutputDir . '/chewie_150x150.png');
            }
            if (file_exists($testOutputDir . '/chewie_large.jpg')) {
                unlink($testOutputDir . '/chewie_large.jpg');
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
        }

        parent::tearDown();
    }

    public function testLocalStorageExists()
    {
        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];

        $this->assertTrue($storage->disk('local')->exists(config('work.tmp_path') . '.gitkeep'));
    }

    public function testCanCreateWorkingCopy()
    {
        $dest  = config('work.tmp_path') . 'test/chewie.jpg';

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        $request = new \Illuminate\Http\Request();
        $handler = new \App\Handlers\Commands\ThumbnailJobCommandHandler($storage, $request);

        $tmpFile = $handler->createWorkingCopy($dest);

        $this->assertFileExists($tmpFile);
    }

    public function testCreateThumbnails()
    {
        $testfile  = config('work.tmp_path') . 'test/chewie.jpg';

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
                'destination'   => config('work.tmp_path') . '/output/chewie_150x150.png',
                'content_type'  => 'image/png',
            ],
            [
                'source'        => config('work.tmp_path') . 'chewie_large.jpg',
                'destination'   => config('work.tmp_path') . '/output/chewie_large.jpg',
                'content_type'  => 'image/jpg',
            ]
        ];

        $storage = $this->app['Illuminate\Contracts\Filesystem\Factory'];
        $request = new \Illuminate\Http\Request();
        $handler = new \App\Handlers\Commands\ThumbnailJobCommandHandler($storage, $request);

        // Get the original file permission
        $visibility = $storage->disk(config('work.provider'))->getVisibility(config('work.tmp_path') . '/test/chewie.jpg');
        $handler->uploadFiles($testfiles, $visibility);

        $this->assertFileNotExists(storage_path() . '/tmp/chewie_large.jpg');
        $this->assertFileNotExists(storage_path() . '/tmp/chewie_150x150.png');
    }
}
