<?php namespace App\Commands;

use App\Commands\Command;

class ThumbnailJobCommand extends Command
{
    /**
     * @var \Intervention\Image\Image
     */
    public $image;

    public $file;

    public $fileName;

    /**
     * @var array
     */
    public $sizes;

    /**
     * Create a new command instance.
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

}
