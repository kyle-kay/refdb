<?php

namespace App\Service;

use App\Entity\ArxivLookup;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for searching and formatting arXiv preprints.
 *
 * Uses the arXiv API to fetch metadata and formats references
 * according to JACoW style:
 *   Author(s), "Title," arXiv:XXXX.XXXXX [category], year.
 */
class ArxivSearch
{
    protected EntityManagerInterface $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Search for an arXiv preprint by ID.
     *
     * @param string $text The search text (arXiv ID or URL)
     * @return array|null The formatted result or null if not found
     */
    public function search(string $text): ?array
    {
        $arxivId = ArxivHelper::extractArxivId($text);

        if (empty($arxivId)) {
            return null;
        }

        // Check cache first
        $cached = $this->getCachedResult($arxivId);
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from arXiv API
        $metadata = $this->fetchMetadata($arxivId);
        if ($metadata === null) {
            return null;
        }

        // Format the reference
        $reference = $this->formatReference($metadata);

        // Build result array
        $result = [
            'reference' => $reference,
            'arxivId' => $arxivId,
            'type' => 'arxiv-preprint',
            'title' => $metadata['title'],
            'authors' => $metadata['authors'],
            'category' => $metadata['category'],
            'year' => $metadata['year'],
            'journalName' => null,
            'abbreviation' => null,
        ];

        // Cache the result
        $this->cacheResult($arxivId, $result);

        return $result;
    }

    /**
     * Fetch metadata from the arXiv API.
     */
    private function fetchMetadata(string $arxivId): ?array
    {
        $url = ArxivHelper::getApiUrl($arxivId);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $error = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error || $httpCode !== 200 || empty($response)) {
            return null;
        }

