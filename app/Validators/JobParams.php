<?php
namespace App\Validators;
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 1:34
 */

/**
 * Class JobParams
 * @package App\Validators
 */
class JobParams
{
    public function validator($data)
    {
        return \Validator::make($data, $this->rules());
    }

    public function rules()
    {
        return [
            'sizes'         => 'required|array|min:1',
            'sizes.width'   => 'required|min:1',
            'sizes.height'  => 'required|min:1',
            'sizes.name'    => 'sometimes',
            'sizes.quality' => 'sometimes|numeric|min:1|max:100',
            'sizes.ext'     => 'sometimes|string',
        ];
    }
}
