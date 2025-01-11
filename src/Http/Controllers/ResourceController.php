<?php

namespace ArieTimmerman\Laravel\SCIMServer\Http\Controllers;

use ArieTimmerman\Laravel\SCIMServer\SCIM\ListResponse;
use Illuminate\Http\Request;
use ArieTimmerman\Laravel\SCIMServer\Helper;
use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\ResourceType;
use Illuminate\Database\Eloquent\Model;
use ArieTimmerman\Laravel\SCIMServer\Events\Delete;
use ArieTimmerman\Laravel\SCIMServer\Events\Get;
use ArieTimmerman\Laravel\SCIMServer\Events\Create;
use ArieTimmerman\Laravel\SCIMServer\Events\Replace;
use ArieTimmerman\Laravel\SCIMServer\Events\Patch;
use ArieTimmerman\Laravel\SCIMServer\Parser\Parser as ParserParser;
use ArieTimmerman\Laravel\SCIMServer\PolicyDecisionPoint;
use ArieTimmerman\Laravel\SCIMServer\Tests\Model\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

class ResourceController extends Controller
{
    protected static function isAllowed(PolicyDecisionPoint $pdp, Request $request, $operation, array $attributes, ResourceType $resourceType, ?Model $resourceObject, $isMe = false)
    {
        return $pdp->isAllowed($request, $operation, $attributes, $resourceType, $resourceObject, $isMe);
    }

    protected static function validateScim(ResourceType $resourceType, $flattened, ?Model $resourceObject)
    {
        $validations = $resourceType->getValidations();

        foreach ($validations as $key => $value) {
            if (is_string($value)) {
                $validations[$key] = $resourceObject ? preg_replace('/,\[OBJECT_ID\]/', ',' . $resourceObject->id, $value) : str_replace(',[OBJECT_ID]', '', $value);
            }
        }

        $validator = Validator::make($flattened, $validations);

        if ($validator->fails()) {
            $e = $validator->errors();

            throw (new SCIMException('Invalid data!'))->setCode(400)->setScimType('invalidSyntax')->setErrors($e);
        }

        return $validator->validate();
    }

    public static function createFromSCIM($resourceType, $input, PolicyDecisionPoint $pdp = null, Request $request = null, $allowAlways = false, $isMe = false)
    {
        if (!isset($input['schemas']) || !is_array($input['schemas'])) {
            throw (new SCIMException('Missing a valid schemas-attribute.'))->setCode(400);
        }

        $flattened = Helper::flatten($input, $input['schemas']);
        $flattened = static::validateScim($resourceType, $flattened, null);

        if (!$allowAlways && !static::isAllowed($pdp, $request, PolicyDecisionPoint::OPERATION_POST, $flattened, $resourceType, null, $isMe)) {
            throw (new SCIMException('This is not allowed'))->setCode(403);
        }

        $resourceObject = $resourceType->getFactory()();

        $resourceType->getMapping()->replace($input, $resourceObject);

        //validate
        $newObject = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());

        $flattened = static::validateScim($resourceType, $newObject, $resourceObject);

        $resourceObject->save();

