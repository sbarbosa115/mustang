<?php

namespace App\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for API controllers: answers with an Output DTO serialized in snake_case.
 */
abstract class ApiController extends AbstractController
{
    protected function ok(object $output, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json($output, $status);
    }

    protected function created(object $output): JsonResponse
    {
        return $this->json($output, Response::HTTP_CREATED);
    }

    protected function noContent(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The contract's placeholder: the route, its Input and its Output exist, the behaviour lands with its item.
     */
    protected function notImplemented(string $owner): JsonResponse
    {
        return new JsonResponse([
            'error' => 'not_implemented',
            'message' => \sprintf('This endpoint is part of the contract and is built by item "%s".', $owner),
            'detail' => ['owner' => $owner],
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
