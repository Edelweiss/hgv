<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Service\PublicationService;
use App\Service\HgvXmlService;

class PublicationController extends HgvController
{
    private PublicationService $publicationService;
    private HgvXmlService $xmlService;

    public function __construct(
        RequestStack $requestStack,
        PublicationService $publicationService,
        HgvXmlService $xmlService
    ) {
        parent::__construct($requestStack);
        $this->publicationService = $publicationService;
        $this->xmlService = $xmlService;
    }

    /**
     * Main publication browser page with three interactive columns.
     * Column 1 (volumes) is pre-loaded; columns 2 and 3 are populated via AJAX.
     */
    public function index(): Response
    {
        $volumes = $this->publicationService->getVolumes();

        return $this->render('publication/index.html.twig', [
            'volumes' => $volumes,
        ]);
    }

    /**
     * AJAX endpoint: returns HTML list items of publication numbers
     * for a given (series, volume) pair.
     */
    public function loadNumbers(Request $request): Response
    {
        $series = $request->query->get('series', '');
        $volume = $request->query->get('volume', '');

        $numbers = $this->publicationService->getNumbers($series, $volume);

        return $this->render('publication/_numbers.html.twig', [
            'numbers' => $numbers,
        ]);
    }

    /**
     * AJAX endpoint: returns rendered datasheets for records matching
     * a specific (series, volume, number) publication triple.
     */
    public function loadRecords(Request $request): Response
    {
        $series = $request->query->get('series', '');
        $volume = $request->query->get('volume', '');
        $number = $request->query->get('number', '');

        $ids = $this->publicationService->findRecordIds($series, $volume, $number);

        $records = [];
        foreach ($ids as $id) {
            $record = $this->xmlService->getFullRecord($id);
            if ($record) {
                $records[] = $record;
            }
        }

        return $this->render('publication/_results.html.twig', [
            'records' => $records,
        ]);
    }
}
