<?php

namespace ArieTimmerman\Laravel\SCIMServer\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class SCIMException extends Exception
{
    protected $scimType = "invalidValue";
    protected $httpCode = 404;

    protected $errors = [];
    
    public function __construct($message)
    {
        parent::__construct($message);
    }
    
    public function setScimType($scimType) : SCIMException
    {
        $this->scimType = $scimType;
        
        return $this;
    }
    
    public function setCode($code) : SCIMException
    {
        $this->httpCode = $code;
        
        return $this;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;

        return $this;
    }
    
    public function report()
    {
        Log::debug(sprintf("Validation failed. Errors: %s\n\nMessage: %s\n\nBody: %s", json_encode($this->errors, JSON_PRETTY_PRINT), $this->getMessage(), request()->getContent()));
    }

    
    public function render($request)
    {
        return response((new \ArieTimmerman\Laravel\SCIMServer\SCIM\Error($this->getMessage(), $this->httpCode, $this->scimType))->setErrors($this->errors), $this->httpCode);
    }
}