        return $resourceObject;
    }

    /**
     * @return Model
     */
    public function createObject(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, $isMe = false)
    {
        $input = $request->input();

        $resourceObject = static::createFromSCIM($resourceType, $input, $pdp, $request, false, $isMe);

        event(new Create($resourceObject, $resourceType, $isMe, $request->input()));

        return $resourceObject;
    }

    /**
     * Create a new scim resource
     *
     * @param  Request      $request
     * @param  ResourceType $resourceType
     * @throws SCIMException
     * @return \Symfony\Component\HttpFoundation\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function create(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, $isMe = false)
    {
        $resourceObject = $this->createObject($request, $pdp, $resourceType, $isMe);

        return Helper::objectToSCIMResponse($resourceObject, $resourceType)->setStatusCode(201);
    }

    public function show(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, Model $resourceObject)
    {
        event(new Get($resourceObject, $resourceType, null, $request->input()));

        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }

    public function delete(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, Model $resourceObject)
    {
        $resourceObject->delete();

        event(new Delete($resourceObject, $resourceType, null, $request->input()));

        return response(null, 204);
    }

    public function replace(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, Model $resourceObject, $isMe = false)
    {
        $originalRaw = Helper::objectToSCIMArray($resourceObject, $resourceType);

        $resourceType->getMapping()->replace($request->input(), $resourceObject, null, true);

        $newObject = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());

        $flattened = $this->validateScim($resourceType, $newObject, $resourceObject);

        if (!static::isAllowed($pdp, $request, PolicyDecisionPoint::OPERATION_PATCH, $flattened, $resourceType, null)) {
            throw new SCIMException('This is not allowed');
        }

        $resourceObject->save();

        event(new Replace($resourceObject, $resourceType, $isMe, $request->input(), $originalRaw));

        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }

    public function update(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType, Model $resourceObject, $isMe = false)
    {
        $input = $request->input();

        if ($input['schemas'] !== ["urn:ietf:params:scim:api:messages:2.0:PatchOp"]) {
            throw (new SCIMException(sprintf('Invalid schema "%s". MUST be "urn:ietf:params:scim:api:messages:2.0:PatchOp"', json_encode($input['schemas']))))->setCode(404);
        }

        if (isset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'])) {
            $input['Operations'] = $input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations'];
            unset($input['urn:ietf:params:scim:api:messages:2.0:PatchOp:Operations']);
        }

        $oldObject = Helper::objectToSCIMArray($resourceObject, $resourceType);

        foreach ($input['Operations'] as $operation) {
            switch (strtolower($operation['op'])) {
                case "add":
                    $resourceType->getMapping()->patch('add', $operation['value'] ?? null, $resourceObject, ParserParser::parse($operation['path'] ?? null));
                    break;

                case "remove":
                    if (isset($operation['path'])) {
                        $resourceType->getMapping()->patch('remove', $operation['value'] ?? null, $resourceObject, ParserParser::parse($operation['path'] ?? null));
                    } else {
                        throw new SCIMException('You MUST provide a "Path"');
                    }
                    break;

                case "replace":
                    $resourceType->getMapping()->patch('replace', $operation['value'], $resourceObject, ParserParser::parse($operation['path'] ?? null));
                    break;

                default:
                    throw new SCIMException(sprintf('Operation "%s" is not supported', $operation['op']));
            }
        }

        $dirty = $resourceObject->getDirty();

        $newObject = Helper::flatten(Helper::objectToSCIMArray($resourceObject, $resourceType), $resourceType->getSchema());

        $flattened = $this->validateScim($resourceType, $newObject, $resourceObject);

        if (!static::isAllowed($pdp, $request, PolicyDecisionPoint::OPERATION_PATCH, $flattened, $resourceType, null)) {
            throw new SCIMException('This is not allowed');
        }

        $resourceObject->save();

        event(new Patch($resourceObject, $resourceType, $isMe, $request->input(), $oldObject));

        return Helper::objectToSCIMResponse($resourceObject, $resourceType);
    }


    public function notImplemented(Request $request)
    {
        return response(null, 501);
    }

    public function wrongVersion(Request $request)
    {
        throw (new SCIMException('Only SCIM v2 is supported. Accessible under ' . url('scim/v2')))->setCode(501)
            ->setScimType('invalidVers');
    }

    public function index(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType)
    {
        $query = $resourceType->getQuery();

        // if both cursor and startIndex are present, throw an exception
        if ($request->has('cursor') && $request->has('startIndex')) {
            throw (new SCIMException('Both cursor and startIndex are present. Only one of them is allowed.'))->setCode(400);
        }

        // Non-negative integer. Specifies the desired maximum number of query results per page, e.g., 10. A negative value SHALL be interpreted as "0". A value of "0" indicates that no resource results are to be returned except for "totalResults".
        $count = min(max(0, intVal($request->input('count', config('scim.pagination.defaultPageSize')))), config('scim.pagination.maxPageSize'));

        $startIndex = null;
        $sortBy = null;

        if ($request->input('sortBy')) {
            $sortBy = $resourceType->getMapping()->getSortAttributeByPath(\ArieTimmerman\Laravel\SCIMServer\Parser\Parser::parse($request->input('sortBy')));
        }

        $resourceObjectsBase = $query->when(
            $filter = $request->input('filter'),
            function (Builder $query) use ($filter, $resourceType) {
                try {

                    Helper::scimFilterToLaravelQuery($resourceType, $query, ParserParser::parseFilter($filter));
                } catch (\Tmilos\ScimFilterParser\Error\FilterException $e) {
                    throw (new SCIMException($e->getMessage()))->setCode(400)->setScimType('invalidFilter');
                }
            }
        );

        $totalResults = $resourceObjectsBase->count();

        /**
         * @var \Illuminate\Database\Query\Builder $resourceObjects
         */
        $resourceObjects = $resourceObjectsBase
            ->with($resourceType->getWithRelations());

        if ($sortBy != null) {
            $direction = $request->input('sortOrder') == 'descending' ? 'desc' : 'asc';

            $resourceObjects = $resourceObjects->orderBy($sortBy, $direction);
        }

        $resources = null;
        if ($request->has('cursor')) {
            if($sortBy == null){
                $resourceObjects = $resourceObjects->orderBy('id');
            }

            if($request->input('cursor')){
                $cursor = @Cursor::fromEncoded($request->input('cursor'));

                if($cursor == null){
                    throw (new SCIMException('Invalid Cursor'))->setCode(400)->setScimType('invalidCursor');
                }
            }

            $countRaw = $request->input('count');

            if($countRaw < 1 || $countRaw > config('scim.pagination.maxPageSize')){
                throw (new SCIMException(
                    sprintf('Count value is invalid. Count value must be between 1 - and maxPageSize (%s) (when using cursor pagination)', config('scim.pagination.maxPageSize'))
                ))->setCode(400)->setScimType('invalidCount');
            }
            
            $resourceObjects = $resourceObjects->cursorPaginate(
                $count,
                cursor: $request->input('cursor')
            );
            $resources = collect($resourceObjects->items());

            
        } else {
            // The 1-based index of the first query result. A value less than 1 SHALL be interpreted as 1.
            $startIndex = max(1, intVal($request->input('startIndex', 0)));

            $resourceObjects = $resourceObjects->skip($startIndex - 1)->take($count);
            $resources = $resourceObjects->get();
        }

        // TODO: splitting the attributes parameters by dot and comma is not correct, but works in most cases
        // if body contains attributes and this is an array, use that, else use existing
        if($request->json('attributes') && is_array($request->json('attributes'))){
            $attributes = $request->json('attributes');
        } else {
            $attributes = $request->input('attributes') ? preg_split('/[,.]/', $request->input('attributes')) : [];
        }

        if (!empty($attributes)) {
            $attributes[] = 'id';
            $attributes[] = 'meta';
            $attributes[] = 'schemas';
        }

        // TODO: implement excludedAttributes
        $excludedAttributes = [];

        return new ListResponse(
            $resources,
            $startIndex,
            $totalResults,
            $attributes,
            $excludedAttributes,
            $resourceType,
            ($resourceObjects instanceof CursorPaginator) ? $resourceObjects->nextCursor()?->encode() : null,
            ($resourceObjects instanceof CursorPaginator) ? $resourceObjects->previousCursor()?->encode() : null
        );
    }

    public function search(Request $request, PolicyDecisionPoint $pdp, ResourceType $resourceType){

        $input = $request->json()->all();

        // ensure request post body is a scim SearchRequest
        if (!is_array($input) || !isset($input['schemas']) || !in_array("urn:ietf:params:scim:api:messages:2.0:SearchRequest", $input['schemas'])) {
            throw (new SCIMException('Invalid schema. MUST be "urn:ietf:params:scim:api:messages:2.0:SearchRequest"'))->setCode(400);
        }

        // ensure $request->input reads from payload/post only, not query parameters
        $request->replace($request->json()->all());

        return $this->index($request, $pdp, $resourceType);
    }
}
