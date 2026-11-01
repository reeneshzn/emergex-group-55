# Security - 15 test functions

Part of the EMERGEX test suite. Run this level on its own with:

    pytest -m security

That command uses the markers rather than this folder, so it
returns every test at this level including any filed elsewhere.

| File | Test functions | Also covers |
| --- | ---: | --- |
| `test_security.py` | 15 | CSRF, injection, escaping, disclosure. Two tests here are expected to fail: DEF-04, DEF-05. |

## Why the folder is not the whole picture

About a third of the tests belong to two levels at once. A test
proving a deactivated listing vanishes from search is integration
AND regression. A folder holds a file once, so each file sits
under the level it mostly covers and keeps its own markers.

The markers are the accurate record. For the full breakdown see
reports/layer-index.md, regenerated with:

    python tools/layer_index.py
