<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 21:35
 */

namespace App\File;
use Illuminate\Log\Writer;

/**
 * Class AbstractFileProvider
 * @package App\File
 */
abstract class AbstractFileProvider implements FileProviderInterface
{

    /**
     * @var Writer
     */
    public $log;

    /**
     * @var \stdClass
     */
    protected $file;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->file = new \stdClass();

        $this->file->tmpName     = null;
        $this->file->name        = null;
        $this->file->size        = null;
        $this->file->meta        = null;
        $this->file->contenttype = null;
        $this->file->childrens   = null;
    }

    /**
     * Discards a file and all generated child files
     */
    public function discardFile()
    {
        // If tile object hasnt been set up
        if (!is_object($this->file)) {
            return;
        }

        // Attempt to discard the file
        if (file_exists($this->file->tmpName)) {
            unlink($this->file->tmpName);
        }

        // Check if file has generated child files
        if (is_array($this->file->childrens) && count($this->file->childrens) > 0) {
            // Discard child files recursively
            foreach ($this->file->childrens as $childFile) {
                if (file_exists($childFile->tmpName)) {
                    unlink($childFile->tmpName);
                }
            }
        }

        return;
    }

    /**
     * @param Writer $logger
     */
    public function setLog(Writer $logger)
    {
        $this->log = $logger;
    }
}
