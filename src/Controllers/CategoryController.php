<?php

namespace App\Controllers;

use App\Services\CategoryService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class CategoryController
{
    public function __construct (
        private readonly CategoryService $categoryService
    ) {}

    /**
     * Get all categories.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function getCategories (ServerRequest $request, Response $response): Response
    {
        $categories = $this->categoryService->getCategories();

        return $response->withJson([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get a category.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @param array $params
     * @return Response
     */
    public function getCategory (ServerRequest $request, Response $response, array $params): Response
    {
        $name = $params['category'] ?? null;
        if ($name === null) {
            return $response->withStatus(400)->withJson([
                'success' => false,
                'message' => 'name_missing'
            ]);
        }

        $category = $this->categoryService->getCategory($name);
        if ($category === null) {
            return $response->withStatus(404)->withJson([
                'success' => false,
                'message' => 'category_not_found'
            ]);
        }

        return $response->withJson([
            'success' => true,
            'category' => $category
        ]);
    }

    /**
     * Get multiple posts from a category.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @param array $params
     * @return Response
     */
    public function getCategoryPosts (ServerRequest $request, Response $response, array $params): Response
    {
        $name = $params['category'] ?? null;
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

        $posts = $this->categoryService->getCategoryPosts($name, $offset, $limit, $query);
        return $response->withJson([
            'success' => true,
            'posts' => $posts
        ]);
    }
}