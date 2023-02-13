<?php

namespace App\Controllers;

use App\Services\TagService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class TagController
{
    public function __construct (
        private readonly TagService $tagService
    ) {}

    /**
     * Get all categories.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function getTags (ServerRequest $request, Response $response): Response
    {
        $categories = $this->tagService->getTags();

        return $response->withJson([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get a tag.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @param array $params
     * @return Response
     */
    public function getTag (ServerRequest $request, Response $response, array $params): Response
    {
        $name = $params['tag'] ?? null;
        if ($name === null) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'name_missing'
            ]);
        }

        $tag = $this->tagService->getTag($name);
        if ($tag === null) {
            return $response->withStatus(404)->withJson([
                'success' => false,
                'message' => 'tag_not_found'
            ]);
        }

        return $response->withJson([
            'success' => true,
            'tag' => $tag
        ]);
    }

    /**
     * Get multiple posts from a tag.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @param array $params
     * @return Response
     */
    public function getTagPosts (ServerRequest $request, Response $response, array $params): Response
    {
        $name = $params['tag'] ?? null;
        if ($name === null) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'name_missing'
            ]);
        }

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

        $output = $this->tagService->getTagPosts($name, $offset, $limit, $query);
        return $response->withJson([
            'success' => true,
            'totalCount' => $output->count,
            'posts' => $output->posts
        ]);
    }
}