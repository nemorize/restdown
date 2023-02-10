<?php

namespace App\Controllers;

use App\Services\PostService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class PostController
{
    public function __construct (
        private readonly PostService $postService
    ) {}

    /**
     * Get multiple posts.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function getPosts (ServerRequest $request, Response $response): Response
    {
        $offset = $request->getQueryParam('offset', 0);
        $limit = $request->getQueryParam('limit', 10);
        $query = $request->getQueryParam('query', null);

        $posts = $this->postService->getPosts($offset, $limit, $query);
        return $response->withJson($posts);
    }

    /**
     * Get a post by its slug.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @param array $params
     * @return Response
     */
    public function getPost (ServerRequest $request, Response $response, array $params = []): Response
    {
        $slug = $params['slug'] ?? null;
        if ($slug === null) {
            return $response->withStatus(400);
        }

        $post = $this->postService->getPost($slug);
        if ($post === null) {
            return $response->withStatus(404);
        }

        return $response->withJson($post);
    }
}