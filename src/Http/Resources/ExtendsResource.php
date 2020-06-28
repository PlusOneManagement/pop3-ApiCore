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
        foreach(explode(",", $request->with) as $with){
            $resource[$with] = $this->$with;
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
        foreach($resource as $k => $val){
            if(!Str::endsWith($k, '_id')){
                $resource[$k] = $val;
                continue;
            }
            $with = (string)Str::of($k)->before('_id')->camel()->ucfirst();
            $resource[$with] = $this->$with;
        }
        return $resource;
    }
}