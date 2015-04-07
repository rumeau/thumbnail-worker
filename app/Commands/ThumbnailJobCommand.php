<?php namespace App\Commands;

use App\Commands\Command;

class ThumbnailJobCommand extends Command
{
    /**
     * @var \Intervention\Image\Image
     */
    public $image;

    /**
     * @var \stdClass
     */
    public $file;

    /**
     * @var array
     */
    public $sizes;

    /**
     * @var array
     */
    public $bucket;

    /**
	 * Create a new command instance.
	 *
     * @param \Intervention\Image\Image   $image
     * @param \stdClass  $file
     * @param string     $bucket
     * @param array      $sizes
     */
	public function __construct($image, $file, $bucket, $sizes = [])
	{
		$this->image = $image;
        $this->file  = $file;
        $this->sizes = $sizes;
        $this->bucket = $bucket;
	}

}
