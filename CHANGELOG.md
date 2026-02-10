# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-02-10

### New Feature: arXiv Preprint Support

Added full support for arXiv preprints, a high-priority item from the IPAC27 feature request.

#### Features

- **Auto-detection of arXiv IDs**: Searches containing arXiv IDs are automatically routed to the arXiv API
- **Multiple input formats supported**:
  - Plain ID: `2301.12345` or `hep-th/9901001`
  - With prefix: `arXiv:2301.12345`
  - URL: `https://arxiv.org/abs/2301.12345`
  - PDF URL: `https://arxiv.org/pdf/2301.12345.pdf`
- **JACoW-compliant formatting**: References formatted as `Author(s), "Title," arXiv:XXXX.XXXXX [category], year.`
- **BibTeX export**: Full BibTeX support with proper arXiv fields (`eprint`, `archivePrefix`, `primaryClass`)
- **Caching**: Results cached in database for faster subsequent lookups

#### New Files

| File | Description |
|------|-------------|
| `src/Service/ArxivHelper.php` | arXiv ID detection, extraction, and normalization |
| `src/Service/ArxivSearch.php` | arXiv API integration and reference formatting |
| `src/Entity/ArxivLookup.php` | Entity for caching arXiv lookup results |

#### Modified Files

| File | Change |
|------|--------|
| `src/Controller/SearchController.php` | Added arXiv auto-detection and routing |

#### Usage Examples

| Input | Result |
|-------|--------|
| `2301.12345` | Fetches arXiv paper and formats reference |
| `arXiv:2301.12345` | Same as above |
| `https://arxiv.org/abs/2301.12345` | Same as above |
| `hep-th/9901001` | Old-style arXiv ID also supported |

---

## [2026-01-23] - Bug Fixes

### Bug Fixes from User Group Feedback

Based on feedback from the JACoW user group about formatting issues with the reference tool:

#### Bug #1: Journal Abbreviation Returns Wrong Journal (CRITICAL)
**Problem:** Searching for `doi:10.1126/science.abo4297` returned "AIMS Med. Sci." instead of "Science"

**Root Cause:** `lookupAbbreviation()` blindly took the first result from UBC Journal Abbreviations API fuzzy search. For common short names like "Science", the API returned multiple journals containing "science" in their name.

**Fix:** Added `findMatchingAbbreviation()` in `src/Service/ExternalSearch.php`:
- Validates that the returned full journal name matches the input
- Tries exact match first, then reversed column order, then prefix match
- Returns `null` (no abbreviation) rather than a wrong abbreviation

#### Bug #2: Thesis DOI Returns "No Results"
**Problem:** Valid thesis DOIs like `doi:10.3929/ethz-a-010748643` returned no results

**Root Cause:** The `search()` method only had explicit handling for `journal-article` and `proceedings-article` types. Other types like `dissertation` fell through without proper handling.

**Fix:** Updated `src/Service/ExternalSearch.php`:
- `dissertation`, `report`, and other types now pass through with IEEE formatting
- No longer blocked by missing type handling

#### Bug #3: Book Formatting Incomplete
**Problem:** Book DOI `doi:10.1007/978-3-319-18317-6` was missing italicized title and publisher address

**Root Cause:**
- Book titles weren't being italicized (only journal names were)
- Publisher location (`publisher-location`) wasn't being extracted from DOI.org metadata

**Fix:**
- Added `extractPublisherLocation()` in `src/Service/ExternalSearch.php`
- Book types (`book`, `monograph`, `edited-book`, `book-chapter`, `reference-book`) now insert location before publisher
- Updated `src/Service/MarkupReference.php` to accept `type` and `title` parameters
- Book titles are now italicized in LaTeX (`\textit{}`) and Word (`<em>`) output
- Refactored `src/Controller/SearchController.php` with `formatExternalResult()` helper

### Files Changed

| File | Change |
|------|--------|
| `src/Service/ExternalSearch.php` | Added `findMatchingAbbreviation()`, `extractPublisherLocation()`, book/thesis type handling |
| `src/Service/MarkupReference.php` | Added `type`/`title` params, `isBookType()` for title italicization |
| `src/Controller/SearchController.php` | Refactored with `formatExternalResult()` helper |

---

## Pending Features (from IPAC27 Feature Request)

The following items from `ling_feature_request_01_23_2026.md` are tracked below:

### High Priority
| Feature | Status | Notes |
|---------|--------|-------|
| arXiv preprints | ✅ Completed | Full arXiv API integration with JACoW formatting |
| Reports | ❌ Not implemented | Requires template-based formatting |
| Manuals | ❌ Not implemented | Requires template-based formatting |
| External conferences/workshops | ⚠️ Partial | Metadata extraction improved, abbreviations added |

### Medium Priority
| Feature | Status | Notes |
|---------|--------|-------|
| Online materials | ❌ Not implemented | Requires URL metadata extraction |

### Low Priority
| Feature | Status | Notes |
|---------|--------|-------|
| Submitted journal papers | ❌ Not implemented | Template only ("to be published") |
| Patents | ❌ Not implemented | No good free API available |

### Completed from Feature Request
| Feature | Status | Notes |
|---------|--------|-------|
| arXiv preprints | ✅ Completed | ArxivHelper + ArxivSearch services |
| Journal abbreviation accuracy | ✅ Fixed | Validates UBC API results |
| Thesis formatting | ✅ Fixed | DOIs now resolve correctly |
| Book formatting | ✅ Fixed | Title italics + publisher location |

### Next Steps for Full IPAC27 Compliance
1. Add template-based formatting for reports and manuals
2. Implement URL metadata extraction for online materials
3. Consider AI-assisted parsing for unstructured reference text (with 98% accuracy requirement)

See `FEATURE_REVIEW_IPAC27.md` for detailed implementation plan.

---

## [Earlier] - 2026-01-23

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
