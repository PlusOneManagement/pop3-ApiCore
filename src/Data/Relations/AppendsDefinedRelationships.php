<?php

namespace Core\Data\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 *
 */
trait AppendsDefinedRelationships
{

    public function bootAppendsDefinedRelationships()
    {

    }

    public function getRelationshipsAttribute()
    {
        $relationships = new Collection();

        $resource = $this->resource ?: $this->toArray();

        foreach ($resource as $field => $value) {
            if (Str::contains($field, '_id')) {

                $name = (string)Str::of($field)->before('_id')->camel()->lower();

                $relation = $this->$name ?? null;
                if($relation){
                    $relationships->$name = $relation ?? [];
                }

                $relations = $this->{$name = Str::plural($name)} ?? null;
                if($relations){
                    $relationships->$name = $relations ?? [];
                }
            }
        }
        return $relationships;
    }
}
