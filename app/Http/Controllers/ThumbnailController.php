<?php namespace App\Http\Controllers;

use App\Commands\ThumbnailJobCommand;
use App\Http\Requests;

use App\Validators\JobParams;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Writer;

class ThumbnailController extends Controller
{

    /**
     * @var string
     */
    protected $tmpPath;

    /**
     * @var \Aws\S3\S3Client
     */
    protected $s3Client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var Writer
     */
    protected $log;

    /**
     * @param Writer $logger
     */
    public function __construct(Writer $logger)
    {
        $this->log             = $logger;
        $this->log->info('-- Init thumbnail work --');
    }

    /**
     * Attempt to create thumbnails from a queue message
     *
     * @param Requests\Thumbnail $request
     * @param JobParams $jobValidator
     * @return Response|mixed
     */
    public function store(Requests\Thumbnail $request, JobParams $jobValidator)
    {
        // Get queue message data
        $data           = $request->all();
        $this->s3Client = \AWS::get('s3');
        $this->bucket   = isset($data['storage_options']['bucket']) ? $data['storage_options']['bucket'] : false;

        /**
         * Validate valid bucket existence
         */
        if (!$this->bucket|| !$this->s3Client->doesBucketExist($this->bucket)) {
            return $this->endError('Bucket not found');
        }

        /**
         * Attempt to validate provided params
         */
        $validator = $jobValidator->validator($data);
        if ($validator->fails()) {
            return $this->endError($validator->messages());
        }

        /**
         * Get S3 file
         * @var \App\File\FileProvider $fileProvider
         */
        $fileProvider = app('App\File\FileProvider');
        $provider     = $fileProvider->getProvider();
        if (!$file = $provider->getFile($data['filename'], $this->bucket)) {
            return $this->endError();
        }

        // Generate the image resource
        try {
            $image = \Image::make($file->tmpName);
        } catch (\Exception $e) {
            return $this->endError('Cant create image resource: ' . $e->getMessage());
        }

        // Process the image and return files
        $files = $this->dispatch(new ThumbnailJobCommand($image, $file, $this->bucket, $data['sizes']));
        try {
            $this->log->debug('--- Initializing upload ---');
            // Upload files
            $provider->putFiles($files, $this->bucket);

            $this->log->info('Done Uploading!');
            $this->log->debug('-> Removing temp file');

            // Discard the file and its childrens once job is done
            $provider->discardFile($files);
            // Destroy the image resource
            $image->destroy();
        } catch (\Exception $e) {
            // If any error, discard the files and restore the message to the queue
            $this->log->debug('Remove previous files in this task');

            $this->log->debug('-> Removing temp file');
            $provider->discardFile($files);
            // Destroy the image resource
            $image->destroy();

            return $this->endError($e->getMessage());
        }

        $this->log->info('-- End thumbnail work --');
        // Send a success response
        return response(null, 200);
    }

    /**
     * Start profiling work
     *
     * @todo move to service
     * @return mixed
     */
    protected function startProfiling()
    {
        if (!config('work.profile', false)) {
            return null;
        }

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $starttime = $mtime;

        $this->log->info('[PROFILE] START MEMORY: ' . memory_get_usage());

        return $starttime;
    }

    /**
     * End profiling work
     *
     * @todo move to service
     * @param $starttime
     * @return null
     */
    protected function endProfiling($starttime)
    {
        if (!config('work.profile', false)) {
            return null;
        }

        $this->log->info('[PROFILE] END MEMORY: ' . memory_get_usage());
        $this->log->info('[PROFILE] PEAK MEMORY: ' . memory_get_peak_usage(true));

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $endtime = $mtime;
        $totaltime = ($endtime - $starttime);
        $this->log->info('[PROFILE] JOB EXECUTED IN ' . $totaltime . ' SECONDS');
    }

    /**
     * @param $msg
     */
    public function debug($msg)
    {
        if (config('app.debug', false)) {
            $this->log->debug($msg);
        }
    }

    /**
     * @param string $msg
     * @return mixed
     */
    protected function endError($msg = null)
    {
        if ($msg != null) {
            $this->log->error($msg);
        }
        $this->log->error('-- End thumbnail work --');
        return response(null, 400);
    }
}
