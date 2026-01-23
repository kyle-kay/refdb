# RefDB Assessment & Future Directions

## Current State Assessment

### What the app does well

- Clean reference search and formatting (IEEE, BibTeX, LaTeX, Word)
- External DOI lookup with caching
- Conference proceedings database (JACoW-specific)

### Pain points observed

- Requires MongoDB Atlas + web server infrastructure
- External API dependencies (CrossRef, DOI.org, UBC)
- No offline capability
- Single point of failure with centralized hosting

---

## AI Integration Opportunities

### 1. Reference Parsing from Messy Input

Users often paste poorly formatted references. AI could:

**Input:** `"Zhang et al Nature 2026 barocaloric"`  
**Output:** Structured reference with DOI lookup

- Use a small local LLM (Llama 3, Phi-3) to extract author, title, journal, year
- Then query CrossRef/DOI.org with structured data

### 2. Smart Query Understanding

**Input:** `"that paper about electron cooling from the 2021 conference in Russia"`  
**Output:** Searches for COOL'21, Novosibirsk, cooling-related papers

- Natural language → structured search query
- Could run locally with ~4GB model

### 3. Citation Style Conversion

AI could handle edge cases that regex misses:

- Author name variations (et al. rules, non-Latin names)
- Journal abbreviation disambiguation
- Conference naming inconsistencies

### 4. Duplicate Detection

- Semantic similarity matching for "same paper, different format"
- Preprint ↔ published version linking

---

## Modern Self-Hosted Deployment Options

### Option A: Desktop App (Electron/Tauri)

| Aspect | Details |
|--------|---------|
| Tech | Tauri (Rust) + existing PHP as local server, or rewrite frontend in React |
| Database | SQLite instead of MongoDB |
| Offline | Full offline capability with periodic sync |
| Distribution | Single installer per OS |
| AI | Bundle small local LLM (GGUF format, ~2-4GB) |

**Pros:** No hosting costs, works offline, editors control their data  
**Cons:** Requires rewrite, update distribution complexity

### Option B: Docker Compose Package

```yaml
# docker-compose.yml editors would run
services:
  refdb:
    image: jacow/refdb:latest
    ports: ["8080:80"]
  mongodb:
    image: mongo:6
  ollama:  # Optional local AI
    image: ollama/ollama
```

**Pros:** Minimal changes to existing code, familiar deployment  
**Cons:** Requires Docker knowledge, still needs a machine to run

### Option C: Static Site + API (JAMstack)

- Convert frontend to static site (Next.js/Nuxt)
- Use serverless functions for search (Cloudflare Workers, Vercel)
- Database: Turso (SQLite edge) or PlanetScale
- Free tier hosting available

**Pros:** Near-zero hosting cost, global CDN, scales automatically  
**Cons:** Significant rewrite, vendor dependencies

### Option D: Browser Extension + Local Storage

- Chrome/Firefox extension with IndexedDB
- Syncs with central database when online
- Works offline with cached data
- Could integrate with Overleaf, Google Docs

**Pros:** Zero infrastructure per editor, works in writing context  
**Cons:** Limited to browser, storage limits

---

## Recommended Path

Given JACoW's context (academic community, limited funding, volunteer-driven):

### Phase 1: Immediate (Low Effort)

- Add PWA support (offline caching of recent searches)
- Export full database as SQLite download for offline use
- Improve error handling when external APIs fail

### Phase 2: Medium Term

- Docker Compose package for self-hosting editors
- Replace MongoDB with SQLite (simpler, portable)
- Add optional Ollama integration for AI features

### Phase 3: Long Term

- Tauri desktop app with embedded database
- Local AI for reference parsing
- Peer-to-peer sync between editors (no central server needed)

---

## AI-Specific Architecture

If embedding AI, the recommended flow:

```
┌─────────────────────────────────────────────┐
│  User Input: "Zhang Nature 2026 cooling"    │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Local LLM (Ollama/llama.cpp)               │
│  - Extract: author, keywords, year, journal │
│  - Confidence score                         │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Structured Query                           │
│  {author: "Zhang", journal: "Nature",       │
│   year: 2026, keywords: ["cooling"]}        │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  Search Chain:                              │
│  1. Local SQLite (instant)                  │
│  2. CrossRef API (if not found)             │
│  3. DOI.org (for formatting)                │
└─────────────────────────────────────────────┘
```

### Model Recommendations

| Model | Use Case |
|-------|----------|
| `phi-3-mini` (3.8B) | Good for structured extraction, runs on CPU |
| `llama-3.2-3b` | Better reasoning, needs decent GPU |
| `nomic-embed-text` | For semantic search/deduplication |

---

## Questions to Consider

1. **Who are the primary users?** Conference editors, paper authors, or both?
2. **How important is offline access?** Critical for conferences with poor WiFi?
3. **Is there budget for any hosting?** Even $5/month opens options
4. **Would editors install software?** Or must it be purely web-based?
5. **Data sovereignty concerns?** Some institutions may not want data on external servers
