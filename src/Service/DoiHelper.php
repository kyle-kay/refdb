<?php

namespace App\Service;

/**
 * Utility class for DOI detection, extraction, and normalization.
 * Centralizes all DOI-related pattern matching logic.
 */
class DoiHelper
{
    /**
     * Regex pattern for matching DOI numbers.
     * DOI format: 10.PREFIX/SUFFIX where PREFIX is 4-9 digits
     * Note: The dot after "10" must be escaped to match literal dot.
     */
    public const DOI_PATTERN = '#10\.\d{4,9}/[-._;()/:a-zA-Z0-9]+#i';

    /**
     * Check if the given text appears to be primarily a DOI query.
     * A DOI query is text that contains a DOI and little else.
     */
    public static function isDoi(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $doi = self::extractDoi($text);
        if ($doi === null) {
            return false;
        }

        // Check if the text is primarily a DOI (with possible URL prefix or "doi:" prefix)
        $normalized = self::normalizeInput($text);
        return $normalized !== null;
    }

    /**
     * Extract a DOI from text that may contain other content.
     * Returns null if no DOI is found.
     */
    public static function extractDoi(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // First normalize the input to handle URL formats
        $normalized = self::normalizeInput($text);
        if ($normalized !== null) {
            return rtrim($normalized, ".,;");
        }

        // Fall back to regex extraction from longer text
        preg_match(self::DOI_PATTERN, $text, $matches);
        if (empty($matches)) {
            return null;
        }

        return rtrim($matches[0], ".,;");
    }

    /**
     * Normalize various DOI input formats to a plain DOI string.
     * Handles:
     *   - Plain DOI: 10.1038/s41586-025-10013-1
     *   - DOI prefix: doi:10.1038/s41586-025-10013-1
     *   - HTTPS URL: https://doi.org/10.1038/s41586-025-10013-1
     *   - HTTP URL: http://doi.org/10.1038/s41586-025-10013-1
     *   - DX URL: https://dx.doi.org/10.1038/s41586-025-10013-1
     *
     * Returns null if the input doesn't match any recognized DOI format.
     */
    public static function normalizeInput(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        $text = trim($text);

        // Remove common URL prefixes
        $prefixes = [
            'https://doi.org/',
            'http://doi.org/',
            'https://dx.doi.org/',
            'http://dx.doi.org/',
            'doi.org/',
            'dx.doi.org/',
            'doi:',
            'DOI:',
            'DOI ',
            'doi ',
        ];

        foreach ($prefixes as $prefix) {
            if (stripos($text, $prefix) === 0) {
                $text = substr($text, strlen($prefix));
                break;
            }
        }

        $text = trim($text);

        // Validate that the result looks like a DOI
        if (preg_match('/^10\.\d{4,9}\/[-._;()\/:\w]+$/i', $text)) {
            return rtrim($text, ".,;");
        }

        return null;
    }

    /**
     * Check if the search query looks like it's primarily a DOI search.
     * This is used to determine if we should prioritize DOI-specific search.
     */
    public static function isDoiSearch(?string $query): bool
    {
        if (empty($query)) {
            return false;
        }

        $trimmed = trim($query);

        // If the normalized input is a valid DOI, it's a DOI search
        $normalized = self::normalizeInput($trimmed);
        if ($normalized !== null) {
            return true;
        }

        // Also check if the entire query is essentially just a DOI
        // (allowing for minor whitespace or punctuation)
        $cleaned = preg_replace('/\s+/', '', $trimmed);
        return self::normalizeInput($cleaned) !== null;
    }

    /**
     * Format a DOI for display with the doi: prefix.
     */
    public static function formatWithPrefix(string $doi): string
    {
        return 'doi:' . $doi;
    }

    /**
     * Format a DOI as a full URL.
     */
    public static function formatAsUrl(string $doi): string
    {
        return 'https://doi.org/' . $doi;
    }
}
