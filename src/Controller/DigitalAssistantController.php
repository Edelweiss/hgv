<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DigitalAssistantController extends HgvController
{
    public function index(): Response
    {
        return $this->render('digital_assistant/index.html.twig', [
            'controller_name' => 'DigitalAssistantController'
        ]);
    }
}
