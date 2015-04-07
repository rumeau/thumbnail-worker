<?php namespace App\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Validator;
use Monolog\Logger;

class Thumbnail extends Request
{

    protected $log;

    public function __construct(Logger $logger)
    {
        $this->log = $logger;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'storage_options' => ['required', 'array'],
            'filename' => ['required'],
            'jobs' => ['required', 'array']
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $this->log->error($validator->messages());
        $this->log->error('-- End thumbnail work --');

        return response($validator->messages(), 400);
    }
}
