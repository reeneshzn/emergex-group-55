# Functional - 62 test functions

Part of the EMERGEX test suite. Run this level on its own with:

    pytest -m functional

That command uses the markers rather than this folder, so it
returns every test at this level including any filed elsewhere.

| File | Test functions | Also covers |
| --- | ---: | --- |
| `test_listings.py` | 14 | Also integration and security. |
| `test_search.py` | 17 | Also integration (results compared against the listings table) and security (the clamped rent parameter). |
| `test_shortlist.py` | 10 | Also UI and end-to-end. |
| `test_validation.py` | 21 | The viewing-request rules. Three tests here are expected to fail: DEF-01, DEF-02, DEF-03. |

## Why the folder is not the whole picture

About a third of the tests belong to two levels at once. A test
proving a deactivated listing vanishes from search is integration
AND regression. A folder holds a file once, so each file sits
under the level it mostly covers and keeps its own markers.

The markers are the accurate record. For the full breakdown see
reports/layer-index.md, regenerated with:

    python tools/layer_index.py
