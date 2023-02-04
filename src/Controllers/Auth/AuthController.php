<?php

namespace App\Controllers\Auth;

use App\Core\Http\Validator;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class AuthController
{
    public function __construct (
        private readonly Validator $validator
    ) {}

    /**
     * GET /auth/authenticate
     *
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function getAuthenticate (ServerRequest $request, Response $response): Response
    {
        $data = $this->validator->get($request, [
            'email_address' => 'required|email',
            'password' => 'required|min:8'
        ]);

        return $response->withJson($data);
    }
}