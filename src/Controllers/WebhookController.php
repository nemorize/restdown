<?php

namespace App\Controllers;

use App\Application;
use App\Services\GitService;
use App\Services\IndexingService;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class WebhookController
{
    public function __construct (
        private readonly GitService $gitService,
        private readonly IndexingService $indexingService
    ) {}

    /**
     * Webhook route.
     *
     * @param ServerRequest $request
     * @param Response $response
     * @return Response
     */
    public function webhook (ServerRequest $request, Response $response): Response
    {
        $githubSignature = $request->getHeaderLine('X-Hub-Signature-256');
        if ($githubSignature === '') {
            return $response->withStatus(400);
        }

        [ $algo, $hash ] = explode('=', $githubSignature, 2) + [ '', '' ];
        if ($algo !== 'sha256') {
            return $response->withStatus(400);
        }

        $rawBody = $request->getBody()->getContents();
        if (!hash_equals($hash, hash_hmac('sha256', $rawBody, Application::get('env:GITHUB_SECRET')))) {
            return $response->withStatus(400);
        }

        $githubEvent = $request->getHeaderLine('X-GitHub-Event');
        if ($githubEvent === 'ping') {
            return $response->withStatus(200)->write('pong');
        }

        if ($githubEvent !== 'push') {
            return $response->withStatus(400);
        }

        set_time_limit(0);

        $root = $this->indexingService->getRootDir();
        if (!$root) {
            $root = Application::get('env:MARKDOWN_ROOT') ?? './markdowns';
            if (!str_starts_with($root, '/')) {
                $root = __DIR__ . '/../../' . $root;
            }
            mkdir($root, 0777, true);
            $root = realpath($root);
            if (!$root) {
                return $response->withStatus(500)->withJson([
                    'error' => 'Failed to create the root directory.'
                ]);
            }
        }

        if (!is_dir($root . '/.git')) {
            if ($this->gitService->clone($root) === null) {
                return $response->withStatus(500)->withJson([
                    'error' => 'Failed to clone the repository.'
                ]);
            }
        }

        if ($this->gitService->pull($root) === null) {
            return $response->withStatus(500)->withJson([
                'error' => 'Failed to pull the repository.'
            ]);
        }

        $this->indexingService->saveIndexing();
        shell_exec('rm -rf ' . __DIR__ . '/../../storage/cache');

        return $response->withStatus(200);
    }
}