<?php

namespace App\Service;

use App\Entity\Author;
use App\Entity\Conference;
use App\Entity\Reference;
use App\Service\DoiHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\ServerApi;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class SearchService
{
    private Environment $twig;
    private string $searchDb;
    private LoggerInterface $logger;
    private ?\MongoDB\Collection $collection = null;
    private EntityManagerInterface $manager;

    public function __construct(Environment $twig, string $searchDb, LoggerInterface $logger, EntityManagerInterface $manager)
    {
        $this->twig = $twig;
        $this->searchDb = $searchDb;
        $this->logger = $logger;
        $this->manager = $manager;
    }

    public function getCollection($collectionName = "search")
    {
        if ($this->collection !== null) {
            return $this->collection;
        }
        $uri = $this->searchDb;
        $apiVersion = new ServerApi(ServerApi::V1);
        $client = new \MongoDB\Client($uri, [], ['serverApi' => $apiVersion, 'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY)]);
        try {
            // Send a ping to confirm a successful connection
            $client->selectDatabase('search')->command(['ping' => 1]);
        } catch (Exception $e) {
            $this->logger->error("Unable to connect to search database: " . $e->getMessage());
        }
        $this->collection = $client->selectCollection("search", $collectionName);
        return $this->collection;
    }

    private function getPayload(Reference $reference)
    {
        $authors = $reference->getAuthors();
        $lastNames = [];
        /** @var Author $author */
        foreach ($authors as $author) {
            $nameParts = explode(". ", $author->getName());
            $lastNames[] = end($nameParts);
        }

        $data = [
            "ref_id" => $reference->getId(),
            "title" => $reference->getTitle(),
            "paper_code" => $reference->getPaperId(),
            "label" => $reference->getCache(),
            "conference_code" => $reference->getConference()->getCode(),
            "conference_name" => $reference->getConference()->getName(),
            "authors" => implode(" ", $lastNames)
        ];

        if (!empty($reference->getConference()->getYear())) {
            $data["date"] = $reference->getConference()->getYear();
        }

        if (!empty($reference->getConference()->getLocation())) {
            $data["location"] = $reference->getConference()->getLocation();
        }

        if (!empty($reference->doiOnly())) {
            $data["doi"] = $reference->doiOnly();
        }
        return $data;
    }

    public function insertOrUpdate(Reference $reference)
    {
        $collection = $this->getCollection();
        $filter = ["conference_code" => $reference->getConference()->getCode()];
        if ($reference->getPaperId() === null) {
            $filter["ref_id"] = $reference->getId();
        } else {
            $filter["paper_code"] = $reference->getPaperId();
        }
        $result = $collection->findOne($filter);
        if ($result === null) {
            $collection->insertOne($this->getPayload($reference));
        } else {
            $collection->updateOne(["_id" => $result->_id], ['$set' => $this->getPayload($reference)]);
        }
    }

    /**
     * Check if the query appears to be a DOI.
     */
    public function isDoiQuery(?string $query): bool
    {
        return DoiHelper::isDoiSearch($query);
    }

    /**
     * Search for references by DOI specifically.
     * Returns matching references if the DOI is found in the database.
     */
    public function searchByDoi(string $doi): array
    {
        // Normalize the DOI
        $normalizedDoi = DoiHelper::extractDoi($doi);
        if ($normalizedDoi === null) {
            return [];
        }

        // Search MongoDB by DOI field specifically
        $cursor = $this->getCollection()->find(['doi' => $normalizedDoi]);
        $ids = [];
        foreach ($cursor as $document) {
            $ids[] = $document->ref_id;
        }

        if (empty($ids)) {
            return [];
        }

        return $this->manager->getRepository(Reference::class)->createQueryBuilder("r")
            ->where("r.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();
    }

    public function search(?string $query = null, int $limitResults = 10): array
    {
        // Check if this is a DOI query - if so, search DOI field first
        if (DoiHelper::isDoiSearch($query)) {
            $doiResults = $this->searchByDoi($query);
            if (!empty($doiResults)) {
                return $doiResults;
            }
            // If no DOI match found in internal DB, return empty
            // The controller will handle fallback to external search
            return [];
        }

        $pipeline = [
            ['$search' => [
                'index' => 'search',
                'text' => ['query' => $query, 'path' => ['wildcard' => '*']],
            ]]
        ];

        // Max results 20
        $cursor = $this->getCollection()->aggregate($pipeline, []);
        $results = [];
        $ids = [];
        foreach ($cursor as $document) {
            $results[] = $document;
            $ids[] = $document->ref_id;
            if (count($results) > $limitResults) {
                break;
            }
        }

        if (empty($ids)) {
            return [];
        }

        $references = $this->manager->getRepository(Reference::class)->createQueryBuilder("r")
            ->where("r.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();

        return array_filter(array_map(function ($result) use ($references) {
            foreach ($references as $reference) {
                if ($reference->getId() === $result->ref_id) {
                    return $reference;
                }
            }
            return null;
        }, $results));
    }

    public function updateConference(Conference $conference): void
    {
        $conference->getReferences()->map(function (Reference $reference) {
            $this->insertOrUpdate($reference);
        });
    }
}
