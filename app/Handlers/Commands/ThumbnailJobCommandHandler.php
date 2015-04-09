<?php namespace App\Handlers\Commands;

use App\Commands\ThumbnailJobCommand;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Http\Request;

/**
 * Class ThumbnailJobCommandHandler
 * @package App\Handlers\Commands
 */
class ThumbnailJobCommandHandler
{
    /**
     * @var Log
     */
    public $log;

    /**
     * @var string
     */
    protected $outputExtension;

    /**
     * @var int
     */
    protected $outputQuality;

    /**
     * @var Factory
     */
    protected $storage;

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $localStorage;

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $jobStorage;

    /**
     * @var \Intervention\Image\Image
     */
    protected $image;

    /**
     * Create the command handler.
     *
     * @param Factory $storage
     * @param Request $request
     * @param Log     $logger
     */
    public function __construct(Factory $storage, Request $request, Log $logger = null)
    {
        $this->storage          = $storage;
        $this->localStorage     = $storage->disk('local');

        // Set dynamic bucket
        if ($request->get('storage_options.bucket', null) !== null) {
            config(['filesystems.disks.s3.bucket' => $request->get('storage_options.bucket', null)]);
        }
        // Load the job storage
        $this->jobStorage       = config('work.provider') == 'local'
            ? $this->localStorage
            : $this->storage->disk(config('work.provider'));

        if ($logger === null) {
            $this->log = app('log');
        } else {
            $this->log = $logger;
        }
        $this->outputExtension  = config('work.default_output_ext', 'jpg');
        $this->outputQuality    = config('work.default_output_quality', 80);

        return $this;
    }

    /**
     * Handle the command.
     *
     * Process all sizes in the data message
     *
     * @param  ThumbnailJobCommand $command
     * @return array
     */
    public function handle(ThumbnailJobCommand $command)
    {
        $data = $command->data;

        // Copy file
        $fileCopy = $this->createWorkingCopy($data['filename']);

        // Work on the copied file
        $files = $this->createThumbnails($fileCopy, $data['filename'], $data['sizes']);

        // Get the original file permission
        $visibility = $this->jobStorage->getVisibility($data['filename']);

        // Upload or store the file
        $this->uploadFiles($files, $visibility);

        // Clean up
        $this->cleanUp($files, $fileCopy);
    }

    /**
     * Format the new file name
     *
     * @param $name
     * @param $suffix
     * @return string
     */
    protected function formatNewName($name, $suffix)
    {
        $info = pathinfo($name);
        $newTarget = $info['dirname'] . '/' . $info['filename'] . '_' . $suffix;
        if (isset($info['extension'])) {
            $newTarget .= '.' . $info['extension'];
        }

        return $newTarget;
    }

    /**
     * Returns a temporary file with a copy of the original
     *
     * @param $filename
     * @return string
     */
    public function createWorkingCopy($filename)
    {
        $tmpFile     = tempnam(storage_path() . '/' . config('work.tmp_path'), 'imgs-s3-');
        $tmpInfo     = pathinfo($tmpFile);
        $tmpFileName = config('work.tmp_path') . $tmpInfo['basename'];
        $file        = $this->jobStorage->get($filename);
        $this->localStorage->put($tmpFileName, $file);

        return $tmpFile;
    }

