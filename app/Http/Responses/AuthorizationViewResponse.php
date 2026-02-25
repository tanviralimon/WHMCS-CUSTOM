<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Laravel\Passport\Contracts\AuthorizationViewResponse as AuthorizationViewResponseContract;

class AuthorizationViewResponse implements AuthorizationViewResponseContract, Responsable
{
    /**
     * The OAuth authorization parameters.
     */
    protected array $parameters = [];

    /**
     * Create a new response instance.
     */
    public function withParameters(array $parameters = []): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        return response()->view('vendor.passport.authorize', $this->parameters);
    }
}
