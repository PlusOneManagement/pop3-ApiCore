<?php

namespace Core\Http\Resources;

use Illuminate\Support\Str;

trait ExtendsResource
{
    /**
     * @param $request
     * @param $resource
     * @return mixed
     */
    protected function getWithRelation($request, $resource)
    {
        $with = is_array($request->with) ?
            $request->with : explode(",", $request->with);

        //$resource += [$this->mergeWhen(count($with) > 0, $this->load($with))];
        if(count($with) > 0){
            $resource += $this->with($with);
        }

        return $resource;
    }

    /**
     * @param $request
     * @param $resource
     * @return mixed
     */
    protected function getAllRelations($request, $resource)
    {
        foreach ($resource as $k => $val) {
            if (!Str::endsWith($k, '_id')) {
                $resource[$k] = $val;
                continue;
            }
            $with = (string)Str::of($k)->before('_id')->camel()->ucfirst();
            $resource[$with] = $this->{Str::camel($with)};
        }
        return $resource;
    }

    /**
     * Returns extra appended data in the resource
     * @param $request
     * @param $resource
     * @return mixed
     */
    protected function getExtraFields($request, $resource)
    {
        if(!$request->filled('extra')){
            return $resource;
        }

        $extras = is_array($request->extra) ?
            $request->extra : explode(",", $request->extra);

        if(!empty($extras)){
            foreach ($extras as $extra) {
                $resource[$extra] = $this->resource->{$extra} ?? null;
            }
        }

        return $resource;
    }
}
