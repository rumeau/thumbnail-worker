<?php namespace App\File;

use Aws\S3\Enum\Group;
use Aws\S3\Enum\Permission;
use Aws\S3\Model\AcpBuilder;

/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 1:54
 */

class S3Provider extends AbstractFileProvider
{
    /**
     * @var string
     */
    protected $tmpPath;

    /**
     * @var \Aws\S3\S3Client
     */
    protected $s3Client;

    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->s3Client = \AWS::get('s3');
        $this->bucket   = isset($data['storage_options']['bucket']) ? $data['storage_options']['bucket'] : false;
        $this->tmpPath  = storage_path() . config('work.tmp_path');
    }

    /**
     * Get a file from the s3 bucket
     *
     * @param string $file
     * @param array $bucket
     * @return bool|\stdClass
     */
    public function getFile($file, $bucket = [])
    {
        if (is_array($bucket)) {
            $bucket = array_shift($bucket);
        }

        $tmpDir  = is_dir($this->tmpPath) ? $this->tmpPath : sys_get_temp_dir();
        $this->log->debug('Temp dir is: ' . $tmpDir);
        // Generate a tmp name for s3 file being downloaded
        $tmpFile = tempnam($tmpDir, 'imgs-s3-');
        $f       = fopen($tmpFile, 'w');


        try {
            /**
             * @var \Guzzle\Service\Resource\Model $result
             */
            $result = $this->s3Client->getObject([
                'Bucket' => $bucket,
                'Key'    => $file,
                'SaveAs' => $f
            ]);
            /**
             * @var \Guzzle\Http\EntityBody $body
             */
            $body   = $result['Body'];
            $this->log->debug('Fetched object to location: ' . $body->getUri());
            fclose($f);
        } catch (\Exception $e) {
            // Close the resource and delete the temp file
            fclose($f);
            unlink($tmpFile);
            $this->log->error('File "' . $file . '" could not be downloaded with message: ' . $e->getMessage());

            return false;
        }

        $this->file->tmpName     = $tmpFile;
        $this->file->name        = $file;
        $this->file->size        = $result['ContentLength'];
        $this->file->meta        = $result['Metadata'];
        $this->file->contenttype = $result['ContentType'];

        return $this->file;
    }

    /**
     * Stores all file thumbnails back to the s3 bucket
     *
     * @param array $files
     * @param string $bucket
     * @return bool
     * @throws \Exception
     */
    public function putFiles($files, $bucket)
    {
        $this->log->debug(count($files) . ' files in queue to upload');

        // If owner has been set, create the ACP instance for it
        if ($owner = config('work.owner', false)) {
            $acpBuilder = AcpBuilder::newInstance();
            $acpBuilder->setOwner($owner)
                ->addGrantForGroup(Permission::READ, Group::AUTHENTICATED_USERS);

            $acp = $acpBuilder->build();
        }

        $keys = array();
        $error = '';
        foreach ($files as $file) {
            $this->log->debug(
                '[LINE:' . __LINE__ . '] Uploading file to key: ' . $bucket . '/' . $file['destination']
            );
            $info = [
                'Bucket'        => $bucket,
                'Key'           => $file['destination'],
                'SourceFile'    => $file['source'],
                'ContentType'   => $file['content_type'],
            ];

            // Copy meta information from original
            if (isset($file['meta'])) {
                $info['Meta'] = $file['meta'];
            }
            // Set ACP to resource
            if (isset($acp)) {
                $info['ACP'] = $acp;
            }

            // Save the file to S3
            $result = $this->s3Client->putObject($info);
            // If object saved
            if (isset($result['ETag'])) {
                $this->log->debug('[LINE:' . __LINE__ . '] File with ETag: ' . $result['ETag'] . ' stored on S3');
                $keys[]['Key'] = $file['destination'];
            } else {
                // If error, delete previous objects from this batch
                $this->log->error('[LINE:' . __LINE__ . '] ERROR Failed to upload ' . $file['destination']);
                if (count($keys)) {
                    $this->log->debug('[LINE:' . __LINE__ . '] Removing previously uploaded files');
                    $this->s3Client->deleteObjects([
                        'Bucket'  => $this->bucket,
                        'Objects' => $keys
                    ]);
                }
                $error = 'There was an error when uploading the files to the S3 bucket';
                break;
            }
        }
        if (!empty($error)) {
            throw new \Exception($error);
        }

        return true;
    }
}
