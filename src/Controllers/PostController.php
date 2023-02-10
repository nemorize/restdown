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
        $query = $request->getQueryParam('query');

        if (filter_var($offset, FILTER_VALIDATE_INT) === false || (int) $offset < 0) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'validation_failed',
                'detail' => [
                    'offset' => 'min [0]'
                ]
            ]);
        }

        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1 || (int) $limit > 100) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'validation_failed',
                'detail' => [
                    'limit' => 'between [1, 100]'
                ]
            ]);
        }

        $posts = $this->postService->getPosts($offset, $limit, $query);
        return $response->withJson([
            'success' => true,
            'posts' => $posts
        ]);
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
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'slug_missing'
            ]);
        }

        $post = $this->postService->getPost($slug);
        if ($post === null) {
            return $response->withStatus(404)->withJson([
                'success' => false,
                'message' => 'post_not_found'
            ]);
        }

        return $response->withJson([
            'success' => true,
            'post' => $post
        ]);
    }
}