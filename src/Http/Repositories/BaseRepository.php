<?php

namespace Core\Http\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BaseRepository
{
    use HandlesResponse;

    public function __construct()
    {
        $this->setResult('status', 201);
        $this->setResult('message', null);
    }

    /**
     * @param Request $request
     * @param Model $Model
     * @return object
     */
    public function updateOne($input, Model $Model, $save = true)
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $arrayData = (array)$Model->$key;
                foreach ($value as $k => $val) {
                    $arrayData[$k] = $val;
                }
                $input[$key] = $arrayData;
            }
        }
        $Model->fill($input);

        if ($save) {
            $Model->save();
        }
        return $Model;
    }

    public function createNew($requestData, Model $model)
    {
//        $Model = new $model;

//        if(is_array(Arr::first($requestData))){
//            foreach($requestData as $key => $data){
//                $Model->$k = $model->create($data);
//            }
//        } else {
//            $Model = $model->create($requestData);
//        }
        return $model->create($requestData);
    }

    /**
     * @param Request $request
     * @param Model $model
     * @return mixed
     */
    public function create(Request $request, Model $model)
    {
        try {
            $this->data = $this->createNew($request->all(), $model);

            $this->setResult([
                'message' => __('Successfully created new record(s)'),
                'data' => $this->data,
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    /**
     * @param Request $request
     * @param Model $model
     * @return object
     */
    public function update(Request $request, Model $model)
    {
        try {
            $input = $request->except('orphaned_at', 'deleted_at');
            $this->data = $this->updateOne($input, $model);
                $this->setResult([
                'status' => 201,
                'message' => __('Successfully updated new records!'),
                'data' => Arr::except($this->data->toArray(), 'fields'),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    /**
     * @param Request $request
     * @param Model $model
     */
    public function delete(Request $request, Model $model)
    {
        try {
            if ($request->filled('id')) {
                $IDs = (array) $request->id;
                $model->find($IDs)->delete();
            } else {
                $model->delete();
            }
            $this->data = $model;
                $this->setResult([
                'message' =>  __('Successfully archived record(s)'),
                'data' => $this->data->toArray(),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    /**
     * @param Request $request
     * @param $Model
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, Model $Model)
    {
        try {
            $Model->restore();
            $this->setResult([
                'status' => 201,
                'message' => __('Successfully restored archived record(s)!'),
                'data' => Arr::except($Model->toArray(), 'fields'),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    public function destroy(Request $request, Model $model)
    {
        try {
            if ($request->filled('id')) {
                $IDs = (array) $request->id;
                foreach ($model->find($IDs) as $m) {
                    $m->forceDelete();
                }
            } else {
                $model->forceDelete();
            }
            $this->setResult([
                'message' =>  __('Permanently deleted record(s)'),
                'data' => $model->toArray(),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    /**
     * @param Request $request
     * @param Model $model
     */
    public function orphan(Request $request, Model $model)
    {
        try {
            if ($request->filled('id')) {
                $IDs = (array) $request->id;
                foreach ($model->find($IDs) as $m) {
                    $m->orphan();
                }
            } else {
                $model->orphan();
            }
            $this->setResult([
                'message' =>  __('Successfully orphaned record(s)'),
                'data' => $model->toArray(),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    /**
     * @param Request $request
     * @param $Model
     * @return \Illuminate\Http\JsonResponse
     */
    public function deOrphan(Request $request, Model $model)
    {
        try {
            if ($request->filled('id')) {
                $IDs = (array) $request->id;
                foreach ($model->find($IDs) as $m) {
                    $m->deOrphan();
                }
            } else {
                $model->deOrphan();
            }
            $this->setResult([
                'message' =>  __('Successfully restored orphaned record(s)'),
                'data' => $model->toArray(),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }

    public function bulkUpdate(Request $request, Collection $Records)
    {
        try {
            $request->validate([
                'id' => 'required'
            ]);

            $except = ['id', 'archived', 'orphaned_at'];

            if ($request->has('archived')) {
                $deleted_at = $request->boolean('archived')? now(): null;
                $request->merge(compact('deleted_at'));
            }
            foreach ($Records as $record) {
                $record->fill($request->except($except))->save();
            }

            $this->setResult([
                'status' => 201,
                'message' => __('Successfully performed bulk update on record'),
                'data' => Arr::except($Records->toArray(), ['fields']),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }

        return $this->getResult();
    }


    public function bulkDelete(Request $request, $model)
    {
        try {
            $request->validate([
                'id' => 'required'
            ]);

            if ($model instanceof Builder) {
                $model->delete();
            } else {
                $model->delete((array)$request->id);
            }

            $this->setResult([
                'message' =>  __('Successfully deleted record(s)'),
                'data' => $model->toArray(),
            ]);
            //
        } catch (\Exception $ex) {
            $this->setException($ex);
        }
        return $this->getResult();
    }
}
