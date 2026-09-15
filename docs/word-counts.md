# Setting the over/under lines

## Where the numbers come from

Every talk of a conference is fetched from the Church's study API and counted.
Only **what was spoken** counts: the talk body, with footnotes, titles, bylines
and the editorial summary line removed.

**Admin → Word counts** does it in a browser. It downloads a few talks per
request and reloads itself until they're all on disk, so hosting that kills
long-running scripts can't interrupt it, then one button stores the counts as
results and rescores everyone.

From a shell, if that's handier:

```bash
php tools/count_words.php 2026/10           # print the counts
php tools/count_words.php 2026/10 --write   # store them as results and rescore
```

Both share `lib/harvest.php`, so they always agree.

Responses cache under `tools/cache/`, so re-runs don't re-hit the site.

## Historical counts

All three measured the same way, with the same script:

| Term | Apr 2025 | Oct 2025 | Apr 2026 | mean | median |
|---|---:|---:|---:|---:|---:|
| "Jesus Christ" | 310 | 256 | 289 | 285 | 289 |
| "temple" + "temples" | 84 | 53 | 38 | 58 | 53 |
| "covenant" + "covenants" | 117 | 85 | 87 | 96 | 87 |
| "Book of Mormon" | 33 | 57 | 16 | 35 | 33 |
| *spoken words* | *53,458* | *54,846* | *43,841* | | |

April 2026 ran short because the Solemn Assembly sustaining President Oaks took
session time.

## The three that count people, not words

They sit in the same Over/under section as the word counts, and work the same
way — but no pattern can find them, because the transcripts don't say who
prayed or where anybody was born. Enter these by hand on Results. Counted from
the session summaries and bylines in the Liahona:

| | Apr 2025 | Oct 2025 | Apr 2026 | Line |
|---|---:|---:|---:|---:|
| Women who prayed | 2 | 2 | 2 | 2.5 |
| Women who spoke | 3 | 3 | 3 | 3.5 |
| Speakers born outside the US | ? | ? | ? | 10.5 |

**The first two barely move.** Three conferences running, exactly two women
prayed and three women spoke, so whichever side of the line matches history is
close to a free three points for anyone who checks. If that bothers you, the
honest fix is not a different line — no line splits a number that never
changes — but a different question type: a dropdown of 0/1/2/3/4+ keeps it a
real guess and still needs no typing.

The third line is the least grounded number on the sheet. Birthplace isn't in
the transcripts, so it has to be counted by hand against leader biographies,
and 10.5 is an estimate from the April 2026 list rather than a measurement.

## Counting rules

Set deliberately, and baked into each question's `pattern` so the sheet and the
script can't drift apart:

| Term | Pattern | Rule |
|---|---|---|
| "Jesus Christ" | `/\bJesus\s+Christ\b/iu` | Includes "The Church of Jesus Christ of Latter-day Saints" |
| temple | `/\btemples?\b/iu` | Singular and plural both count |
| covenant | `/\bcovenants?\b/iu` | Both count, including "Doctrine and Covenants" |
| Book of Mormon | `/\bBook\s+of\s+Mormon\b/iu` | Phrase match |

## Why the lines end in ½

A line of 44.5 can't be tied, so every over/under resolves cleanly and nobody
loses three points to a technicality. The push branch is still in the scoring
engine in case someone sets a whole-number line by hand.

## Current lines

| Term | Line | Reasoning |
|---|---:|---|
| "Jesus Christ" | 287.5 | Tight range (256–310); sits between the mean and median |
| temple + temples | 44.5 | Falling hard: 84 → 53 → 38 |
| covenant + covenants | 87.5 | Last two conferences were 85 and 87 |
| "Book of Mormon" | 32.5 | Wildest term (16–57); sits on the median |

Re-run the script against the two most recent conferences before each new one
and move the lines if the trend has shifted.

## What still has to be done by hand

"How many times will President Oaks be quoted by another speaker?" needs a human
— attribution is too varied to pattern-match reliably. Enter it on the Results
page like any other answer.
