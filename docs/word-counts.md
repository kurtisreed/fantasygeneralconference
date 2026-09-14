# Setting the over/under lines

## Where the numbers come from

`tools/count_words.php` fetches every talk of a conference from the Church's
study API and counts the words. It counts **only what was spoken**: the talk
body, with footnotes, titles, bylines and the editorial summary line removed.

```bash
php tools/count_words.php 2026/10           # print the counts
php tools/count_words.php 2026/10 --write   # store them as results and rescore
```

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
