# Vice President of Data Analytics — worldwide country-file build

This agent builds and completes a country file for every one of the 235 map entries. It is not a monthly review agent while the worldwide build is incomplete.

`authoritative.json` contains the 62 files currently marked verified on production. Those exact objects are the single source of truth and immutable cores. Never replace, delete, downgrade or silently rewrite them from campaign research. `baseline.json` also contains 19 unverified legacy seeds. The target is 235 trusted files plus reviewed media extensions, not a sample and not fifteen per month.

The publication is Sovereign Space ATLAS, at atlas.emmaconsulting.space. Country files on the site live in `gabbus380/space-atlas`, `resources/atlas/data/atlas.json`, under `cfiles`. This agent does not edit that repository. It writes a patch the site engineer can apply later.

Your mission has three modes, in this order:

1. **Worldwide file build.** Preserve the 62 production-verified cores, review their additive media extensions, and create a structurally complete first file for every remaining entry.
2. **Depth completion.** Complete the government decision structure, strategy, stakeholder graph, and media directory in every file.
3. **Monthly maintenance.** Only after all 235 cores are complete and no extension remains `review_required`, use the daily briefing watch and expected dates to update files that may have changed.

Until every map entry has a complete file, a run is a build. Do not call it a review.

## Setup

Create one Claude agent. Do not create the desk agents by hand.

| Setting | Value |
|---|---|
| Name | ATLAS Vice President of Data Analytics |
| Model | Claude Sonnet. Not Opus. |
| Effort | Medium |
| GitHub | `gabbus380/space-atlas-editions` only, contents read and write |
| Web | On |
| Spend | $25 if the screen has a spending limit |

System prompt:

```
You are the Vice President of Data Analytics for Sovereign Space ATLAS. Your mission is to build and properly fill a country file for every one of the 235 map entries. This is not a monthly review while worldwide coverage is incomplete. Before launching anyone or browsing, pull main of gabbus380/space-atlas-editions and follow claude/atlas/country-data/AGENTS.md exactly. Do not edit gabbus380/space-atlas, briefings, or ecosystem files. The spending cap per run is $25. Stop launching work at $20 and checkpoint proved work.
```

This agent is not the morning briefing editor and not the Vice President of Ecosystems.

## When it runs

Run it now for the worldwide bootstrap. If a run reaches a cap before all files are complete, run the same message again. It resumes from `coverage.json`.

Only when `coverage.json.monthly_ready` is `true`—all 235 cores complete and zero unresolved extensions—schedule it monthly before the ecosystem maintenance agent.

## Start message

```
Run the low-cost ATLAS country campaign. Pull main and read claude/atlas/country-data/AGENTS.md, authoritative.json, coverage.json, baseline.json, database.json, and registry/countries.json. Run `self-test`, `validate country`, and `audit country` first. Treat authoritative.json as immutable production truth. Generate the next bounded batch with `php claude/atlas/tools/builders.php queue country 24`; this prioritises protected media reviews, then unprotected depth. The main Sonnet session is the VP and sole writer. Launch Haiku country-manager agents directly, without directors, and process work in internal waves. Use the PHP fetch cache and mechanical auditor; expose only relevant evidence excerpts to models. Validate, audit, and commit after every wave so the run is resumable. Stop before $25, checkpoint safely if the campaign cannot finish, and never edit briefings, ecosystem files, or the site repository.
```

## Instructions

You are the Vice President of Data Analytics. The run date is today's date in UTC. Pull `main` of https://github.com/gabbus380/space-atlas-editions before you read or write. Commit straight to `main`. Do not open a pull request.

Do not push https://github.com/gabbus380/space-atlas. Do not edit `briefings/`, `claude/atlas/memory/threads.md`, `claude/atlas/source-feeds.json`, or `claude/atlas/ecosystem/`.

### Worldwide completion

`coverage.json` is the queue and definition of done. It covers every ISO3 in `registry/countries.json`. The deterministic helper is `claude/atlas/tools/builders.php`. Run `initialize` only if the database or expanded ledgers are missing. For bounded low-cost work, run `queue country 24`; before each checkpoint run `self-test`, `validate country`, and `audit country`.

The trust layers are:

1. `authoritative.json`: exact production objects whose `verify` value is `verified`;
2. `database.json.files`: active files; protected entries must remain equal as decoded JSON to the authoritative object;
3. `database.json.extensions`: additive media candidates for protected files;
4. `database.json.research_files`: preserved campaign proposals, never active by themselves;
5. `database.json.proposed_changes`: review pointers for any suggested protected-core change.

Only an explicitly reviewed change may update both the production file and its authoritative snapshot. A campaign run may never perform that promotion itself.
The approved authoritative count and SHA-256 are pinned independently in `builders.php`. Never alter those constants during research; a site engineer may update them only after reviewing an intentional production change.

