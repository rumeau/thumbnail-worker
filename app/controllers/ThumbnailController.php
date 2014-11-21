<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 20/11/2014
 * Time: 22:37
 */

/**
 * Class ThumbnailController
 */
class ThumbnailController extends BaseController
{
    /**
     * @var string
     */
    protected $tmpPath;

    /**
     * @var Aws\S3\S3Client
     */
    protected $s3Client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var
     */
    protected $outputExtension;

    public function __construct()
    {
        $this->tmpPath         = storage_path() . Config::get('app.paperlast.tmp_path');
        $this->outputExtension = Config::get('app.paperlast.output_extension', 'jpg');
    }

    /**
     * @return Response
     */
    public function store()
    {
        Log::info('-- Init thumbnail work --');
        $start     = $this->startProfiling();

        $response  = Response::make('', 200);
        $input     = Input::all();
        $validator = $this->validator($input);
        if ($validator->fails()) {
            Log::error($validator->messages());
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        $data           = Input::all();
        $this->s3Client = AWS::get('s3');
        $this->bucket   = isset($data['storage_options']['bucket']) ? $data['storage_options']['bucket'] : false;
        if (!$this->bucket|| !$this->s3Client->doesBucketExist($this->bucket)) {
            Log::error('Bucket not found');
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        $error = $this->validateParams($data);
        if ($error) {
            Log::error($error);
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        $file = $this->getFile($data['filename']);
        if (!$file) {
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        try {
            $image = Image::make($file->tmpName);
        } catch (Exception $e) {
            Log::error('Cant create image resource: ' . $e->getMessage());
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        if (!$this->processImage($image, $file, $data['sizes'])) {
            Log::error('-- End thumbnail work --');
            $response->setStatusCode(400);
            return $response;
        }

        $this->endProfiling($start);

        Log::info('-- End thumbnail work --');
        return $response;
    }

    /**
     * Process all sizes in the data message
     *
     * @param \Intervention\Image\Image $image
     * @param $file
     * @param array $sizes
     * @return bool|string
     */
    protected function processImage(\Intervention\Image\Image $image, $file, array $sizes)
    {
        $name        = $file->name;
        $meta        = $file->meta;
        $contentType = $file->contenttype;
        $files       = array();
        $temporalFiles = array();
        $this->debug(count($sizes) . ' Sizes to create');

        $image->encode($this->outputExtension);
        $counter = 1;
        $image->backup();
        foreach ($sizes as $suffix => $size) {
            $this->debug('Creating image thumbnail and added to queue: ' . $counter);
            $newName = $this->formatNewName($name, $suffix);

            // add callback functionality to retain maximal original image size
            $image->resize($size['width'], $size['height'], function ($constraint) {
                /**
                 * @var Intervention\Image\Constraint $constraint
                 */
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Save new thumbnail on a temporary file
            $tmpFileInfo = pathinfo($file->tmpName);
            $newTmpFile = $tmpFileInfo['dirname'] . '/' . $suffix . '_' . $tmpFileInfo['filename'];
            $newTmpFile = $newTmpFile . '.' . $this->outputExtension;
            $image->save($newTmpFile);
            $image->reset();

            // Add thumbnail to upload queue
            $files[] = array(
                'source'       => $newTmpFile,
                'destination'  => $newName,
                'content_type' => $contentType,
                'meta'         => $meta
            );
            $temporalFiles[] = $newTmpFile;
            $counter++;
        }

        try {
            $this->debug('--- Initializing upload ---');
            $this->putFiles($files);

            Log::info('Done Uploading!');
            $this->debug('-> Removing temp file');
            foreach ($temporalFiles as $toRemove) {
                unlink($toRemove);
                $this->debug('Removed file: ' . $toRemove);
            }
            unlink($file->tmpName);
            $image->destroy();
        } catch (\Exception $e) {
            $this->debug('Remove previous files in this task');
            foreach ($temporalFiles as $toRemove) {
                unlink($toRemove);
                $this->debug('Removed file: ' . $toRemove);
            }
            $this->debug('-> Removing temp file');
            unlink($file->tmpName);
            $image->destroy();

            Log::error($e->getMessage());
            return false;
        }

        // No errors
        return true;
    }

    /**
     * Get a file from the s3 bucket
     *
     * @param $file
     * @return bool|stdClass
     */
    public function getFile($file)
    {
        $tmpDir  = is_dir($this->tmpPath) ? $this->tmpPath : sys_get_temp_dir();
        $this->debug('Temp dir is: ' . $tmpDir);
        $tmpFile = tempnam($tmpDir, 'imgs-s3-');
        $f       = fopen($tmpFile, 'w');

        try {
            /**
             * @var Guzzle\Service\Resource\Model $result
             */
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $file,
                'SaveAs' => $f
            ]);
            /**
             * @var Guzzle\Http\EntityBody $body
             */
            $body   = $result['Body'];
            $this->debug('Fetched object to location: ' . $body->getUri());
            fclose($f);
        } catch (\Exception $e) {
            fclose($f);
            unlink($tmpFile);
            Log::error('File "' . $file . '" could not be downloaded with message: ' . $e->getMessage());

            return false;
        }

        $object              = new \stdClass();
        $object->tmpName     = $tmpFile;
        $object->name        = $file;
        $object->meta        = $result['Metadata'];
        $object->contenttype = $result['ContentType'];

        return $object;
    }

    /**
     * Stores all file thumbnails back to the s3 bucket
     *
     * @param array $files
     * @return bool
     * @throws Exception
     */
    public function putFiles($files = array())
    {
        $this->debug(count($files) . ' files in queue to upload');

        if ($owner = Config::get('app.paperlast.owner', false)) {
            $acpBuilder = \Aws\S3\Model\AcpBuilder::newInstance();
            $acpBuilder->setOwner($owner)
                ->addGrantForGroup(\Aws\S3\Enum\Permission::READ, \Aws\S3\Enum\Group::AUTHENTICATED_USERS);

            $acp = $acpBuilder->build();
        }

        $keys = array();
        $error = '';
        foreach ($files as $file) {
            $this->debug(
                '[LINE:' . __LINE__ . '] Uploading file to key: ' . $this->bucket . '/' . $file['destination']
            );
            $info = array(
                'Bucket'     => $this->bucket,
                'Key'        => $file['destination'],
                'SourceFile' => $file['source'],
                'ContentType'=> 'image/jpg',
            );

            if (isset($file['meta'])) {
                $info['Meta'] = $file['meta'];
            }
            if (isset($acp)) {
                $info['ACP'] = $acp;
            }

            $result = $this->s3Client->putObject($info);
            if (isset($result['ETag'])) {
                $this->debug('[LINE:' . __LINE__ . '] File with ETag: ' . $result['ETag'] . ' stored on S3');
                $keys[]['Key'] = $file['destination'];
            } else {
                Log::error('[LINE:' . __LINE__ . '] ERROR Failed to upload ' . $file['destination']);
                if (count($keys)) {
                    $this->debug('[LINE:' . __LINE__ . '] Removing previously uploaded files');
                    $this->s3Client->deleteObjects(array(
                        'Bucket' => $this->bucket,
                        'Objects'=> $keys
                    ));
                }
                $error = 'There was an error when uploading the files to the S3 bucket';
                break;
            }
        }
        if (!empty($error)) {
            throw new Exception($error);
        }

        return true;
    }

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
     * @param $params
     * @return bool|string
     */
    protected function validateParams($params)
    {
        if (!isset($params['sizes']) || !count($params['sizes'])) {
            return 'You must define at least one image size to process';
        }

        foreach ($params['sizes'] as $key => $size) {
            if (!is_string($key)) {
                return 'Size key must be a string';
            }
        }

        return false;
    }

    /**
     * @param $data
     * @return \Illuminate\Validation\Validator
     */
    protected function validator($data)
    {
        $rules = [
            'storage_options' => ['required', 'array'],
            'filename' => ['required'],
            'sizes' => ['required', 'array']
        ];

        $validator = Validator::make($data, $rules);

        return $validator;
    }

    /**
     * @return mixed
     */
    protected function startProfiling()
    {
        if (!Config::get('app.paperlast.profile', false)) {
            return null;
        }

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $starttime = $mtime;

        Log::info('[PROFILE] START MEMORY: ' . memory_get_usage());

        return $starttime;
    }

    protected function endProfiling($starttime)
    {
        if (!Config::get('app.paperlast.profile', false)) {
            return null;
        }

        Log::info('[PROFILE] END MEMORY: ' . memory_get_usage());
        Log::info('[PROFILE] PEAK MEMORY: ' . memory_get_peak_usage(true));

        $mtime = microtime();
        $mtime = explode(" ", $mtime);
        $mtime = $mtime[1] + $mtime[0];
        $endtime = $mtime;
        $totaltime = ($endtime - $starttime);
        Log::info('[PROFILE] JOB EXECUTED IN ' . $totaltime . ' SECONDS');
    }

    /**
     * @param $msg
     */
    public function debug($msg)
    {
        if (Config::get('app.debug', false)) {
            Log::debug($msg);
        }
    }
}
