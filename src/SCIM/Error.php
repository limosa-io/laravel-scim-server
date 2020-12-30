<?php

namespace ArieTimmerman\Laravel\SCIMServer\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Error implements Jsonable
{
    protected $detail;
    protected $status;
    protected $scimType;
    protected $errors;

    public function toJson($options = 0)
    {
        return json_encode(
            array_filter(
                [
                "schemas" => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                "detail" => $this->detail,
                "status" => $this->status,
                "scimType" => ($this->status == 400 ? $this->scimType : null),

                // not defined in SCIM 2.0
                'errors' => $this->errors
                ]
            ),
            $options
        );
    }

    public function __construct($detail, $status = "404", $scimType = "invalidValue")
    {
        $this->detail = $detail;
        $this->status = $status;
        $this->scimType = $scimType;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;

        return $this;
    }
}
