# Feature Review: IPAC27 AI-Enhanced Editing Tool

**Date:** 2026-01-23
**Reviewer:** Claude (AI Assistant)
**Source:** `ling_feature_request_01_23_2026.md`

---

## Executive Summary

Ling's feature request outlines an AI-enhanced reference formatting tool for IPAC27 that serves both authors and editors. The current JACoW Reference Search tool handles JACoW conference papers and some journal articles well, but lacks support for many common reference types.

**Key Requirements:**
1. Web interface accessible to authors and editors
2. Auto-detect reference types and format to JACoW standards
3. 98%+ accuracy with no fabricated information
4. Auto-fill missing fields (DOI, journal abbreviations)

---

## Feature Gap Analysis

### Currently Supported (Minor Improvements Needed)

| Reference Type | Current Status | Work Required |
|----------------|----------------|---------------|
| JACoW conference papers | Well formatted | Keep conference DB updated |
| Published journal papers | Partial | Fix abbreviation inconsistencies |
| External conferences | Partial | Improve metadata extraction |

### Not Currently Supported (New Development Required)

| Reference Type | Priority | Complexity |
|----------------|----------|------------|
| arXiv preprints | **High** | Medium |
| Technical reports | **High** | Medium |
| Books / book chapters | **High** | Medium |
| Manuals | **High** | Low |
| Theses | Medium | Low |
| Online materials | Medium | Medium |
| Submitted papers | Low | Low |
| Patents | Low | High |

---

## Proposed Feature List

### Phase 1: Core Identifier Support (HIGH PRIORITY)

#### 1.1 Reference Type Detection
- Expand `DoiHelper` to `ReferenceTypeDetector`
- Detect multiple identifier types from user input

**Identifiers to Support:**
| Type | Pattern | Example |
|------|---------|---------|
| DOI | `10\.\d{4,9}/...` | `10.1038/s41586-025-10013-1` |
| arXiv | `arXiv:\d{4}\.\d{4,5}` | `arXiv:2301.12345` |
| ISBN-10/13 | `ISBN[-: ]?(97[89])?\d{9}[\dX]` | `ISBN 978-3-16-148410-0` |
| PMID | `PMID:?\s*\d+` | `PMID:12345678` |
| URL | Standard URL regex | `https://example.com/doc.pdf` |

**Files to create:**
- `src/Service/ReferenceTypeDetector.php`

#### 1.2 arXiv Integration
- Query arXiv API for preprint metadata
- Extract: title, authors, abstract, categories, linked DOI
- Format according to JACoW arXiv citation style

**API Endpoint:** `https://export.arxiv.org/api/query`

**Files to create:**
- `src/Service/ArxivSearch.php`
- `src/Entity/ArxivCache.php`

#### 1.3 ISO4 Journal Abbreviations
- Replace/supplement UBC lookup with LTWA-based abbreviations
- Build local abbreviation database for reliability
- Handle edge cases (journal name variations)

**Data Source:** ISSN LTWA (List of Title Word Abbreviations)

**Files to modify:**
- `src/Service/ExternalSearch.php` (lookupAbbreviation method)
- `src/Entity/Journal.php` (add ISSN field)

---

### Phase 2: Extended Reference Types (HIGH PRIORITY)

#### 2.1 Book/Chapter Support
- Integrate OpenLibrary API for ISBN lookups
- Support both full books and individual chapters
- Extract: title, authors, publisher, year, edition, ISBN

**API Endpoint:** `https://openlibrary.org/isbn/{isbn}.json`

**Files to create:**
- `src/Service/BookSearch.php`
- `src/Entity/BookCache.php`

#### 2.2 Technical Report Formatting
- Template-based formatting (no API needed)
- Support common report formats (lab reports, institutional)
- Fields: authors, title, report number, institution, year

**Files to create:**
- `src/Service/JacowFormatter.php`
- `config/jacow_formats.yaml`

#### 2.3 Manual Formatting
- Template-based formatting
- Fields: title, organization, edition, year, URL

---

### Phase 3: JACoW Format Engine

#### 3.1 Configurable Format Templates
Create a centralized formatting engine with templates per reference type:

```yaml
# config/jacow_formats.yaml
journal_article:
  template: "{authors}, \"{title},\" {journal}, vol. {volume}, no. {issue}, pp. {pages}, {month} {year}. {doi}"

arxiv_preprint:
  template: "{authors}, \"{title},\" arXiv:{arxiv_id} [{category}]"

book:
  template: "{authors}, {title}. {publisher}, {city}, {year}."

book_chapter:
  template: "{authors}, \"{chapter_title},\" in {book_title}, {editors}, Eds. {publisher}, {city}, {year}, pp. {pages}."

thesis:
  template: "{author}, \"{title},\" {degree} thesis, {department}, {institution}, {city}, {country}, {year}."

report:
  template: "{authors}, \"{title},\" {institution}, {location}, Rep. {report_number}, {year}."

manual:
  template: "{title}, {organization}, {edition}, {year}. {url}"

patent:
  template: "{inventors}, \"{title},\" {country} Patent {number}, {date}."

online:
  template: "{authors}, \"{title},\" {website}. {url} (accessed {access_date})"
```

