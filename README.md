Agents start at `claude/atlas/AGENTS.md`. Find your cause there and follow only that section.

# ATLAS editions

This repository holds the daily briefing and editor's ledger, plus isolated staging areas for the worldwide country-file and ecosystem builders. Builder work stays under `claude/atlas/country-data/` and `claude/atlas/ecosystem/`; it is not published to the website automatically.

Add the edition as `briefings/YYYY-MM-DD.json`.

The edition lists all eight desks, in this order, and each desk has at least one item: `esa_eu`, `cee`, `nordics`, `west`, `eurasia`, `mena`, `asia_pac`, `americas`. A desk with no fresh development gets one lookback of the last five days. On the first Tuesday of the month, UTC, every desk also gets an overview of the previous calendar month. That Tuesday is the monthly edition: a Saturday or Sunday the 1st is not a publishing morning, and a Monday the 1st is one day too soon.

Keep `audio_script` and `audio_sections` in the JSON, and cover every desk in the narration. The site records the Kokoro voice from that script. Do not upload an mp4 unless you already have the file at `audio/brief_YYYY-MM-DD.mp4`.

Ledger files belong in `claude/atlas/ledger/`. The run report belongs in `claude/atlas/reports/YYYY-MM-DD.md`.

Memory editions for 1 September through 22 October 2026 are `briefings/YYYY-MM-DD.json`, indexed in `claude/atlas/memory/chronicle.md`. They are a low-confidence record from Space.com, SpaceNews, Spaceflight Now, Payload, NASASpaceflight, Ars Technica, The Verge, MIT Technology Review, Universe Today, SpaceQ, and SpacePolicyOnline. The editions of 23–26 October are the fuller filings.

The memory is `claude/atlas/memory/threads.md`. Read it before writing. File an item only when today's packet changes a thread's last state, or a next date arrives. After publishing, update the thread.

The reading packet for a morning is `claude/atlas/context/YYYY-MM-DD.md`. It is built by reading the feeds, and by a text reader when a page blocks a normal download. The editor writes from that file and does not browse.

The standing source list is `claude/atlas/source-feeds.json`. Read those feeds and newsrooms before the open web. Each link was checked on 26 October 2026. A link marked unreachable refused or timed out on an automated read.

Prefer three independent sources, one in the country's own language and one from a government or embassy page. If that mix cannot be found, five independent sources is still the high bar. A desk may also file a lower-confidence item rather than go quiet. Medium means two independent sources from the standing list. Low means one named source from that list, and the summary says so in the first sentence. Set confidence to high, medium, or low. The same wire copied by three sites counts once. Social media, aggregators, and Wikipedia still do not count. A quiet desk item is only for a five-day window with nothing even at low confidence.

The site copies a file that passes its checks at 05:00 and 05:40 UTC, before the 06:20 UTC email. A briefing that contains a key, a private key, or a token is refused and is not published.

Do not put application code or secrets in this repository.

Pull `main` at the start of a run. A session opened earlier in the day is looking at an older file.

## Worldwide builders

Country and ecosystem agents start from their own instructions:

- `claude/atlas/country-data/AGENTS.md`
- `claude/atlas/ecosystem/AGENTS.md`

They do not edit briefings or the Laravel application. Their cumulative JSON is staged for a later site-engineer import. Prepare and validate their queues with:

```sh
php claude/atlas/tools/builders.php initialize
php claude/atlas/tools/builders.php queue country 12
php claude/atlas/tools/builders.php queue ecosystem 18
php claude/atlas/tools/builders.php validate all
```

Claude Code role definitions are in `.claude/agents/`. To allow VP → Director → Manager delegation, run with two nested layers:

```sh
CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=2 claude --agent country-vp
CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=2 claude --agent ecosystem-vp
```

If the environment does not expose the `Agent` tool to directors, the VP uses the flat fallback described in its instructions.

For an official page that blocks automated access, the same helper performs one direct fetch and then tries a Jina text rendering while retaining the original URL:

```sh
php claude/atlas/tools/builders.php fetch 'https://official.example/page'
```

The fallback is a transport mechanism, not a second independent source.

`regions` is a list of `{id, name, items}`, in the desk order above. It is not a map, so `regions.items()` raises `AttributeError`. Count desks like this:

```python
{r["id"]: len(r["items"]) for r in d["regions"]}
```

`audio_script` is the section texts joined with a blank line. Join them as `"\n\n".join(...)`. Writing `chr(10)*2 .join(...)` is a syntax trap: Python treats `2 .join` as a method call on the integer 2.
