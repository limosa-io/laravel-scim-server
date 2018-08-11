<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\PolicyDecisionPoint;

class MeController extends ResourceController{

    protected function isAllowed(PolicyDecisionPoint $pdp, Request $request, $operation, array $attributes, ResourceType $resourceType, ?Model $resourceObject){
        
        return $pdp->isAllowed($request, PolicyDecisionPoint::OPERATION_POST, $attributes, $resourceType, null, true);
        

    }

    public function createMe(Request $request, PolicyDecisionPoint $pdp){

        $resourceType = ResourceType::user();

        return parent::create($request, $pdp, $resourceType);
    }

    public function getMe(Request $request, PolicyDecisionPoint $pdp){

        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();

        return Helper::objectToSCIMArray( $class::find($subject->getUserId()) , $resourceType);
        
    }

    public function replaceMe(Request $request, PolicyDecisionPoint $pdp){

        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();
        
        return parent::replace($request, $pdp, $resourceType, $class::find($subject->getUserId()));

    }

    public function updateMe(Request $request, PolicyDecisionPoint $pdp){

        $resourceType = ResourceType::user();
        $class = $resourceType->getClass();
        $subject = $request->user();
        
        return parent::update($request, $pdp, $resourceType, $class::find($subject->getUserId()));

    }






}