**Files to create:**
- `src/Service/JacowFormatter.php`
- `src/Config/JacowFormatConfig.php`
- `config/jacow_formats.yaml`

---

### Phase 4: Medium Priority Features

#### 4.1 Thesis Support
- Template-based formatting
- Optional: ProQuest/institutional repository lookups
- Fields: author, title, degree type, department, institution, year

#### 4.2 Online Materials
- URL metadata extraction (Open Graph, meta tags)
- Wayback Machine integration for archived URLs
- Access date tracking

**Files to create:**
- `src/Service/UrlMetadataExtractor.php`

---

### Phase 5: AI Enhancement (Optional)

#### 5.1 Free-Text Reference Parsing
- Parse unstructured reference text into structured fields
- Use local LLM (Ollama) or API (Anthropic Claude)
- Confidence scoring with human review for low-confidence results

**Accuracy Safeguards:**
- AI extracts fields, does NOT generate identifiers
- All DOIs/ISBNs verified against authoritative sources
- Low-confidence results flagged for manual review
- No data returned unless verified

**Files to create:**
- `src/Service/AiReferenceParser.php`
- `src/Service/OllamaClient.php` (or `AnthropicClient.php`)

#### 5.2 Smart Query Understanding
- Natural language to structured query conversion
- Example: "Zhang paper about cooling 2021" → structured search

---

### Phase 6: Low Priority Features

#### 6.1 Patent Support
- Complex due to lack of free APIs
- Options: USPTO API, manual entry templates
- Fields: inventors, title, patent number, country, date

#### 6.2 Submitted Papers
- Template only ("to be published" format)
- No external lookup possible

---

## UI Enhancements

### New Interface Elements

1. **Reference Type Indicator**
   - Display detected type: "Detected: arXiv preprint"
   - Allow manual override if incorrect

2. **Confidence Score**
   - Visual indicator (high/medium/low)
   - Warning for low-confidence extractions

3. **Missing Fields Warning**
   - List missing required fields
   - Suggest where to find missing information

4. **Preview Panel**
   - Side-by-side: raw input vs. formatted output
   - Edit capability before final export

5. **Multiple Format Export**
   - One-click export to: Text, BibTeX, LaTeX, Word
   - Batch export for multiple references

---

## API Dependencies Summary

| API | Purpose | Auth Required | Rate Limit |
|-----|---------|---------------|------------|
| CrossRef | DOI metadata, text search | No (polite pool with email) | 50/sec |
| DOI.org | Citation formatting | No | Reasonable use |
| arXiv | Preprint metadata | No | 1 req/3 sec |
| OpenLibrary | Book/ISBN lookup | No | None stated |
| UBC Journal Abbrev. | Journal abbreviations | No | Unknown |
| USPTO | Patent data | API key | Varies |

---

## Implementation Priority Matrix

```
                    HIGH VALUE
                        │
    ┌───────────────────┼───────────────────┐
    │                   │                   │
    │  arXiv Support    │  AI Parsing       │
    │  ISO4 Abbrev.     │  Smart Query      │
    │  Books/ISBN       │                   │
    │                   │                   │
LOW ├───────────────────┼───────────────────┤ HIGH
EFFORT                  │                   EFFORT
    │                   │                   │
    │  Report Templates │  Patent Support   │
    │  Manual Templates │  Full UI Redesign │
    │  Thesis Templates │                   │
    │                   │                   │
    └───────────────────┼───────────────────┘
                        │
                    LOW VALUE
```

---

## Success Metrics

1. **Coverage**: Support 10+ reference types (currently ~3)
2. **Accuracy**: 98%+ for identifier-based lookups
3. **Completeness**: Auto-fill DOI for 80%+ of journal articles
4. **User Satisfaction**: Reduce manual formatting time by 50%+

---

## Next Steps

1. Review and prioritize features with stakeholders
2. Create detailed technical specifications for Phase 1
3. Set up development environment and testing framework
4. Begin implementation with Reference Type Detection
5. Iterate based on editor feedback

---

## References

- JACoW Formatting Citations: https://www.jacow.org/Authors/FormattingCitations
- JACoW Reference Search: https://refs.jacow.org/
- arXiv API: https://info.arxiv.org/help/api/
- OpenLibrary API: https://openlibrary.org/developers/api
- CrossRef API: https://www.crossref.org/documentation/retrieve-metadata/rest-api/
