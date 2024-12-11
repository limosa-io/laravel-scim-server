<?php

namespace ArieTimmerman\Laravel\SCIMServer\Attribute;

use ArieTimmerman\Laravel\SCIMServer\Exceptions\SCIMException;
use ArieTimmerman\Laravel\SCIMServer\Parser\Path;
use Illuminate\Database\Eloquent\Model;

class MutableCollection extends Collection
{
    public function add($value, Model &$object)
    {
        $values = collect($value)->pluck('value')->all();

        // Check if objects exist
        $existingObjects = $object
            ->{$this->attribute}()
            ->getRelated()
            ->findMany($values)
            ->map(fn ($o) => $o->getKey());

        if (($diff = collect($values)->diff($existingObjects))->count() > 0) {
            throw new SCIMException(
                sprintf('One or more %s are unknown: %s', $this->attribute, implode(',', $diff->all())),
                500
            );
        }

        $object->{$this->attribute}()->syncWithoutDetaching($existingObjects->all());

        $object->load($this->attribute);
    }

    public function remove($value, Model &$object, Path $path = null)
    {
        $values = collect($value)->pluck('value')->all();

        $object->{$this->attribute}()->detach($values);

        $object->load($this->attribute);
    }

    public function replace($value, Model &$object, ?Path $path = null)
    {
        $values = collect($value)->pluck('value')->all();

        // Check if objects exist
        $existingObjects = $object
        ->{$this->attribute}()
        ->getRelated()
        ::findMany($values);
        $existingObjectIds = $existingObjects
            ->map(fn ($o) => $o->getKey());

        if (($diff = collect($values)->diff($existingObjectIds))->count() > 0) {
            throw new SCIMException(
                sprintf('One or more %s are unknown: %s', $this->attribute, implode(',', $diff->all())),
                500
            );
        }

        // Act like the relation is already saved. This allows running validations, if needed.
        $object->setRelation($this->attribute, $existingObjects);

        $object->saved(function (Model $model) use ($object, $existingObjectIds)  {
            // Save relationships only after the model is saved. Essential if the model is new.
            // Intentionlly `$object` is used instead of `$model`, to avoid accidentially updating the wrong model.
            $object->{$this->attribute}()->sync($existingObjectIds->all());    
        });
        
    }

    public function patch($operation, $value, Model &$object, ?Path $path = null)
    {
        if ($operation == 'add') {
            $this->add($value, $object);
        } elseif ($operation == 'remove') {
            $this->remove($value, $object, $path);
        } elseif ($operation == 'replace') {
            $this->replace($value, $object, $path);
        } else {
            throw new SCIMException('Operation not supported: ' . $operation);
        }
    }
}
