<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Error implements Jsonable
{
    protected $errors;

    public function toJson($options = 0)
    {
        return json_encode(
            array_filter(
                [
                "schemas" => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                "detail" => $this->detail,
                "status" => $this->status,
                "scimType" => ($this->status == 400 ? $this->scimType : ($this->status == 409 ? 'uniqueness' : null)),

                // not defined in SCIM 2.0
                'errors' => $this->errors
                ]
            ),
            $options
        );
    }

    public function __construct(protected $detail, protected $status = "404", protected $scimType = "invalidValue")
    {
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;

        return $this;
    }
}
