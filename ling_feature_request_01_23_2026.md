# IPAC27 AI-Enhanced Editing Tool

## Goals

1. A website interface that can be accessed by both authors and editors.
2. Detect the reference type and format references according to JACoW rules.
3. Do not include any made-up information; accuracy must exceed 98%.
4. Fill in missing field information when possible (e.g., DOI via Google or Crossref; journal abbreviations via ISO4).

---

## Improvements Needed Based on Current JACoW Reference Search Tools

| Reference Type | Status (JACoW tool) | Improvement Needed | Goal | Priority |
|----------------|---------------------|-------------------|------|----------|
| External conferences / workshops / symposia | Partially formatted | Inconsistent metadata and formatting | Full JACoW-compliant formatting | **High** |
| Published journal papers | Well formatted | Incorrect or inconsistent abbreviations | Standardized, fully compliant formatting | **High** |
| arXiv preprints | Not recognized by tool, no result | — | Align with JACoW standards | **High** |
| Reports | Not recognized by tool, no result | — | Support structured report formatting | **High** |
| Books / book chapters | Not recognized by tool, no result | — | Full book/chapter formatting compliance | **High** |
| Manuals | Not recognized by tool, no result | — | Support manual formatting | **High** |
| Theses | Not recognized by tool, no result | — | Enable proper thesis formatting | **Medium** |
| Online materials | Not recognized by tool, no result | — | Support online materials formatting | **Medium** |
| JACoW conference papers | Well formatted | Latest conferences may not be included | Maintain full compliance | **Low** |
| Submitted journal papers | Not recognized by tool, no result | — | Format submitted papers in JACoW style | **Low** |
| Patents | Not recognized by tool, no result | — | Standardized patent formatting | **Low** |

---

## References

1. JACoW formatting citations: https://www.jacow.org/Authors/FormattingCitations
2. JACoW reference search tool: https://refs.jacow.org/
3. GitHub JACoW reference search engine: https://github.com/JACoW-org/refdb
4. GitHub JACoW CatScan: https://github.com/AustralianSynchrotron/jacow-validator