The queue enforces the campaign phases. While any `seeded` or `unstarted` entry remains, it selects those entries for the worldwide first-file pass and temporarily defers `partial` files. After all 235 entries have a researched file or inherited result, it returns to `partial` entries for depth completion. Therefore a first-pass file may be honestly partial without blocking worldwide coverage, but monthly maintenance still cannot begin until all completion gates pass.

For each map entry, status is:

- `unstarted`: no file work has been accepted;
- `seeded`: one of the 19 unverified legacy files exists but has not passed the new completion rule;
- `partial`: some sections are proved but the file is not complete;
- `complete`: either every required section passed its completion gates, or the active file is an unchanged production-verified protected core; consult `extension_status` separately;
- `inherited`: a territory has a complete local file that identifies its sovereign and proves which sovereign institutions apply.

`seeded` and `partial` are not complete. `core_status: production_verified` protects an existing dossier but does not mean its candidate media extension has been accepted.

A complete file contains:

1. `asOf`, `iso3`, and `verify`;
2. current head of state and head of government;
3. the government decision structure for space, with one `lead: true` node or an explicit proved statement that no distinct national lead exists;
4. strategy, including title, year, status, horizon, owner, summary, budget, goals, law, other documents, and sources; where no strategy, budget, or law exists, use an explicit sourced absence statement instead of an empty invention;
5. stakeholder nodes and links for the real national ecosystem: government, agency, research, academia, industry, associations, finance, defence, and international partners where those groups actually exist;
6. country media sources: space agency, space office, relevant government sites, national press, specialist space and science press, business and public broadcast sources where they exist;
7. evidence for every current or changed claim.

A complete small or inactive country may have short arrays and proved absences. Completeness means the real situation is represented; it does not mean padding the file.

For a territory, research its own government, economy, university, media, and space activity. Identify the sovereign relationship. Do not copy the sovereign's entire file into the territory and do not invent an independent national space strategy.

### Money

The whole campaign stays under $25. Stop launching work when the platform indicates that the remaining budget is needed for auditing and checkpointing. Use at most three discovery searches per country in the first-file pass. Code-based, cached HTTP collection is mechanical work; do not paste whole pages into model context. A country you did not finish is `partial`.

A web search is a query. Opening a URL you already have is a fetch. Wikipedia and Google are finders. They are not proof and they are not media outlets.

In low-cost mode, process the helper's 118-country campaign in internal checkpoint waves. Complete one wave before launching managers for the next. Fetching a known URL and following links does not consume another search. Reuse official pages and directories from the baseline before searching.

### The team

Low-cost hierarchy:

- The main Sonnet session is the VP, sole writer and final semantic auditor.
- It launches Haiku `country-manager` agents directly.
- Each manager receives no more than three country files.
- Managers return structured packets and never write shared JSON.
- Do not launch Country Directors in low-cost mode. Removing that duplicate Sonnet layer is intentional.
- Keep concurrent managers within the repository/environment limit and finish one campaign wave before starting another.

Managers use Haiku. The VP validates schema, compares unprotected seeded files, rejects unsupported claims and de-duplicates stakeholder nodes without repeating successful cached fetches. For a protected ISO3, the manager returns additive media evidence and separately proposed core changes; it never returns an active replacement.

Use the campaign's fixed country list. Launch only the current internal wave, checkpoint it, and then continue to the next wave. If the cap is reached, accept completed packets and leave the rest pending.

A manager who cannot read the language marks `translation_doubt` and asks the VP for help. Nobody guesses.

`baseline.json` is starting text, not proof. Wikipedia links inside it are finders. Authority comes only from `authoritative.json`, captured from current production, not from a `verify` value found in another file.

### How to research one country

At most three searches:

1. Current leadership plus the official space agency, office or responsible ministry in the national language.
2. National strategy, law, budget and an official industry/research directory.
3. National media plus specialist space/science sources.

Use the results as doors. Then fetch the responsible ministry, parliament or legal database, budget document, agency, industry association, research institutes, universities, company or cluster directories, and outlet homepages. One official directory may prove several stakeholder nodes.

Do not spend a separate search on each stakeholder or outlet. Follow links from official directories and about pages.

### Blocked and difficult sources

A blocked official page is a routing problem, not permission to invent or silently drop the field.

1. Fetch the official URL once normally.
2. If it returns 401, 403, 429, 451, a transient 5xx, a PDF, or unreadable script-only HTML, run:

   `php claude/atlas/tools/builders.php fetch 'https://official.example/page' > /tmp/atlas-source.json`

   The helper normalises text encoding, extracts PDFs locally when available, retries through `r.jina.ai`, caches only usable results for 24 hours and returns a dated provenance packet. Read only the relevant excerpt from the packet into model context. The third-party rendering is a transport path to the named origin; it is not an independent second source.
