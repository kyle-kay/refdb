# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-01-23

### Problem Description

Users reported that searching for DOIs without checking "Search for external reference" returned incorrect results. For example:

- Searching for `10.1038/s41586-025-10013-1` (a Nature paper about barocaloric effects) returned unrelated papers about electron guns and synchrotrons
- The system only returned correct results when "Search for external reference" was manually checked
- This created a confusing user experience where DOI searches required extra steps

**Root Causes Identified:**

1. **No DOI detection in internal search**: The MongoDB full-text search treated DOIs as generic text, matching random words/numbers against titles and authors instead of the DOI field
2. **Regex pattern bug**: The DOI pattern `10.\d{4,9}/...` had an unescaped `.` after `10`, which matched any character (e.g., `10x1234/...` would match)
3. **No DOI input normalization**: Users could enter DOIs in various formats (`doi:10.xxx`, `https://doi.org/10.xxx`, etc.) but only raw DOIs were recognized
4. **curl error handling bug**: `curl_errno()` was called after `curl_close()`, making error detection unreliable

### Fixed

#### DOI Auto-Detection and Fallback
- **New `DoiHelper` utility class** (`src/Service/DoiHelper.php`):
  - Centralized DOI detection with `isDoiSearch()` method
  - Proper regex pattern: `10\.\d{4,9}/[-._;()/:a-zA-Z0-9]+` (escaped dot)
  - Input normalization for multiple formats:
    - Plain DOI: `10.1038/s41586-025-10013-1`
    - With prefix: `doi:10.1038/s41586-025-10013-1`
    - HTTPS URL: `https://doi.org/10.1038/s41586-025-10013-1`
    - HTTP URL: `http://doi.org/10.1038/s41586-025-10013-1`
    - DX URL: `https://dx.doi.org/10.1038/s41586-025-10013-1`

#### Smart Search Routing
- **Updated `SearchService`** (`src/Service/SearchService.php`):
  - Added `isDoiQuery()` method to detect DOI searches
  - Added `searchByDoi()` method to search MongoDB DOI field specifically
  - Modified `search()` to check for DOI queries first before full-text search

#### Automatic External Fallback
- **Updated `SearchController`** (`src/Controller/SearchController.php`):
  - When internal search returns empty AND query is a DOI
  - Automatically falls back to CrossRef/DOI.org external search
  - No checkbox required for DOI queries

#### Bug Fixes
- **Fixed DOI regex patterns** in:
  - `src/Service/ExternalSearch.php` - now uses `DoiHelper`
  - `src/Service/MarkupReference.php` - fixed `10\.` pattern in latex() and word()

- **Fixed curl error handling** in `src/Service/ExternalSearch.php`:
  - `getBibTex()`: Check `curl_errno()` before `curl_close()`
  - `lookupMeta()`: Check `curl_errno()` before `curl_close()`
  - `doiToReference()`: Check `curl_errno()` before `curl_close()`

### How It Works Now

```
User enters: 10.1038/s41586-025-10013-1

1. DoiHelper detects this is a DOI query
2. SearchService searches MongoDB DOI field specifically
3. If not found in local database:
   - Automatically queries CrossRef/DOI.org
   - Returns formatted reference (IEEE, BibTeX, LaTeX, or Word)
4. User sees correct result without needing to check any boxes
```

### Files Changed

| File | Change |
|------|--------|
| `src/Service/DoiHelper.php` | **New** - Centralized DOI detection/normalization |
| `src/Service/SearchService.php` | Added DOI-specific search methods |
| `src/Service/ExternalSearch.php` | Use DoiHelper, fix curl error handling |
| `src/Service/MarkupReference.php` | Fix DOI regex patterns |
| `src/Controller/SearchController.php` | Add auto-fallback for DOI queries |

### Technical Notes for Future AI Review

1. **DOI Pattern**: The canonical regex is in `DoiHelper::DOI_PATTERN`. Always use `10\.` (escaped dot) not `10.`

2. **Search Priority**: DOI queries bypass full-text search entirely and go straight to DOI field lookup

3. **Caching**: External lookups are cached in `Lookup` and `LookupMeta` entities - no TTL currently

4. **Fallback Chain**: Internal DOI search → External (CrossRef → DOI.org)

5. **curl Best Practice**: Always call `curl_errno()` and `curl_getinfo()` BEFORE `curl_close()`