    /**
     * Generates the image thumbnails
     *
     * @param $filePath
     * @param $origFilePath
     * @param $sizes
     * @return array
     * @throws \Exception
     */
    public function createThumbnails($filePath, $origFilePath, $sizes)
    {
        $this->log->debug('-> Begin thumbnail creation');
        $this->log->debug(count($sizes) . ' Thumbnails to create');

        $this->createImageResource($filePath);

        $counter    = 1;
        $childrens  = [];
        // Backup the original resource
        $this->image->backup();
        $oldOutputExtension = $this->image->extension;
        foreach ($sizes as $size) {
            $this->log->debug('Creating new thumbnail.');
            /**
             * Set the current extension for the image,
             * don't do anything if its the same
             */
            $newOutputExtension = isset($size['ext']) ? $size['ext'] : $this->outputExtension;
            if ($oldOutputExtension != $newOutputExtension) {
                $oldOutputExtension = $newOutputExtension;
                $this->image->encode($newOutputExtension);
            }
            $quality = isset($size['quality']) ? $size['quality'] : $this->outputQuality;

            // Set file suffix
            $suffix = isset($size['name']) ? $size['name'] : "{$size['width']}x{$size['height']}";
            $this->log->debug('Creating thumbnail "' . $suffix . '" and added to upload queue: ' . $counter);

            // add callback functionality to retain maximal original image size
            $this->image->resize($size['width'], $size['height'], function ($constraint) {
                /**
                 * @var \Intervention\Image\Constraint $constraint
                 */
                $constraint->aspectRatio();
                $constraint->upsize();
            });


            // Save new thumbnail on a temporary file
            $tmpFileInfo    = pathinfo($filePath);
            $origFileInfo   = pathinfo($origFilePath);
            $newTmpFileName = $tmpFileInfo['dirname'] . '/' . $origFileInfo['filename'] . '.' . $newOutputExtension;
            $newTmpFilePath = $this->formatNewName($newTmpFileName, $suffix);
            // Format the name with the size suffix
            $newThumbnailFilePath = $this->formatNewName($origFilePath, $suffix);

            // Save the temp file
            $this->log->debug('Saving thumbnail: ' . $counter . ' to a temporary location');
            $this->image->save($newTmpFilePath, $quality);
            // Get the mime of the file
            $mime = $this->image->mime();
            // Restore original image resource
            $this->image->reset();

            // Add thumbnail to upload queue
            $childrens[] = [
                'source'        => $newTmpFilePath,
                'destination'   => $newThumbnailFilePath,
                'content_type'  => $mime,
            ];

            $this->log->debug('Thumbnail created!');
            $this->log->debug('Checking if there is any other thumbnail to create...');
            $counter++;
        }

        // Destroy the image resource as is no longer needed
        $this->image->destroy();

        $this->log->debug('No more thumbnails pending!');
        $this->log->debug('-> Done creating thumbnails');

        return $childrens;
    }

    /**
     * Create an Intervention image resource
     *
     * @param $imagePath
     * @throws \Exception
     */
    protected function createImageResource($imagePath)
    {
        // Generate the image resource
        try {
            $this->log->debug('Trying to create an image resource...');
            $this->image = \Image::make($imagePath);
        } catch (\Exception $e) {
            unlink($imagePath);
            throw new \Exception('Cant create image resource: ' . $e->getMessage());
        }

        $this->log->debug('Image resource created!');
    }

    public function uploadFiles($files, $visibility)
    {
        $this->log->debug('-> Start thumbnail upload');
        try {
            $count = 1;
            $total = count($files);
            foreach ($files as $k => $childFile) {
                $this->log->debug('Uploading file ' . $count . ' of ' . $total);
                // Save the new file and set its visibility
                $this->jobStorage->put($childFile['destination'], $this->localStorage->get($childFile['source']));
                $this->jobStorage->setVisibility($childFile['destination'], $visibility);
                // Clear the tmp file
                $this->localStorage->delete($childFile['source']);
                unset($files[$k]);
                $this->log->debug('File uploaded!');
                $count++;
            }

            $this->log->debug('Done Uploading!');
            $this->log->debug('-> File Upload  ended');
        } catch (\Exception $e) {
            $this->log->error('An error was detected while uploading files');
            $this->log->error($e->getMessage());

            // If any error, discard the files and restore the message to the queue
            $this->log->critical('A file could not be uploaded: ' . $e->getMessage());
            $this->log->debug('Removing previous files in this task');

            foreach ($files as $k => $childFile) {
                $this->localStorage->delete($childFile['source']);
                unset($files[$k]);
            }

            $this->log->debug('-> File Upload  ended');
            throw new \Exception('Errors were found when attempting to upload files');
        }
    }

    /**
     * Clear the file copy
     *
     * @param $fileCopy
     */
    protected function cleanUp($fileCopy)
    {
        $this->log->debug('Removing temp file');
        // Discard the file and its childrens once job is done
        $this->localStorage->delete($fileCopy);
    }
}
