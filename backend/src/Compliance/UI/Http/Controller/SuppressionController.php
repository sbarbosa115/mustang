<?php

namespace App\Compliance\UI\Http\Controller;

use App\Compliance\Application\Query\ConsentQueries;
use App\Compliance\Application\Query\SuppressionView;
use App\Compliance\UI\Http\Output\SuppressionOutput;
use App\Compliance\UI\Http\Output\SuppressionPageOutput;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Input\PageQuery;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Consent')]
final class SuppressionController extends ApiController
{
    public function __construct(private readonly ConsentQueries $queries)
    {
    }

    /**
     * Contacts of this dealership's customers that opted out, at any dealership, newest first; q matches the phone or email.
     */
    #[Route('/api/v1/compliance/suppressions', name: 'api_suppressions_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Contacts that opted out, across every dealership', content: new Model(type: SuppressionPageOutput::class))]
    public function suppressionsList(#[MapQueryString] PageQuery $query = new PageQuery()): JsonResponse
    {
        $page = $this->queries->suppressions($query->offset(), $query->perPage, $query->term());

        return $this->ok(new SuppressionPageOutput(
            array_map(static fn (SuppressionView $s): SuppressionOutput => new SuppressionOutput($s->id, $s->contactValue, $s->channel, $s->reason, $s->createdAt), $page['items']),
            $page['total'],
            $query->page,
            $query->perPage,
        ));
    }
}
