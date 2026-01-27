<?php

namespace App\Controller;

use App\Entity\Search;
use App\Enum\FormatType;
use App\Form\OmniSearchType;
use App\Service\DoiHelper;
use App\Service\ExternalSearch;
use App\Service\FavouriteService;
use App\Service\MarkupReference;
use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 *
 */
class SearchController extends AbstractController
{

    private function formatExternalResult(array $externalResult, FormatType $formatType, ExternalSearch $externalSearch): array
    {
        $formatter = new MarkupReference();
        $type = $externalResult["type"] ?? null;
        $title = $externalResult["title"] ?? null;

        $externalResult["reference"] = match ($formatType){
            FormatType::Text => $externalResult["reference"],
            FormatType::BibTex => $externalSearch->getBibTex($externalResult["doi"]),
            FormatType::BibItem => $formatter->latex($externalResult["reference"], $externalResult["abbreviation"], $type, $title),
            FormatType::Word => $formatter->word($externalResult["reference"], $externalResult["abbreviation"], $type, $title),
        };

        return $externalResult;
    }

    /**
     * @Route("/external/{format}", name="external-query", defaults={"format": "text"})
     * @param Request $request
     * @return JsonResponse
     */
    public function externalAction(Request $request, ExternalSearch $externalSearch, ?string $format = "text")
    {
        $query = $request->get('query');
        $externalResult = $externalSearch->search($query);

        if (!empty($externalResult)) {
            $externalResult = $this->formatExternalResult($externalResult, FormatType::from($format), $externalSearch);
        }

        return new JsonResponse(['query'=>$externalResult]);
    }

    /**
     * @Route("/internal/{format}", name="internal-query", defaults={"format": "text"})
     * @param Request $request
     * @return JsonResponse
     */
    public function internalAction(Request $request, SearchService $searchService, Environment $twig, ?string $format = "text")
    {
        $query = $request->get('query');
        $response = $searchService->search($query);
        $results = [];
        foreach ($response as $reference) {
            $result = $reference->jsonSerialize();
            if (FormatType::from($format) == FormatType::BibItem) {
                $result['name'] = $twig->render("reference/latex.html.twig", ["reference"=>$reference, "form"=>"short", "hide_header"=>true]);
            }
            $results[] = $result;
        }
        return new JsonResponse(['query'=>$results]);
    }

    /**
     * @Route("/", name="homepage")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, SearchService $searchService, ExternalSearch $externalSearch, FavouriteService $favouriteService)
    {
        $search = new Search();
        $form = $this->createForm(OmniSearchType::class, $search);
        $form->handleRequest($request);

        $results = [];

        $searched = false;
        $externalResult = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $searched = true;
            $query = $search->getQuery();

            if ($search->getCheckExternal()) {
                // User explicitly requested external search
                $externalResult = $externalSearch->search($query);

                if (!empty($externalResult)) {
                    $externalResult = $this->formatExternalResult($externalResult, $search->getFormatType(), $externalSearch);
                }
            } else {
                // Internal search
                $results = $searchService->search($query);

                // If the query is a DOI and no internal results found,
                // automatically fall back to external search
                if (empty($results) && DoiHelper::isDoiSearch($query)) {
                    $externalResult = $externalSearch->search($query);

                    if (!empty($externalResult)) {
                        $externalResult = $this->formatExternalResult($externalResult, $search->getFormatType(), $externalSearch);
                    }
                }
            }
        }

        return $this->render("search/index.html.twig", [
            "searched" => $searched,
            "references" => $results,
            "format" => $search->getFormatType()?->value ?? FormatType::Text,
            "form" => $form->createView(),
            "query" => $search->getQuery(),
            "favourites" => $favouriteService->getFavourites(),
            "external" => $externalResult,
        ]);
    }
}
