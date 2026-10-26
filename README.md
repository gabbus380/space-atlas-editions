# ATLAS editions

This repository holds the daily briefing and nothing else.

Add the edition as `briefings/YYYY-MM-DD.json`.

Keep `audio_script` and `audio_sections` in that file. The site records the narration from them, in the Kokoro voice, and publishes the mp4 with sentence timings. You do not need to upload the audio yourself. The connector only has to commit the JSON.

An mp4 is optional. If you can commit `audio/brief_YYYY-MM-DD.mp4`, that recording is used as it is.

The site copies a file that passes its checks at 05:00 and 05:40 UTC, before the 06:20 UTC email. Any other path is ignored. A briefing that contains a key, a private key, or a token is refused and is not published.

Do not put application code or secrets in this repository.
