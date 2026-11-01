# UI - 28 test functions

Part of the EMERGEX test suite. Run this level on its own with:

    pytest -m ui

That command uses the markers rather than this folder, so it
returns every test at this level including any filed elsewhere.

| File | Test functions | Also covers |
| --- | ---: | --- |
| `test_behaviour.py` | 13 | Accessibility, responsive layout and fallback. Also integration and functional. |
| `test_campuses.py` | 15 | Also integration - several tests change data and require the page to follow. |

## Why the folder is not the whole picture

About a third of the tests belong to two levels at once. A test
proving a deactivated listing vanishes from search is integration
AND regression. A folder holds a file once, so each file sits
under the level it mostly covers and keeps its own markers.

The markers are the accurate record. For the full breakdown see
reports/layer-index.md, regenerated with:

    python tools/layer_index.py
