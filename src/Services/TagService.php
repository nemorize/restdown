<?php

namespace App\Services;

class TagService
{
    public function __construct (
        private readonly IndexingService $indexingService,
        private readonly PostService $postService
    ) {}

    /**
     * Get all tags.
     *
     * @return array
     */
    public function getTags (): array
    {
        return $this->indexingService->getTags();
    }

    /**
     * Get a tag.
     *
     * @param string $tag
     * @return ?object
     */
    public function getTag (string $tag): ?object
    {
        return $this->indexingService->getTag($tag);
    }

    /**
     * Get all posts from a tag.
     *
     * @param string $tag
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return object
     */
    public function getTagPosts (string $tag, int $offset, int $limit, string $where = null): object
    {
        $output = $this->indexingService->getTagPosts($tag, $offset, $limit, $where);
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