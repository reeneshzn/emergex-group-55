# Requirements traceability matrix

Maps the 36 test cases in `TestCases/test_cases_v2.json` to the automated test
methods that implement them, and records the outcome of the recorded run.

**Run of record:** 6 September 2026 · 118 tests · 114 passed · 4 failed · 46.5s
Artefacts: `TestResults/*.trx` and `TestResults/emergex-test-report.html`
Database reset with `application/database/install.php` immediately beforehand.

Every one of the four failures is a documented defect, not a broken test.

**Coverage: 36 of 36 cases automated (100%).**

To find a method in Visual Studio, paste its name into the Test Explorer search
box. Ratio note: 36 cases are implemented by 118 test executions, because a case
with several expected results needs several assertions and a case listing several
inputs becomes several parameterised runs.

---

## UI layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-01 | Verified Landlord Trust Badge Rendering | `PropertyDetail_ForVerifiedLandlord_RendersTheTrustBadge` | Pass |
| TC-02 | NSFAS Accreditation Badge Visibility | `PropertyDetail_NsfasBadge_AppearsOnlyWhenAccredited` | Pass |
| TC-03 | Booking CTA Reflects Booking Mode | `PropertyDetail_BookingCta_MatchesTheBookingMode`, `PropertyDetail_ShowsTheNoRealPaymentDisclaimer` | Pass |
| TC-04 | Search Grid Mobile Responsiveness | `SearchGrid_OnMobileViewport_StacksIntoASingleColumn`, `Pages_OnMobileViewport_DoNotScrollHorizontally`, `MobileNavigation_ToggleIsAccessible` | Pass |
| TC-05 | Role-Aware Header Navigation | `Header_AfterSignIn_ShowsDashboardAndSignOut`, `Header_ReportsLink_IsAdminOnly`, `SignedInUser_IsBouncedAwayFromLoginAndRegister` | Pass |
| TC-06 | Accessible Empty State and Status Messaging | `EmptyState_IsRenderedWithGuidanceCopy`, `FlashMessage_HasStatusRoleForScreenReaders`, `PropertyGrid_ImagesHaveDescriptiveAltText`, `Dashboard_BookingStatusChip_UsesTheMatchingStateClass`, `FlashMessage_IsShownOnceThenCleared` | Pass |

## Functional layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-07 | Register New Student Account | `Register_WithValidStudentDetails_CreatesAccountAndSignsIn`, `RegistrationForm_HidesStudentNumberForLandlords` | **Fail — DEF-013** |
| TC-08 | Enforce Password Policy on Registration | `Register_WithWeakPassword_ShowsPolicyError` | Pass |
| TC-09 | Reject Duplicate Email Registration | `Register_WithExistingEmail_IsRejected` | Pass |
| TC-10 | Reject Invalid Login Credentials | `SignIn_WithWrongPassword_IsRejectedWithGenericMessage`, `SignIn_WithValidCredentials_RedirectsToRoleDashboard` | Pass |
| TC-11 | Throttle Repeated Failed Login Attempts | `SignIn_AfterFiveFailures_IsThrottled` | Pass |
| TC-12 | Reject Submission Without CSRF Token | `LoginForm_WithoutCsrfToken_DoesNotAuthenticate` (control), `LoginForm_WithoutCsrfToken_ReturnsACleanRejectionStatus` (status) | **Partial — DEF-012** |
| TC-13 | Student Logout and Session Termination | `SignOut_TerminatesSessionAndBlocksProtectedPages`, `SessionId_IsRegeneratedOnSignIn` | Pass |
| TC-14 | Block Unauthorised URL Access by Role | `Student_CannotOpenAdminOrLandlordPages`, `AnonymousUser_IsRedirectedToLogin`, `RoleRouting_KeepsRolesOnTheirOwnDashboard`, `Booking_AsLandlord_IsForbidden` | Pass |
| TC-15 | Filter Properties by NSFAS Status | `Properties_FilteredByNsfas_ShowsOnlyAccreditedHomes`, `Properties_WithNsfasAndInstantFilters_ReturnsTheIntersection`, `Properties_FilteredByInstantBook_ShowsOnlyInstantHomes`, `Properties_WithNoFilters_ListsAllVerifiedHomes` | Pass |
| TC-16 | Filter Properties by Maximum Rent | `Properties_FilteredByMaxPrice_ExcludesHomesAboveTheCap`, `Properties_AfterClearingFilters_ShowsEveryHomeAgain` | Pass |
| TC-17 | Keyword Search and Empty Result Handling | `Properties_SearchedByKeyword_ReturnsTheMatchingHome`, `Properties_WithNoMatches_ShowsHelpfulEmptyState` | Pass |
| TC-18 | Reject Invalid Booking Submission | `Booking_WithPastMoveInDate_IsRejected`, `Booking_WithOverlongMessage_IsRejected`, `Booking_WithUnknownProperty_Returns404`, `Dashboard_WithUnknownAction_ReturnsBadRequest` | Pass |
| TC-19 | Reject Malicious File Uploads | `NsfasUpload_WithDisguisedPhpFile_IsRejected`, `NsfasUpload_WithValidPdf_IsAcceptedAsPending` | Pass |

## Integration layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-20 | Search Grid Matches Database Contents | `SearchGrid_RendersExactlyWhatTheDatabaseReturns`, `SearchGrid_ExcludesUnpublishedProperties` | Pass |
| TC-21 | Registration Stores Hashed Password and Role | `Registration_StoresAHashedPasswordAndTheCorrectRole` | Pass |
| TC-22 | Instant Booking Persists Booking and Escrow | `InstantBooking_CreatesApprovedBookingWithUnpaidEscrow` | Pass |
| TC-23 | Escrow Transitions Through Every State | `EscrowSimulation_MovesThroughEveryStateInOrder` | Pass |
| TC-24 | Document Upload and Audit Log Persistence | `DocumentUpload_StoresARandomisedFilenameAndTheOriginalName`, `AdminVerification_IsRecordedInTheAuditLog` | Pass |