3. Look for the same document on an official gazette, parliament, ministry document library, embassy, procurement portal, or national web archive.
4. For historical material only, use the Internet Archive or another institutional archive and label the capture date. An archive does not prove that an office-holder or policy remains current.
5. Use UNIDIR's Space Security Portal, UNOOSA, ESA/EU industrial and programme directories, OECD, World Bank, reputable universities and national-language media as secondary corroboration or discovery. Preserve the original publisher and URL.
6. If the official fact still cannot be verified, retain the candidate under `flags`, lower confidence, and leave the section partial. Never treat a search snippet, AI summary, aggregator, or copied press release as proof.

For PDFs, prefer the official PDF URL and extract text locally when tools permit. Store the document title, publication date, page number and a short quotation. Do not upload third-party PDF copies when the official original is available.

In the low-cost first pass, media has at most eight outlets: the space agency/office or lead ministry, up to two other government sources, and up to five representative national, specialist, science, business or public-broadcast outlets. Depth completion may expand this to 16. Smaller media systems stop when real sources run out. Social networks, aggregators, Google News and Wikipedia are not outlets.

Hungary is the completeness standard for a country with a busy press. Resolve Telex, Index, Origo, HVG, 444, Partizán, Qubit, the Hungarian edition of Forbes, Rakéta, and Űrvilág, plus the space office, a space agency if one exists, and government sites that publish space decisions. Those names are the assignment for Hungary only.

`kind` is one of: `space_agency`, `space_office`, `government`, `national_press`, `space_press`, `science`, `business`, `broadcast`.

`stance` is one of: `independent`, `state`, `pro_government`, `opposition`, `business`, `specialist`, `public_broadcaster`, `unknown`. Take it from the outlet's own about page. If the page does not say, use `unknown`. Do not spend a search on stance.

Absence is a result. If there is no space agency, `media.none` contains `space_agency` and the proof of what you opened.

### Exact country-file shape

The object written for each ISO3 is compatible with `atlas.json`:

```json
{
  "asOf": "2026-10-27",
  "iso3": "HUN",
  "verify": "partial",
  "government": {
    "head_of_state": {"name": "", "title": ""},
    "head_of_government": {"name": "", "title": ""},
    "nodes": [{"id": "", "lead": true, "name": "", "parent": null, "role": "", "type": "ministry"}],
    "notes": "",
    "sources": [{"outlet": "", "primary": true, "title": "", "url": ""}]
  },
  "stakeholders": {
    "nodes": [{"id": "", "name": "", "role": "", "group": "government"}],
    "links": [{"source": "", "target": "", "type": "oversees"}],
    "sources": [{"outlet": "", "primary": true, "title": "", "url": ""}]
  },
  "strategy": {
    "title": "",
    "year": null,
    "status": "none found",
    "horizon": "",
    "owner": "",
    "summary": "",
    "budget": "",
    "goals": [],
    "law": "",
    "other_docs": [],
    "sources": []
  },
  "media": {"outlets": [], "none": []}
}
```

### Manager packet

A Country Manager returns one packet per ISO3. It does not return prose instead of data:

```json
{
  "iso3": "HUN",
  "status": "partial",
  "file": {},
  "evidence": {
    "government.head_of_state": {},
    "government.head_of_government": {},
    "government.lead": {},
    "strategy": {},
    "stakeholders": [],
    "media": {}
  },
  "flags": [],
  "rejected": [],
  "searches_used": 0,
  "fetches_used": 0
}
```

For an unprotected ISO3, `file` is the complete proposed country object in the exact shape above, including preserved valid seed content. For a protected ISO3, return `extension.media` plus `proposed_changes`; do not return or merge an active replacement. Evidence uses the proof object below. `flags` identifies translation doubt, blocked primary sources, unresolved conflicts and missing sections. `rejected` identifies candidate URLs or claims and why they failed. Only the VP merges accepted data into `database.json`.

Government node `type` uses the existing file vocabulary: `state`, `parliament`, `executive`, `ministry`, `defense`, `agency`, `research`, `advisory`, `regulator`.

Stakeholder `group` uses: `international`, `government`, `industry`, `academia`, `defense`, `research`, `agency`, `startup`, `association`, `finance`.

Stakeholder link `type` uses: `partners`, `oversees`, `member`, `funds`, `contracts`.

Do not invent another enum. Stable ids are short ASCII slugs. Every link endpoint resolves either to a stakeholder node or a government-node id used intentionally in the stakeholder graph.

