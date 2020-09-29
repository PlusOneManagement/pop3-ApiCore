<?php

namespace Core\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;

class ReturnJsonResponse
{
    /**
    * The Response Factory our app uses
    *
    * @var ResponseFactory
    */
    protected $factory;

    /**
    * JsonMiddleware constructor.
    *
    * @param ResponseFactory $factory
    */
    public function __construct(ResponseFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
    * Handle an incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @return mixed
    */
    public function handle($request, Closure $next)
    {
        // First, set the header so any other middleware knows we're
        // dealing with a should-be JSON response.
        $request->headers->set('Accept', 'application/json');

        // Get the response
        $response = $next($request);

        // If the response is not strictly a JsonResponse, we make it
        if (!$request->wantsJson()) {
            $response = $this->factory->json(
                $response->content(),
                $response->status(),
                $response->headers->all()
            );
        }

        // return the JsonResponse
        return $response;
    }
}