## End-to-end layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-25 | Instant Book and Simulated Payment Journey | `Student_CanSearchInstantBookAndCompleteTheSimulatedPayment` | Pass |
| TC-26 | Request, Landlord Decision and Deposit Journey | `StudentRequestAndLandlordApproval_CompletesTheBookingJourney`, `LandlordDecline_ClosesTheRequestWithoutAnyPaymentStep`, `RequestBooking_StartsPendingWithATwelveHourDeadline` | Pass |
| TC-27 | Landlord Verification and Document Review Journey | `NewLandlord_IsVerifiedByAdminAndTheBadgeReachesStudents`, `NsfasDocument_UploadedByStudent_IsReviewedAndReflectedBack` | Pass |

## Regression layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-28 | Security Headers and Session Cookie Hardening | `SecurityHeaders_ArePresentOnEveryResponse`, `SessionCookie_RemainsHttpOnlyAndSameSiteLax` | Pass |
| TC-29 | Output Escaping and Currency Formatting | `PropertyTitles_AreHtmlEscapedOnRender`, `Prices_RenderInRandWithThousandSeparators`, `Properties_WithScriptInSearchTerm_EscapesTheInput` | Pass |
| TC-30 | Prevent Price Tampering via Request Parameters | `Booking_WithTamperedPriceFields_PersistsTheDatabaseValues` | Pass |

Smoke tests not tied to a single case (they guard the suite as a whole):
`PublicPages_LoadSuccessfully`, `RoleDashboards_LoadForTheirOwnRole`,
`Pages_LoadEveryStaticAsset`, `Pages_LogNoConsoleErrors`,
`CoreSearchPath_StillReachesAListing`.

## Security layer

| Case | Title | Automated by | Result |
|---|---|---|---|
| TC-31 | Resist SQL Injection on Public Inputs | `Search_WithSqlInjectionPayload_IsTreatedAsPlainText` (4 payloads), `SignIn_WithSqlInjectionPayload_DoesNotAuthenticate` (3 payloads) | Pass |
| TC-32 | Prevent Account Enumeration and Credential Disclosure | `SignIn_DoesNotRevealWhetherAnAccountExists`, `RejectedRegistration_DoesNotEchoThePasswordIntoTheHtml` | Pass |
| TC-33 | Enforce Access Control on Documents and Bookings | `UploadedDocuments_AreNotReadableWithoutASession`, `UploadsDirectory_DoesNotRenderAnIndexListing`, `DepositAction_OnAnotherStudentsBooking_IsRefusedWithoutDisclosure`, `DepositSimulation_OnSomeoneElsesBooking_Returns404` | **Fail — DEF-011** |
| TC-34 | Harden File Upload Handling | `Upload_WithTraversalSequenceInFilename_StoresARandomisedSafeName`, `Upload_TrustsFileContentRatherThanTheDeclaredMimeType` | Pass |
| TC-35 | Protect Session Transport and Prevent Caching | `SessionIdentifier_NeverAppearsInAUrl`, `AuthenticatedPages_AreNotCacheable` | Pass |
| TC-36 | Suppress Internal Detail in Error Responses | `ErrorResponses_DoNotLeakInternalDetail` (4 paths), `Pages_DoNotRenderPhpErrors`, `PropertyDetail_WithUnknownId_Returns404` | Pass |

---

## Defect register

| ID | Severity | Summary | Detected by | Section |
|---|---|---|---|---|
| DEF-010 | High | `book.php` never reads or decrements `available_rooms`. A property advertising zero rooms accepts unlimited bookings. The column is `SMALLINT UNSIGNED`, so the database cannot go negative — a storage constraint, not a business rule. | `Booking_OnAPropertyWithNoRoomsAvailable_IsRefused` | 3.4 |
| DEF-011 | Critical | Uploaded identity documents are written to `public/uploads` and served directly by Apache with no authorisation check. Confirmed: an anonymous request returned HTTP 200 and the file. The 40-hex random filename is obscurity, not access control. Fix: store outside the web root and stream through an authenticated handler that checks `user_documents.user_id`. | `UploadedDocuments_AreNotReadableWithoutASession` | 4.8 |
| DEF-012 | Medium | `verify_csrf()` sets HTTP 419, a Laravel convention rather than an IANA-registered code. Apache does not recognise it and substitutes 500 Internal Server Error. The CSRF block works, but every refused submission is reported as a server crash and the "form expired" guidance never reaches the user. Fix: use 403 Forbidden. | `LoginForm_WithoutCsrfToken_ReturnsACleanRejectionStatus` | 4.8 |
| DEF-013 | Low | `app.js` correctly sets the `hidden` property on the `.student-only` label, but `app.css` declares `label { display: block }`. An author rule beats the user-agent `[hidden] { display: none }`, so the student-number field stays visible and submittable when Landlord is selected. Fix: add `[hidden] { display: none !important; }`. | `RegistrationForm_HidesStudentNumberForLandlords` | 4.7 |

## Two findings about test design

**Client-side validation gave a false pass on injection.** The sign-in email
field is `type="email" required`, so the browser refused to submit a malformed
payload and the request never left the page. Driven through the UI, the SQL
injection test looked like it passed while proving nothing about the server. The
test now POSTs directly, bypassing client validation, because the attacker
chooses the client. Client-side validation is a usability feature, not a control.

**A test written to fail is a legitimate deliverable.** Four tests assert
behaviour the build does not implement. Deleting them would raise the pass rate
to 100% and hide four real defects. They stay red until the code is fixed.
