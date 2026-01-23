<?php

namespace App\Service;

use App\Entity\Journal;
use App\Entity\Lookup;
use App\Entity\LookupMeta;
use Doctrine\ORM\EntityManagerInterface;

class ExternalSearch
{
    protected EntityManagerInterface $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    private function extractDoi($referenceText): ?string
    {
        return DoiHelper::extractDoi($referenceText);
    }

    private function extractEventName($doiResult): ?string
    {
        if ($doiResult->type == "proceedings-article" && isset($doiResult->event)) {
            if (is_string($doiResult->event) && $doiResult->event != "") {
                return $doiResult->event;
            }

            if (isset($doiResult->event->acronym)) {
                if (isset($doiResult->event->location)) {
                    return "Proc. " . $doiResult->event->acronym . ", " . $doiResult->event->location;
                }
                return "Proc. " . $doiResult->event->acronym;
            }

        }
        return null;
    }

    private function extractPublisher($doiResult): ?string
    {
        if (isset($doiResult->publisher)) {
            return $doiResult->publisher;
        }
        return null;
    }

    private function extractJournalName($doiResult): ?string
    {
        $journalKey = "short-container-title";
        if (isset($doiResult->$journalKey) && count($doiResult->$journalKey) != 0) {
            return $doiResult->$journalKey[0];
        }
        $containerKey = "container-title";
        if (isset($doiResult->$containerKey)) {
            if (is_string($doiResult->$containerKey)) {
                return $doiResult->$containerKey;
            }
            if (is_countable($doiResult->$containerKey) && count($doiResult->$containerKey) != 0) {
                return $doiResult->$containerKey[0];
            }
        }
        return null;
    }