        return $this->parseAtomResponse($response, $arxivId);
    }

    /**
     * Parse the Atom XML response from arXiv API.
     */
    private function parseAtomResponse(string $xml, string $arxivId): ?array
    {
        // Suppress warnings for malformed XML
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($doc === false) {
            return null;
        }

        // Register namespaces
        $doc->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $doc->registerXPathNamespace('arxiv', 'http://arxiv.org/schemas/atom');

        // Find the entry
        $entries = $doc->xpath('//atom:entry');
        if (empty($entries)) {
            return null;
        }

        $entry = $entries[0];

        // Check if this is a valid result (not an error entry)
        $idElement = $entry->xpath('atom:id');
        if (empty($idElement)) {
            return null;
        }
        $entryId = (string) $idElement[0];

        // If the ID doesn't contain our arxiv ID, it's probably an error
        $baseArxivId = ArxivHelper::stripVersion($arxivId);
        if (strpos($entryId, $baseArxivId) === false) {
            return null;
        }

        // Extract title
        $titleElement = $entry->xpath('atom:title');
        $title = !empty($titleElement) ? trim((string) $titleElement[0]) : null;
        // Clean up title (remove newlines and extra whitespace)
        $title = preg_replace('/\s+/', ' ', $title);

        if (empty($title)) {
            return null;
        }

        // Extract authors
        $authorElements = $entry->xpath('atom:author/atom:name');
        $authors = [];
        foreach ($authorElements as $authorElement) {
            $authors[] = trim((string) $authorElement);
        }

        if (empty($authors)) {
            return null;
        }

        // Extract published date
        $publishedElement = $entry->xpath('atom:published');
        $published = !empty($publishedElement) ? (string) $publishedElement[0] : null;
        $year = null;
        if ($published) {
            $year = substr($published, 0, 4);
        }

        // Extract primary category
        $categoryElement = $entry->xpath('arxiv:primary_category/@term');
        $category = null;
        if (!empty($categoryElement)) {
            $category = (string) $categoryElement[0];
        } else {
            // Fallback to first category
            $categoryElement = $entry->xpath('atom:category/@term');
            if (!empty($categoryElement)) {
                $category = (string) $categoryElement[0];
            }
        }

        // Extract abstract/summary
        $summaryElement = $entry->xpath('atom:summary');
        $summary = !empty($summaryElement) ? trim((string) $summaryElement[0]) : null;

        // Extract DOI if available (some arXiv papers have associated DOIs)
        $doiElement = $entry->xpath('arxiv:doi');
        $doi = !empty($doiElement) ? (string) $doiElement[0] : null;

        // Extract journal reference if available
        $journalRefElement = $entry->xpath('arxiv:journal_ref');
        $journalRef = !empty($journalRefElement) ? (string) $journalRefElement[0] : null;

        return [
            'arxivId' => $arxivId,
            'title' => $title,
            'authors' => $authors,
            'year' => $year,
            'category' => $category,
            'summary' => $summary,
            'doi' => $doi,
            'journalRef' => $journalRef,
        ];
    }

    /**
     * Format authors according to JACoW style.
     * First author: "F. Last", subsequent: "F. Last, F. Last, and F. Last"
     */
    private function formatAuthors(array $authors): string
    {
        $formatted = [];

        foreach ($authors as $author) {
            // Parse "First Last" or "First Middle Last" format
            $parts = explode(' ', trim($author));
            if (count($parts) >= 2) {
                $lastName = array_pop($parts);
                $initials = '';
                foreach ($parts as $part) {
                    if (!empty($part)) {
                        $initials .= strtoupper($part[0]) . '. ';
                    }
                }
                $formatted[] = trim($initials) . ' ' . $lastName;
            } else {
                $formatted[] = $author;
            }
        }

        $count = count($formatted);
        if ($count === 1) {
            return $formatted[0];
        } elseif ($count === 2) {
            return $formatted[0] . ' and ' . $formatted[1];
        } else {
            $last = array_pop($formatted);
            return implode(', ', $formatted) . ', and ' . $last;
        }
    }

    /**
     * Format the reference according to JACoW style.
     * Format: Author(s), "Title," arXiv:XXXX.XXXXX [category], year.
     */
    private function formatReference(array $metadata): string
    {
        $authors = $this->formatAuthors($metadata['authors']);
        $title = $metadata['title'];
        $arxivId = ArxivHelper::formatWithPrefix($metadata['arxivId']);
        $category = $metadata['category'] ? ' [' . $metadata['category'] . ']' : '';
        $year = $metadata['year'] ?? '';

        // JACoW format: Author(s), "Title," arXiv:XXXX.XXXXX [category], year.
        $reference = sprintf(
            '%s, "%s," %s%s, %s.',
            $authors,
            $title,
            $arxivId,
            $category,
            $year
        );

        return $reference;
    }

    /**
     * Get BibTeX format for an arXiv paper.
     */
    public function getBibTex(string $arxivId): ?string
    {
        $metadata = $this->fetchMetadata($arxivId);
        if ($metadata === null) {
            return null;
        }

        // Generate a cite key from first author's last name and year
        $citeKey = 'arxiv';
        if (!empty($metadata['authors'])) {
            $firstAuthor = $metadata['authors'][0];
            $parts = explode(' ', $firstAuthor);
            $lastName = strtolower(array_pop($parts));
            $lastName = preg_replace('/[^a-z]/', '', $lastName);
            $citeKey = $lastName . ($metadata['year'] ?? '');
        }

        $authors = implode(' and ', $metadata['authors']);
        $title = $metadata['title'];
        $year = $metadata['year'] ?? '';
        $eprint = $metadata['arxivId'];
        $category = $metadata['category'] ?? '';

        $bibtex = "@misc{{$citeKey},\n";
        $bibtex .= "  author = {{$authors}},\n";
        $bibtex .= "  title = {{{$title}}},\n";
        $bibtex .= "  year = {{$year}},\n";
        $bibtex .= "  eprint = {{$eprint}},\n";
        $bibtex .= "  archivePrefix = {arXiv},\n";
        if ($category) {
            $bibtex .= "  primaryClass = {{$category}},\n";
        }
        $bibtex .= "}";

        return $bibtex;
    }

    /**
     * Get cached result for an arXiv ID.
     */
    private function getCachedResult(string $arxivId): ?array
    {
        $lookup = $this->manager->getRepository(ArxivLookup::class)->findOneBy(['arxivId' => $arxivId]);
        if ($lookup === null) {
            return null;
        }

        return [
            'reference' => $lookup->getReference(),
            'arxivId' => $arxivId,
            'type' => 'arxiv-preprint',
            'title' => $lookup->getTitle(),
            'authors' => $lookup->getAuthors(),
            'category' => $lookup->getCategory(),
            'year' => $lookup->getYear(),
            'journalName' => null,
            'abbreviation' => null,
        ];
    }

    /**
     * Cache a result for future lookups.
     */
    private function cacheResult(string $arxivId, array $result): void
    {
        $lookup = new ArxivLookup();
        $lookup->setArxivId($arxivId);
        $lookup->setReference($result['reference']);
        $lookup->setTitle($result['title']);
        $lookup->setAuthors($result['authors']);
        $lookup->setCategory($result['category']);
        $lookup->setYear($result['year']);

        $this->manager->persist($lookup);
        $this->manager->flush();
    }
}
