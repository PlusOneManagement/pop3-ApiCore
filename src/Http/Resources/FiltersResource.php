<?php

namespace Core\Http\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
*
*/
trait FiltersResource
{
    /**
     * @param $request
     * @param $resource
     * @return array
     */
    public function filterRequest($request, $resource)
    {
        if ($request->filled('select')) {
            $resource = $this->getSelected($request, $resource);
        }
        if (count($request->except('select'))) {
            $resource = $this->getSearched($request, $resource);
        }
        return $resource;
    }

    /**
     * @param $request
     * @param $resource
     * @return array
     */
    public function getSelected($request, $resource)
    {
        $selected = preg_split("#\s*[,|]\s*#msi", $request->select);
        return Arr::only($resource, $selected);
    }

    /**
     * @param $request
     * @param $resource
     * @return array
     */
    public function getSearched($request, $resource)
    {
        $searched = Arr::only($request->all(), array_keys($resource));

//        if(!count($searched)){
//            return $resource;
//        }
//
//        $resource = Arr::where($resource, function($value, $key) use($searched) {
//            return isset($searched[$key]) && $searched[$key] === $value;
//        });

        return array_filter($resource);
    }
}
