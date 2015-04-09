<?php namespace App\Http\Controllers;

use App\Commands\ThumbnailJobCommand;
use App\Http\Requests;

use App\Validators\JobParams;
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
     * @var \Intervention\Image\Image
     */
    protected $image;

    /**
     * Constructor
     *
     * @param Writer $logger
     */
    public function __construct(Writer $logger)
    {
        $this->log     = $logger;
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

        /**
         * Attempt to validate provided params
         */
        $validator = $jobValidator->validator($data);
        if ($validator->fails()) {
            return $this->endError($validator->messages());
        }

        try {
            // Process the image and return files
            $this->dispatch(new ThumbnailJobCommand($data));
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            $this->log->error($e->getTraceAsString());

            return $this->endError(null);
        }

        $this->log->info('-- End thumbnail work --');
        // Send a success response
        return response(null, 200);
    }

    /**
     * End the request with an error message
     *
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
