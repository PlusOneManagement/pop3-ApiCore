<?php


namespace Core\Boot\Providers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

        $self = $this;
        Builder::macro('getList', function (Model $model = null) use ($self) {
            return $self->returnExpectedResponse($model ?: $this);
        });

        Builder::macro('toList', function (Model $model = null) use ($self) {
            return $self->returnExpectedResponse($model ?: $this);
        });

        Builder::macro('toList', function (Collection $collection = null) use ($self) {
            return $self->returnExpectedCollection($collection ?: $this);
        });
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
