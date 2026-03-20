<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Service\HgvXmlService;

class ShortcutController extends HgvController
{
    private HgvXmlService $xmlService;

    public function __construct(RequestStack $requestStack, HgvXmlService $xmlService)
    {
        parent::__construct($requestStack);
        $this->xmlService = $xmlService;
    }

    /** /hgv/{id}  — lookup by HGV filename, e.g. "8981a". */
    public function hgv(string $id): Response
    {
        // Load full record so the datasheet has all details
        $record = $this->xmlService->getFullRecord($id);
        $data   = $record ? [$record] : [];
        return $this->render('shortcut/shortcut.html.twig', ['data' => $data]);
    }

    /** /tm/{id}  — lookup by TM number. */
    public function tm(string $id): Response
    {
        $data = $this->xmlService->findByTm($id);
        // Load full records for the datasheet
        $full = [];
        foreach ($data as $r) {
            $full[] = $this->xmlService->getFullRecord($r->getId()) ?? $r;
        }
        return $this->render('shortcut/shortcut.html.twig', ['data' => $full]);
    }

    /** /ddb/{id}  — lookup by ddb-hybrid or publication parts. */
    public function ddb(string $id): Response
    {
        $parts  = explode(';', $id);
        $layout = 'base';

        if (count($parts) === 3) {
            // ddb-hybrid format: "pub;vol;doc"
            $data = $this->xmlService->findByDdbHybrid($id);
        } elseif (count($parts) === 6) {
            // Extended format: pub;band;zusBand;nummer;seite;zusaetzlich
            $data   = $this->xmlService->findByPublicationParts(
                $parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5]
            );
            $layout = 'plain';
        } else {
            $data = [];
        }

        // Load full records
        $full = [];
        foreach ($data as $r) {
            $full[] = $this->xmlService->getFullRecord($r->getId()) ?? $r;
        }

        return $this->render('shortcut/shortcut.html.twig', ['data' => $full, 'layout' => $layout]);
    }
}
