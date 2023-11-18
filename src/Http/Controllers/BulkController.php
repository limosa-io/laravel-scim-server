<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkController extends Controller
{

    const MAX_PAYLOAD_SIZE = 1048576;
    const MAX_OPERATIONS = 10;

    /**
     * Process SCIM BULK requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processBulkRequest(Request $request)
    {

        // get the content size in bytes from raw content (not entirely accurate, but good enough for now)
        $contentSize = mb_strlen($request->getContent(), '8bit');

        if($contentSize > static::MAX_PAYLOAD_SIZE){
            throw (new SCIMException('Payload too large!'))->setCode(413)->setScimType('tooLarge');
        }

        $validator = Validator::make($request->input(), [
            'schemas' => 'required|array',
            'schemas.*' => 'required|string|in:urn:ietf:params:scim:api:messages:2.0:BulkRequest',
            'Operations' => 'required|array',
            'Operations.*.method' => 'required|string|in:POST,PUT,PATCH,DELETE',
            'Operations.*.path' => 'required|string|in:/Users,/Groups',
            'Operations.*.bulkId' => 'nullable|string',
            'Operations.*.data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $e = $validator->errors();

            throw (new SCIMException('Invalid data!'))->setCode(400)->setScimType('invalidSyntax')->setErrors($e);
        }

        $operations = $request->input('Operations');

        if(count($operations) > static::MAX_OPERATIONS){
            throw (new SCIMException('Too many operations!'))->setCode(413)->setScimType('tooLarge');
        }

        $bulkIdMapping = [];
        $responses = [];

        // Remove everything till the last occurence of Bulk, e.g. /scim/v2/Bulk should become /scim/v2/
        $prefix = substr($request->path(), 0, strrpos($request->path(), '/Bulk'));

        foreach ($operations as $operation) {
            
            $method = $operation['method'];
            $bulkId = $operation['bulkId'] ?? null;

            // Call internal Laravel route based on method, path and data
            $encoded = json_encode($operation['data'] ?? []);
            $encoded = str_replace(array_keys($bulkIdMapping), array_values($bulkIdMapping), $encoded);

            $request = Request::create(
                $prefix . $operation['path'],
                $operation['method'], 
                server: [
                    'HTTP_Authorization' => $request->header('Authorization'),
                    'CONTENT_TYPE' => 'application/scim+json',
                ],
                content: $encoded
            );

            // run request and get response
            /** @var \Illuminate\Http\Response */
            $response = app()->handle($request);
            // Get the JSON content of the response
            $jsonContent = $response->getContent();
            // Decode the JSON content
            $responseData = json_decode($jsonContent, false);

            // Store the id attribute
            $id = $responseData?->id ?? null;

            // Store the id attribute in the bulkIdMapping array
            if ($bulkId !== null && $id !== null) {
                $bulkIdMapping['bulkId:' . $bulkId] = $id;
            }

            $responses[] = array_filter([
                "location" => $responseData?->meta?->location ?? null,
                "method" => $method,
                "bulkId" => $bulkId,
                "version" => $responseData?->meta?->version ?? null,
                "status" => $response->getStatusCode(),
                "response" => $response->getStatusCode() >= 400 ? $responseData : null,
            ]);
        }

        // Return a response indicating the successful processing of the SCIM BULK request
        return response()->json(
            [
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkResponse'],
                'Operations' => 
                    $responses])->setStatusCode(200)
                        ->withHeaders(['Content-Type' => 'application/scim+json']);
    }
}
