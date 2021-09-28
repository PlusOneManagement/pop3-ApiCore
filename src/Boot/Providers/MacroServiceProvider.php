<?php


namespace Core\Boot\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('paginate', function ($perPage, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            return new LengthAwarePaginator(
                $this->forPage($page, $perPage),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });

        $methods = ['getList', 'toList', 'toPage'];

        foreach ($methods as $method) {
            $self = $this;

            Builder::macro($method, function (Model $model = null) use (&$self) {
                return $self->returnExpectedResponse($model ?: $this);
            });
            Collection::macro($method, function (Collection $collection = null) use (&$self) {
                return $self->returnExpectedCollection($collection ?: $this);
            });
            EloquentCollection::macro($method, function (EloquentCollection $collection = null) use (&$self) {
                return $self->returnExpectedCollection($collection ?: $this);
            });
            LazyCollection::macro($method, function (LazyCollection $collection = null) use (&$self) {
                return $self->returnExpectedCollection($collection ?: $this);
            });
        }
    }

    public function returnExpectedCollection($collection)
    {
        return ($limit = request('limit'))
            ? $collection->paginate($limit)
            : $collection;
    }

    public function returnExpectedResponse($model)
    {
        return ($limit = request('limit'))
            ? $model->paginate($limit)
            : $model->cursor();
    }
}
