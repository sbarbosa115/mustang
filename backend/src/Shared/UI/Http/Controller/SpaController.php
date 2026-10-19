<?php

namespace App\Shared\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Every path that is not the API or a built asset is the React app; its router decides what to show.
 */
final class SpaController extends AbstractController
{
    #[Route('/{path}', name: 'spa', requirements: ['path' => '^(?!api/|build/|_(profiler|wdt)/).*'], methods: ['GET'], priority: -100)]
    public function __invoke(): Response
    {
        return $this->render('spa.html.twig');
    }
}