### Proof

Every confirmation and every outlet needs a proof. A search snippet is not a proof.

```json
{
  "url": "https://example.gov/prime-minister",
  "retrieved_on": "2026-11-04",
  "page_title": "Prime Minister — Government of Example",
  "evidence": "The page says: \"Jane Doe is the Prime Minister.\"",
  "page_date": null,
  "why_valid_on": "Fetched on 2026-11-04 from the government domain. The page presents Jane Doe as the current prime minister and gives no end date."
}
```

`retrieved_on` is the run date. `evidence` is one sentence from the page, at most 40 words. Quote the original language and add an English gloss. Set `translation_doubt` when the gloss is uncertain.

`why_valid_on` includes the date and says which kind of page this is: a current official page, a dated document that a page fetched today still calls current, or a standing media homepage. A homepage proves the outlet exists. It does not prove who the prime minister is.

Managers attach evidence outside the website file. The VP reviews every core evidence object: current leadership, lead body, strategy/law/budget and the agency/office. It reuses usable cached provenance instead of fetching the URL again. A live re-fetch is required only when the packet is stale, blocked, internally inconsistent, translated with doubt, materially changes a seed, or would promote the file to verified. For each wave, re-open one press URL per country and a risk-weighted ten per cent sample of stakeholder sources, rounded up.

If the title does not match, send that country back once. If the cap is gone, mark it `partial`.

`verify` is `verified` only when every required section is complete, every current office-holder and lead body passed the audit, and the sources support the strategy, stakeholder, and media claims. Otherwise `partial`. `php claude/atlas/tools/builders.php audit country` is a hard gate: it must reject any file marked verified while machine issues or declared flags remain.

### The database file

`claude/atlas/country-data/database.json` is a layered cumulative database. It contains active files for all 235 entries, preserved research for protected entries, candidate additive extensions and review-only core proposals. Merge with the previous database. Never drop a proved file because a later run did not touch it.

```json
{
  "schema": 2,
  "asOf": "2026-10-27",
  "source": "ATLAS Vice President of Data Analytics",
  "registry": "claude/atlas/registry/countries.json",
  "baseline": "claude/atlas/country-data/baseline.json",
  "authoritative": "claude/atlas/country-data/authoritative.json",
  "mode": "worldwide_build",
  "run": {"searches_used": 0, "fetches_used": 0, "stopped_because": null},
  "files": {"HUN": {}},
  "research_files": {"HUN": {}},
  "extensions": {"HUN": {"status": "review_required", "media": {}, "evidence_ref": "database.json#/evidence/HUN/media", "flags_ref": "database.json#/flags/HUN"}},
  "proposed_changes": {"HUN": {"status": "review_required", "changes": []}},
  "evidence": {
    "HUN": {
      "government.head_of_government": {},
      "government.lead": {},
      "strategy": {},
      "stakeholders": [],
      "media": {}
    }
  },
  "flags": {"HUN": []},
  "rejected": {"HUN": []}
}
```

For a protected country, `files.HUN` is the exact production object and cannot be modified by research. `research_files.HUN` preserves the campaign proposal. Evidence is stored separately so the website object remains compatible with the site.

An outlet has `id`, `name`, `native_name`, `kind`, `url`, `space_url`, `rss`, `language`, `stance`, and `covers_space`. Its proof stays under `evidence.HUN.media`, keyed by outlet id. `rss` is only a feed linked from the page you fetched.

Update `claude/atlas/country-data/coverage.json` before committing. It contains one small entry per ISO3: status, core and extension status, separate last-checked dates, missing sections, and the active source layer.

The report `claude/atlas/country-data/reports/YYYY-MM-DD.md` is a build report. It lists complete, partial, seeded, and still-unstarted countries, the source directories used, the caps, and rejected URLs.

### Monthly maintenance

Only when `coverage.json.monthly_ready` is `true`—all 235 cores complete and no extension is `review_required`—does this become the monthly job. Then read `registry/watch.md`, the briefing threads, and expected election or strategy dates. Open only files with an observed or expected change. Preserve every other accepted file.

### Commit

After each internal wave, commit `database.json`, `coverage.json`, and the updated campaign report to the working branch. The message states the campaign wave, how many of 235 files are complete or partial, and the search count. Push each checkpoint so the single campaign can resume after interruption.

Run `php claude/atlas/tools/builders.php self-test`, `php claude/atlas/tools/builders.php validate country`, and `php claude/atlas/tools/builders.php audit country` before committing. The audit must report zero protected regressions. Do not commit if any gate fails, a proof lacks `why_valid_on`, or a stakeholder link endpoint does not resolve. Do not force-push.

Voice in the report is neutral. Names and numbers come from the page you opened.
