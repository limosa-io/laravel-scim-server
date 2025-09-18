<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig;
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

        $originalRequest = $request;

        // get the content size in bytes from raw content (not entirely accurate, but good enough for now)
        $contentSize = mb_strlen($originalRequest->getContent(), '8bit');

        if($contentSize > static::MAX_PAYLOAD_SIZE){
            throw (new SCIMException('Payload too large!'))->setCode(413)->setScimType('tooLarge');
        }

        $validator = Validator::make($originalRequest->input(), [
            'schemas' => 'required|array',
            'schemas.*' => 'required|string|in:urn:ietf:params:scim:api:messages:2.0:BulkRequest',
            'failOnErrors' => 'nullable|int',
            'Operations' => 'required|array',
            'Operations.*.method' => 'required|string|in:POST,PUT,PATCH,DELETE',
            'Operations.*.bulkId' => 'nullable|string',
            'Operations.*.data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $e = $validator->errors();

            throw (new SCIMException('Invalid data!'))->setCode(400)->setScimType('invalidSyntax')->setErrors($e);
        }

        $operations = $originalRequest->input('Operations');

        if(count($operations) > static::MAX_OPERATIONS){
            throw (new SCIMException('Too many operations!'))->setCode(413)->setScimType('tooLarge');
        }

        $bulkIdMapping = [];
        $responses = [];
        $errorCount = 0;

        $failOnErrors = $originalRequest->input('failOnErrors');
        $failOnErrors = is_numeric($failOnErrors) ? (int)$failOnErrors : null;

        if ($failOnErrors !== null && $failOnErrors < 1) {
            $failOnErrors = null;
        }

        // Remove everything till the last occurence of Bulk, e.g. /scim/v2/Bulk should become /scim/v2/
        $prefix = substr($originalRequest->path(), 0, strrpos($originalRequest->path(), '/Bulk'));

        $resourceTypeConfig = resolve(SCIMConfig::class)->getConfig();
        $resourceTypePattern = null;

        if (!empty($resourceTypeConfig)) {
            $escapedResourceTypes = array_map(static fn ($name) => preg_quote($name, '/'), array_keys($resourceTypeConfig));
            $resourceTypePattern = '/^\/(' . implode('|', $escapedResourceTypes) . ')(?:\/|\?|$)/';
        }

        foreach ($operations as $index => $operation) {
            
            $method = $operation['method'];
            $bulkId = $operation['bulkId'] ?? null;
            $data = $operation['data'] ?? [];

            if (!is_array($data)) {
                $data = [];
            }

            // Call internal Laravel route based on method, path and data
            $encoded = json_encode($data);
            $encoded = str_replace(array_keys($bulkIdMapping), array_values($bulkIdMapping), $encoded);
            $path = str_replace(array_keys($bulkIdMapping), array_values($bulkIdMapping), $operation['path']);

            if ($resourceTypePattern === null || !preg_match($resourceTypePattern, $path)) {
                throw (new SCIMException('Invalid path!'))->setCode(400)->setScimType('invalidPath');
            }

            $operationRequest = Request::create(
                $prefix . $path,
                $method,
                parameters: [],
                cookies: $originalRequest->cookies->all(),
                files: [],
                server: array_replace(
                    $originalRequest->server->all(),
                    [
                        'HTTP_Authorization' => $originalRequest->header('Authorization'),
                        'CONTENT_TYPE' => 'application/scim+json',
                        'HTTP_CONTENT_TYPE' => 'application/scim+json',
                    ]
                ),
                content: $encoded
            );

            if ($originalRequest->getUserResolver()) {
                $operationRequest->setUserResolver($originalRequest->getUserResolver());
            }

            if ($originalRequest->getRouteResolver()) {
                $operationRequest->setRouteResolver($originalRequest->getRouteResolver());
            }

            // run request and get response
            /** @var \Illuminate\Http\Response */
            $response = app()->handle($operationRequest);
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

            $status = $response->getStatusCode();

            if ($status >= 400) {
                $errorCount++;
            }

            $responses[] = array_filter([
                "location" => $responseData?->meta?->location ?? null,
                "method" => $method,
                "bulkId" => $bulkId,
                "version" => $responseData?->meta?->version ?? null,
                "status" => $status,
                "response" => $status >= 400 ? $responseData : null,
            ]);

            if ($failOnErrors !== null && $errorCount >= $failOnErrors) {
                $remaining = array_slice($operations, $index + 1);

                foreach ($remaining as $remainingOperation) {
                    $responses[] = array_filter([
                        'method' => $remainingOperation['method'],
                        'bulkId' => $remainingOperation['bulkId'] ?? null,
                        'status' => 424,
                        'response' => [
                            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                            'scimType' => 'cancelled',
                            'detail' => 'Operation cancelled because failOnErrors threshold was reached.',
                        ],
                    ]);
                }

                break;
            }
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