    private function searchTextForDoi($referenceText): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.crossref.org/works?query=\"" . urlencode($referenceText) . "\"&rows=1");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResults = curl_exec($ch);

        $crossRefSearchResults = json_decode($rawResults);

        curl_close($ch);

        if (count($crossRefSearchResults->message->items) == 0) {
            return null;
        }

        $firstResult = $crossRefSearchResults->message->items[0];

        if ($firstResult->score < 50) {
            return null;
        }

        if (!isset($firstResult->DOI) || !$firstResult->DOI) {
            return null;
        }

        $publisher = $this->extractPublisher($firstResult);
        $journalName = $this->extractJournalName($firstResult);
        $eventName = $this->extractEventName($firstResult);

        $lookupMeta = new LookupMeta();
        $lookupMeta->setDoi($firstResult->DOI);
        $lookupMeta->setType($firstResult->type);
        $lookupMeta->setJournalName($journalName);
        $lookupMeta->setEventName($eventName);
        $lookupMeta->setPublisher($publisher);
        $this->manager->persist($lookupMeta);
        $this->manager->flush();

        return [
            "doi" => $firstResult->DOI,
            "type" => $firstResult->type,
            "publisher" => $publisher,
            "journalName" => $journalName,
            "eventName" => $eventName,
        ];
    }

    public function getBibTex($doi)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://dx.doi.org/" . $doi);
        $headers = [
            'Accept: text/bibliography; style=bibtex',
        ];
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $error = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            return null;
        }
        if ($http_code != 200) {
            return null;
        }
        return $result;
    }

    private function lookupMeta($doi): ?array
    {
        $lookup = $this->manager->getRepository(LookupMeta::class)->findOneBy(['doi' => $doi]);
        if (!empty($lookup)) {
            return [
                "type"=>$lookup->getType(),
                "journalName"=>$lookup->getJournalName(),
                "eventName"=>$lookup->getEventName(),
                "publisher"=>$lookup->getPublisher(),
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://dx.doi.org/" . $doi);
        $headers = [
            'Accept: application/vnd.citationstyles.csl+json',
        ];
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawResult = curl_exec($ch);
        $error = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($error) {
            return null;
        }
        if ($http_code != 200) {
            return null;
        }

        $result = json_decode($rawResult);
        $publisher = $this->extractPublisher($result);
        $journalName = $this->extractJournalName($result);
        $eventName = $this->extractEventName($result);

        $lookupMeta = new LookupMeta();
        $lookupMeta->setDoi($doi);
        $lookupMeta->setType($result->type);
        $lookupMeta->setJournalName($journalName);
        $lookupMeta->setEventName($eventName);
        $lookupMeta->setPublisher($publisher);

        $this->manager->persist($lookupMeta);
        $this->manager->flush();

        return [
            "type" => $result->type,
            "publisher" => $publisher,
            "journalName" => $journalName,
            "eventName" => $eventName,
        ];
    }

    private function doiToReference($doi): ?string
    {
        $lookup = $this->manager->getRepository(Lookup::class)->findOneBy(['doi' => $doi]);
        if (!empty($lookup)) {
            return $lookup->getReference();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://dx.doi.org/" . $doi);
        $headers = [
            'Accept: text/bibliography; style="ieee"',
        ];
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        if ($error) {
            return null;
        }

        if (strlen($result) > 1000) {
            return null;
        }

        $lookup = new Lookup();
        $lookup->setDoi($doi);
        $lookup->setReference($result);
        $this->manager->persist($lookup);
        $this->manager->flush();

        return $result;
    }

    private function lookupAbbreviation($journalName): ?string
    {
        $journal = $this->manager->getRepository(Journal::class)->findOneBy(['long' => $journalName]);
        if (!empty($journal)) {
            return $journal->getShort();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://journal-abbreviations.library.ubc.ca/ajaxsearch.php?like=" . urlencode($journalName) . "&_=" . rand(1000, 9999));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $journalAbbreviationRaw = curl_exec($ch);
        curl_close($ch);
        preg_match_all("/<td>(.*?)</", $journalAbbreviationRaw, $matches);

        $journalAbbreviation = null;
        if (count($matches[1]) == 0) {
            if (str_starts_with($journalName, "The ")) {
                $journalName = substr($journalName, 4);
                $journalAbbreviation = $this->lookupAbbreviation($journalName);
            }
        } else {
            $journalAbbreviation = $matches[1][0];
        }

        if (empty($journalAbbreviation)) {
            return null;
        }

        $journal = new Journal();
        $journal->setShort($journalAbbreviation);
        $journal->setShortCanonical(str_replace(".", "", $journalAbbreviation));
        $journal->setLong($journalName);
        $this->manager->persist($journal);
        $this->manager->flush();

        return $journalAbbreviation;
    }

    private function abbreviateJournal($originalReference, $journalName): ?array
    {
        // check if $journalName is already the abbreviation
        $journalNameCanonical = str_replace(".", "", $journalName);
        $shortResult = $this->manager->getRepository(Journal::class)->findOneBy(['shortCanonical' => $journalNameCanonical]);
        if (!empty($shortResult)) {
            $abbreviation = $shortResult->getShort();
            $journalName = $shortResult->getLong();
        } else {
            $abbreviation = $this->lookupAbbreviation($journalName);
        }

        if (empty($abbreviation)) {
            return [
                "reference" => $originalReference,
                "abbreviation" => null
            ];
        }

        return [
            "reference" => str_replace($journalName, $abbreviation, $originalReference),
            "abbreviation" => $abbreviation
        ];
    }

    private function adjustIeeeStyling($originalReference): string
    {
        // chop off [1]
        $result = substr($originalReference, 3);

        // remove doi space
        $result = str_replace("doi: 10", "doi:10", $result);

        // change ", doi:10." to ". doi:"
        return trim(preg_replace("/[,.] (doi:10.*)\./", ". $1", $result));
    }

    public function search($text): ?array
    {
        $doi = $this->extractDoi($text);

        if (empty($doi)) {
            $result = $this->searchTextForDoi($text);
            if (empty($result)) {
                return null;
            }
        } else {
            $meta = $this->lookupMeta($doi);
            if (empty($meta)) {
                return null;
            }
            $result = [
                "type" => $meta['type'],
                "journalName" => $meta['journalName'],
                "publisher" => $meta['publisher'],
                "eventName" => $meta['eventName'],
                "doi" => $doi
            ];
        }

        $reference = $this->doiToReference($result['doi']);

        if (empty($reference)) {
            return null;
        }

        // strip remove publisher
        if ($result['publisher'] !== null) {
            $reference = str_replace(", " . $result['publisher'], "", $reference);
        }

        $abbreviation = null;

        if ($result['type'] == "journal-article") {
            $result = $this->abbreviateJournal($reference, $result['journalName']);
            $reference = $result["reference"];
            $abbreviation = $result["abbreviation"];
        } elseif ($result['type'] == "proceedings-article" && $result['eventName'] !== null) {
            $result['eventName'] = str_replace("Proceedings of the ", "Proc. ", $result['eventName']);
            $result['eventName'] = str_replace(" International ", " Int. ", $result['eventName']);
            $result['eventName'] = str_replace(" Conference ", " Conf. ", $result['eventName']);
            $reference = str_replace($result['journalName'], $result['eventName'], $reference);
            $abbreviation = $result['eventName'];
        }

        return [
            "reference" => $this->adjustIeeeStyling($reference),
            "doi" => $doi,
            "journalName" => $result['journalName'] ?? "",
            "abbreviation" => $abbreviation,
        ];
    }
}