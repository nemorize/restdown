<?php

namespace App\Services;

class CategoryService
{
    public function __construct (
        private readonly IndexingService $indexingService,
        private readonly PostService $postService
    ) {}

    /**
     * Get all categories.
     *
     * @return array
     */
    public function getCategories (): array
    {
        return $this->indexingService->getCategories();
    }

    /**
     * Get a category.
     *
     * @param string $category
     * @return ?object
     */
    public function getCategory (string $category): ?object
    {
        return $this->indexingService->getCategory($category);
    }

    /**
     * Get all posts from a category.
     *
     * @param string $category
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return object
     */
    public function getCategoryPosts (string $category, int $offset, int $limit, string $where = null): object
    {
        $output = $this->indexingService->getCategoryPosts($category, $offset, $limit, $where);
        $output->posts = array_map(function ($post) {
            $post->content = $this->postService->parseMarkdown($post->path);
            $post->categories = json_decode($post->categories);
            $post->tags = json_decode($post->tags);

            unset($post->path);
            return $post;
        }, $output->posts);
        return $output;
    }
}