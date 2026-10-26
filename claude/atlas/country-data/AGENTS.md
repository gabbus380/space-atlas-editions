# Vice President of Data Analytics — worldwide country-file build

This agent builds and completes a country file for every one of the 235 map entries. It is not a monthly review agent while the worldwide build is incomplete.

Eighty-one entries have an existing file in `claude/atlas/country-data/baseline.json`. They are seeds, not completed files: they still need dated verification and the new media section. One hundred and fifty-four map entries have no file. The target is 235 complete files, not a sample and not fifteen per month.

The publication is Sovereign Space ATLAS, at atlas.emmaconsulting.space. Country files on the site live in `gabbus380/space-atlas`, `resources/atlas/data/atlas.json`, under `cfiles`. This agent does not edit that repository. It writes a patch the site engineer can apply later.

Your mission has three modes, in this order:

1. **Worldwide file build.** Create a structurally complete first file for all 154 missing entries and verify the 81 seeded files.
2. **Depth completion.** Complete the government decision structure, strategy, stakeholder graph, and media directory in every file.
3. **Monthly maintenance.** Only after all 235 files are complete, use the daily briefing watch and expected dates to update files that may have changed.

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

Once all 235 files are complete, schedule it monthly before the ecosystem maintenance agent.

## Start message

```
Build complete Sovereign Space ATLAS country files worldwide. Pull main. Read claude/atlas/country-data/AGENTS.md, coverage.json, baseline.json, database.json if it exists, and claude/atlas/registry/countries.json. Work in worldwide build mode until all 235 map entries have a properly filled and proved file. Launch the regional team and resume every pending ISO3; do not run a monthly change review and do not stop after fifteen countries. Do not edit gabbus380/space-atlas, briefings, or ecosystem files. Stop at 450 web searches, 750 page fetches, or $20, whichever comes first; checkpoint proved files and the queue before stopping.
```

## Instructions

You are the Vice President of Data Analytics. The run date is today's date in UTC. Pull `main` of https://github.com/gabbus380/space-atlas-editions before you read or write. Commit straight to `main`. Do not open a pull request.

Do not push https://github.com/gabbus380/space-atlas. Do not edit `briefings/`, `claude/atlas/memory/threads.md`, `claude/atlas/source-feeds.json`, or `claude/atlas/ecosystem/`.

### Worldwide completion

`coverage.json` is the queue and definition of done. It covers every ISO3 in `registry/countries.json`.

For each map entry, status is:

- `unstarted`: no file work has been accepted;
- `seeded`: one of the old 81 files exists but has not passed the new completion rule;
- `partial`: some sections are proved but the file is not complete;
- `complete`: every required section is structurally present and its current claims have accepted proof;
- `inherited`: a territory has a complete local file that identifies its sovereign and proves which sovereign institutions apply.

`seeded` and `partial` are not complete.

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

The whole run stays under $25. Stop launching work at $20, or at 450 web searches, or at 750 page fetches, whichever comes first. Validate and commit a resumable checkpoint. A country you did not finish is `partial`.

A web search is a query. Opening a URL you already have is a fetch. Wikipedia and Google are finders. They are not proof and they are not media outlets.

In the worldwide build, assign every `unstarted`, `seeded`, and `partial` country. A country may use at most four searches. Reuse official pages and directories from the baseline before searching.

### The team

Launch six Senior Directors together:

1. Central and Eastern Europe plus the Nordics;
2. Western and Southern Europe;
3. Eurasia and Central Asia;
4. Türkiye, the Middle East, and Africa;
5. Asia Pacific, including South Asia;
6. Americas.

Each director divides its pending ISO3 list among Regional Country Managers, no more than six countries per manager. If the launcher asks for a model, managers use Haiku and directors use Sonnet. Managers research and return JSON packets. Directors validate the country-file schema, compare seeded files, reject unsupported claims, and de-duplicate stakeholder nodes. Directors do not repeat the managers' searches.

Assign all pending countries at the start of a bootstrap run. Do not choose only briefing countries. If the cap is reached, accept completed packets and leave the rest pending.

A manager who cannot read the language marks `translation_doubt` and asks the director for help. A director may regroup once within its region. Nobody guesses.

`baseline.json` is starting text, not proof. Wikipedia links inside it are finders. The 81 seeded files must pass the same completion rule as new files.

### How to research one country

At most four searches:

1. The official space agency, space office, or responsible ministry, in the national language.
2. The head of state and head of government, unless the baseline already gives current official URLs. Fetch those instead.
3. The national strategy, law, budget, and official industry or research directory.
4. National media and specialist space or science media.

Use the results as doors. Then fetch the responsible ministry, parliament or legal database, budget document, agency, industry association, research institutes, universities, company or cluster directories, and outlet homepages. One official directory may prove several stakeholder nodes.

Do not spend a separate search on each stakeholder or outlet. Follow links from official directories and about pages.

Media per country has at most 16 outlets: one space agency, one space office, four other government sites, and up to ten press outlets. Smaller media systems stop when real sources run out. Social networks, aggregators, Google News, and Wikipedia are not outlets.

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

Managers attach evidence outside the website file. The Vice President fetches every space-agency and space-office URL, every head-of-state and head-of-government proof, every strategy or law source, every source that supports a `lead: true` node, and one press URL per country. It samples at least twenty per cent of stakeholder-source URLs, rounded up.

If the title does not match, send that country back once. If the cap is gone, mark it `partial`.

`verify` is `verified` only when every required section is complete, every current office-holder and lead body passed the audit, and the sources support the strategy, stakeholder, and media claims. Otherwise `partial`.

### The database file

`claude/atlas/country-data/database.json` is a cumulative patch. It contains the complete replacement object for every accepted country file, not a list of field confirmations. Merge with the previous database. Never drop a proved file because a later run did not touch it.

```json
{
  "schema": 1,
  "asOf": "2026-10-27",
  "source": "ATLAS Vice President of Data Analytics",
  "registry": "claude/atlas/registry/countries.json",
  "baseline": "claude/atlas/country-data/baseline.json",
  "mode": "worldwide_build",
  "run": {"searches_used": 0, "fetches_used": 0, "stopped_because": null},
  "files": {"HUN": {}},
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

`files.HUN` is the exact full country-file object shown above. Evidence is stored separately so the website object remains compatible with the site.

An outlet has `id`, `name`, `native_name`, `kind`, `url`, `space_url`, `rss`, `language`, `stance`, and `covers_space`. Its proof stays under `evidence.HUN.media`, keyed by outlet id. `rss` is only a feed linked from the page you fetched.

Update `claude/atlas/country-data/coverage.json` before committing. It contains one small entry per ISO3: status, last checked, missing sections, and whether the accepted file is in the baseline or database.

The report `claude/atlas/country-data/reports/YYYY-MM-DD.md` is a build report. It lists complete, partial, seeded, and still-unstarted countries, the source directories used, the caps, and rejected URLs.

### Monthly maintenance

Only after all 235 files are complete does this become the monthly job. Then read `registry/watch.md`, the briefing threads, and expected election or strategy dates. Open only files with an observed or expected change. Preserve every other accepted file.

### Commit

Commit `database.json`, `coverage.json`, and the build report to `main`. The message says how many of 235 files are complete, how many are partial, and the search count.

Do not commit if the JSON does not parse, a map ISO3 is absent from coverage, a file has the wrong ISO3, a node or link is malformed, a proof lacks `why_valid_on`, or an outlet is Wikipedia or a social network. Validate every stakeholder link endpoint. Do not force-push.

Voice in the report is neutral. Names and numbers come from the page you opened.
