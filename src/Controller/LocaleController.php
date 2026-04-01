<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LocaleController extends AbstractController
{
    public function switchLocale(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        // Redirect back to the referring page, or home
        $referer = $request->headers->get('referer');

        return new RedirectResponse($referer ?: $this->generateUrl('PapyrillioHgvBundle_Home'));
    }
}
