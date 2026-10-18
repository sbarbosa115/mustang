<?php

namespace App\Shared\UI\Http\Controller;

use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\HealthOutput;
use Doctrine\DBAL\Connection;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Health')]
final class HealthController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(env: 'MESSAGING_SMS_TRANSPORT')] private readonly string $smsTransport,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
    }

    /**
     * Is the app up and its database reachable. The smoke suite also reads which SMS transport is active.
     */
    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The app is up', content: new Model(type: HealthOutput::class))]
    public function __invoke(): JsonResponse
    {
        $this->connection->executeQuery('SELECT 1');

        return $this->ok(new HealthOutput('ok', $this->environment, $this->smsTransport));
    }
}
