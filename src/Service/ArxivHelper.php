<?php

namespace App\Service;

/**
 * Utility class for arXiv ID detection, extraction, and normalization.
 * Centralizes all arXiv-related pattern matching logic.
 *
 * arXiv ID formats:
 *   - New format (since April 2007): YYMM.NNNNN (e.g., 2301.12345, 0704.0001)
 *   - Old format: category/YYMMNNN (e.g., hep-th/9901001, cond-mat/0001234)
 *   - May include version suffix: 2301.12345v2
 */
class ArxivHelper
{
    /**
     * Regex pattern for new-style arXiv IDs (YYMM.NNNNN with optional version).
     */
    public const NEW_ID_PATTERN = '#\d{4}\.\d{4,5}(?:v\d+)?#i';

    /**
     * Regex pattern for old-style arXiv IDs (category/YYMMNNN with optional version).
     * Common categories: hep-th, hep-ph, hep-ex, cond-mat, astro-ph, quant-ph, physics, etc.
     */
    public const OLD_ID_PATTERN = '#[a-z-]+/\d{7}(?:v\d+)?#i';

    /**
     * Combined pattern that matches both formats.
     */
    public const ARXIV_ID_PATTERN = '#(?:\d{4}\.\d{4,5}|[a-z-]+/\d{7})(?:v\d+)?#i';

    /**
     * Check if the given text appears to be primarily an arXiv query.
     */
    public static function isArxiv(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $arxivId = self::extractArxivId($text);
        return $arxivId !== null;
    }

    /**
     * Extract an arXiv ID from text that may contain other content.
     * Returns null if no arXiv ID is found.
     */
    public static function extractArxivId(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // First try to normalize the input
        $normalized = self::normalizeInput($text);
        if ($normalized !== null) {
            return $normalized;
        }

        // Fall back to regex extraction from longer text
        // Try new format first (more common now)
        if (preg_match(self::NEW_ID_PATTERN, $text, $matches)) {
            return $matches[0];
        }

        // Try old format
        if (preg_match(self::OLD_ID_PATTERN, $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Normalize various arXiv input formats to a plain arXiv ID string.
     * Handles:
     *   - Plain ID: 2301.12345
     *   - With prefix: arXiv:2301.12345
     *   - HTTPS URL: https://arxiv.org/abs/2301.12345
     *   - HTTP URL: http://arxiv.org/abs/2301.12345
     *   - PDF URL: https://arxiv.org/pdf/2301.12345.pdf
     *   - Old format: hep-th/9901001
     *
     * Returns null if the input doesn't match any recognized arXiv format.
     */
    public static function normalizeInput(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        $text = trim($text);

        // Remove common URL prefixes
        $prefixes = [
            'https://arxiv.org/abs/',
            'http://arxiv.org/abs/',
            'https://arxiv.org/pdf/',
            'http://arxiv.org/pdf/',
            'https://export.arxiv.org/abs/',
            'http://export.arxiv.org/abs/',
            'arxiv.org/abs/',
            'arxiv.org/pdf/',
            'arXiv:',
            'arxiv:',
            'ARXIV:',
        ];

        foreach ($prefixes as $prefix) {
            if (stripos($text, $prefix) === 0) {
                $text = substr($text, strlen($prefix));
                break;
            }
        }

        // Remove .pdf suffix if present
        $text = preg_replace('/\.pdf$/i', '', $text);
        $text = trim($text);

        // Validate that the result looks like an arXiv ID
        // New format: YYMM.NNNNN (with optional version)
        if (preg_match('/^\d{4}\.\d{4,5}(?:v\d+)?$/i', $text)) {
            return $text;
        }

        // Old format: category/YYMMNNN (with optional version)
        if (preg_match('/^[a-z-]+\/\d{7}(?:v\d+)?$/i', $text)) {
            return $text;
        }

        return null;
    }

    /**
     * Check if the search query looks like it's primarily an arXiv search.
     */
    public static function isArxivSearch(?string $query): bool
    {
        if (empty($query)) {
            return false;
        }

        $trimmed = trim($query);

        // If the normalized input is a valid arXiv ID, it's an arXiv search
        $normalized = self::normalizeInput($trimmed);
        if ($normalized !== null) {
            return true;
        }

        // Also check if the entire query is essentially just an arXiv ID
        $cleaned = preg_replace('/\s+/', '', $trimmed);
        return self::normalizeInput($cleaned) !== null;
    }

    /**
     * Format an arXiv ID with the arXiv: prefix.
     */
    public static function formatWithPrefix(string $arxivId): string
    {
        return 'arXiv:' . $arxivId;
    }

    /**
     * Format an arXiv ID as a full URL.
     */
    public static function formatAsUrl(string $arxivId): string
    {
        return 'https://arxiv.org/abs/' . $arxivId;
    }

    /**
     * Get the arXiv API URL for fetching metadata.
     */
    public static function getApiUrl(string $arxivId): string
    {
        return 'https://export.arxiv.org/api/query?id_list=' . urlencode($arxivId);
    }

    /**
     * Remove version suffix from arXiv ID (e.g., 2301.12345v2 -> 2301.12345).
     */
    public static function stripVersion(string $arxivId): string
    {
        return preg_replace('/v\d+$/i', '', $arxivId);
    }
}
