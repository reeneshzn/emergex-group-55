using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;

namespace EMERGEX.Tests.SecurityTests;

/// <summary>
/// Security test suite.
///
/// These are defensive tests: each one asserts that a control HOLDS. They send
/// ordinary malformed or hostile-looking input at the application's own public
/// interfaces and check the application refuses it correctly. Nothing here
/// attacks a third party, and every payload is inert.
///
/// The suite deliberately includes tests that currently FAIL. A failing security
/// test that documents a real weakness is a finding, and is more valuable than
/// omitting the case. Those are marked with an explicit defect id in the
/// assertion message.
///
/// Run with:  dotnet test --filter "Category=Security"
///
/// Note that several controls are also covered by tests living in the Functional
/// and Regression suites; those methods carry [Category("Security")] as well, so
/// this filter returns the complete security picture.
/// </summary>
[TestFixture]
[Category("Security")]
public class SecurityTests : BaseTest
{
    // ------------------------------------------------------- Injection --

    /// <summary>
    /// TC-31 — SQL injection through the search field.
    ///
    /// The classic tautology payload must be treated as an ordinary search
    /// string. If the query were concatenated rather than parameterised, this
    /// would either return every row or raise a database error.
    /// </summary>
    [TestCase("' OR '1'='1")]
    [TestCase("'; DROP TABLE users; --")]
    [TestCase("\" OR 1=1 --")]
    [TestCase("1' UNION SELECT null,null,null --")]
    public async Task Search_WithSqlInjectionPayload_IsTreatedAsPlainText(string payload)
    {
        var properties = new PropertiesPage(Page);

        var response = await GoTo($"properties.php?q={Uri.EscapeDataString(payload)}");

        Assert.That(response!.Status, Is.EqualTo(200),
            "A database error leaked through the search field.");

        var body = await Page.ContentAsync();
        Assert.Multiple(() =>
        {
            Assert.That(body, Does.Not.Contain("SQLSTATE"), "A PDO error was rendered to the page.");
            Assert.That(body, Does.Not.Contain("You have an error in your SQL syntax"));
            Assert.That(body, Does.Not.Contain("Fatal error"));
        });

        // A tautology must not act as a wildcard that returns everything.
        await Expect(properties.PropertyCards).ToHaveCountAsync(0);
        await Expect(properties.EmptyState).ToBeVisibleAsync();

        // And the table it tried to drop must still be there.
        Assert.That(Database.UserExists(TestConfig.StudentEmail), Is.True,
            "The users table did not survive the payload.");
    }

    /// <summary>
    /// TC-31 — SQL injection through the sign-in form must not authenticate.
    ///
    /// The payload is POSTed directly rather than typed into the form. The email
    /// field is `type="email" required`, so the browser refuses to submit a
    /// malformed address and the request never leaves the client — which proves
    /// nothing about the server. An attacker would use curl, so the test does the
    /// equivalent: it bypasses client-side validation and exercises the PHP.
    ///
    /// (The client-side block is still useful defence in depth. It is simply not
    /// a security control, because the attacker chooses the client.)
    /// </summary>
    [TestCase("' OR '1'='1", "' OR '1'='1")]
    [TestCase("admin@emergex.test' --", "anything")]
    [TestCase("admin@emergex.test", "' OR '1'='1")]
    public async Task SignIn_WithSqlInjectionPayload_DoesNotAuthenticate(string email, string password)
    {
        var response = await PostAction("login.php", new Dictionary<string, string>
        {
            ["email"] = email,
            ["password"] = password
        });

        var body = await response.TextAsync();

        Assert.Multiple(() =>
        {
            // A 302 here would mean the payload authenticated and was redirected
            // to a dashboard. A rejected sign-in re-renders the form as 200.
            Assert.That(response.Status, Is.EqualTo(200),
                "The injection payload was accepted and redirected to a dashboard.");

            Assert.That(body, Does.Not.Contain("SQLSTATE"), "A PDO error leaked to the client.");
            Assert.That(body, Does.Not.Contain("You have an error in your SQL syntax"));
            Assert.That(body, Does.Not.Contain("Fatal error"));
        });

        // And no session may have been issued: a protected page must still bounce.
        await GoTo("dashboard.php");
        await Expect(Page).ToHaveURLAsync(new Regex(@"login\.php"),
            new() { Timeout = 5000 });
    }

    // -------------------------------------------------- Account privacy --

    /// <summary>
    /// TC-32 — The sign-in form must not allow account enumeration.
    ///
    /// A registered email with a wrong password and an email that does not
    /// exist at all must produce the same message. Different wording tells an
    /// attacker which addresses are worth attacking.
    /// </summary>
    [Test]
    public async Task SignIn_DoesNotRevealWhetherAnAccountExists()
    {
        var known = await FailedSignInMessage(TestConfig.StudentEmail, "DefinitelyWrong@1");
        var unknown = await FailedSignInMessage("no.such.person@emergex.test", "DefinitelyWrong@1");

        Assert.That(unknown, Is.EqualTo(known),
            $"Messages differ, allowing account enumeration.\n" +
            $"  Registered address: {known}\n  Unknown address:    {unknown}");
    }

    private async Task<string> FailedSignInMessage(string email, string password)
    {
        await GoTo("login.php");
        await Page.FillAsync("#email", email);
        await Page.FillAsync("#password", password);
        await Page.ClickAsync("button[type='submit']");
        return (await FormErrors.First.InnerTextAsync()).Trim();
    }

    /// <summary>
    /// TC-32 — A rejected form must never echo the submitted password back into
    /// the HTML, where it would land in browser cache, history and any proxy log.
    /// </summary>
    [Test]
    public async Task RejectedRegistration_DoesNotEchoThePasswordIntoTheHtml()
    {
        const string password = "UniqueSentinelPassword@2026";

        await GoTo("register.php");
        await Page.FillAsync("input[name='first_name']", "Echo");
        await Page.FillAsync("input[name='last_name']", "Test");
        await Page.FillAsync("input[name='email']", TestConfig.StudentEmail); // duplicate, will fail
        await Page.FillAsync("input[name='phone']", "0820000009");
        await Page.FillAsync("input[name='student_number']", "400000999");
        await Page.FillAsync("input[name='password']", password);
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");

        var html = await Page.ContentAsync();
        Assert.That(html, Does.Not.Contain(password),
            "The submitted password was re-rendered into the page source.");
    }

    // ------------------------------------------- Broken access control --

    /// <summary>
    /// TC-33 — Uploaded identity documents must not be readable by anonymous
    /// visitors.
    ///
    /// dashboard.php stores uploads in public/uploads, which Apache serves
    /// directly. The stored name is 40 random hex characters, so it cannot be
    /// guessed — but an unguessable URL is obscurity, not access control. Anyone
    /// who obtains the link (a shared screenshot, a referrer header, a browser
    /// history sync) can read another student's ID document with no session.
    ///
    /// EXPECTED TO FAIL against the current build — this documents DEF-011.
    /// </summary>
    [Test]
    public async Task UploadedDocuments_AreNotReadableWithoutASession()
    {
        var dashboard = new StudentDashboardPage(Page);
        int documentId = 0;

        try
        {
            await SignInAsStudent();
            await dashboard.Open();
            await dashboard.NsfasFileInput.SetInputFilesAsync(new Microsoft.Playwright.FilePayload
            {
                Name = "identity-document.pdf",
                MimeType = "application/pdf",
                Buffer = TestFiles.MinimalPdf()
            });
            await dashboard.UploadButton.ClickAsync();
            await Expect(Flash()).ToContainTextAsync("uploaded for administrator review");

            var studentId = Database.UserId(TestConfig.StudentEmail);
            var document = Database.LatestDocumentFor(studentId)!;
            documentId = Convert.ToInt32(document["id"]);
            var storedName = document["stored_name"]!.ToString()!;

            // Drop the session entirely, then request the file directly.
            //
            // Fetched over HTTP rather than navigated to: a PDF makes the browser
            // start a download instead of rendering a page, which would abort the
            // navigation before any status could be read.
            await Page.Context.ClearCookiesAsync();
            var response = await Page.APIRequest.GetAsync(TestConfig.Url($"uploads/{storedName}"));

            Assert.That(response.Status, Is.Not.EqualTo(200),
                "DEF-011: a student's uploaded identity document is served to " +
                "anonymous visitors straight from public/uploads. The random " +
                "filename is obscurity, not authorisation. Documents should be " +
                "stored outside the web root and streamed by an authenticated " +
                "PHP handler that checks ownership.");
        }
        finally
        {
            if (documentId != 0)
                Database.Execute("DELETE FROM user_documents WHERE id = @id", ("@id", documentId));
        }
    }

    /// <summary>
    /// TC-33 — The uploads directory must not be browsable. Even with per-file
    /// access control, an index listing hands an attacker every filename.
    /// </summary>
    [Test]
    public async Task UploadsDirectory_DoesNotRenderAnIndexListing()
    {
        var response = await GoTo("uploads/");

        if (response!.Status == 200)
        {
            var body = await Page.ContentAsync();
            Assert.That(body, Does.Not.Contain("Index of").IgnoreCase,
                "Apache directory listing is enabled for the uploads folder, " +
                "exposing every stored document filename.");
        }
        else
        {
            Assert.That(response.Status, Is.AnyOf(403, 404));
        }
    }

    /// <summary>
    /// TC-33 — Insecure direct object reference: a signed-in student must not be
    /// able to act on a booking belonging to somebody else, and the response must
    /// not confirm that the booking exists.
    /// </summary>
    [Test]
    public async Task DepositAction_OnAnotherStudentsBooking_IsRefusedWithoutDisclosure()
    {
        var email = UniqueEmail("idor.student");
        var booking = new BookingPage(Page);

        try
        {
            // The demo student creates a booking.
            await SignInAsStudent();
            await booking.Open(Database.PropertyId(TestConfig.Seed.InstantNsfasProperty));
            await booking.Submit(40, "IDOR fixture booking.");

            var victimId = Database.UserId(TestConfig.StudentEmail);
            var victimBooking = Convert.ToInt32(Database.LatestBookingFor(victimId)!["id"]);

            try
            {
                // A different student signs in and targets that booking id.
                await SignOut();
                await RegisterStudent(email, "Emergex@2026");

                var response = await PostAction("dashboard.php", new Dictionary<string, string>
                {
                    ["action"] = "simulate_deposit",
                    ["booking_id"] = victimBooking.ToString()
                });

                Assert.That(response.Status, Is.EqualTo(404),
                    "A student was able to act on another student's booking.");

                // The escrow row must be untouched.
                var escrow = Database.EscrowForBooking(victimBooking)!;
                Assert.That(escrow["status"]?.ToString(), Is.EqualTo("not_paid"),
                    "The foreign request changed the victim's escrow state.");
            }
            finally
            {
                Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", victimBooking));
                Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", victimBooking));
            }
        }
        finally
        {
            Database.DeleteUserByEmail(email);
        }
    }

    // ------------------------------------------------- Upload hardening --

    /// <summary>
    /// TC-34 — A hostile filename must not influence where the file is written.
    ///
    /// dashboard.php derives the extension from the sniffed MIME type and the
    /// name from random bytes, so traversal sequences in the client-supplied
    /// name should be discarded entirely.
    /// </summary>
    [Test]
    public async Task Upload_WithTraversalSequenceInFilename_StoresARandomisedSafeName()
    {
        var dashboard = new StudentDashboardPage(Page);
        int documentId = 0;

        try
        {
            await SignInAsStudent();
            await dashboard.Open();
            await dashboard.NsfasFileInput.SetInputFilesAsync(new Microsoft.Playwright.FilePayload
            {
                Name = "../../../evil.pdf",
                MimeType = "application/pdf",
                Buffer = TestFiles.MinimalPdf()
            });
            await dashboard.UploadButton.ClickAsync();

            var studentId = Database.UserId(TestConfig.StudentEmail);
            var document = Database.LatestDocumentFor(studentId)!;
            documentId = Convert.ToInt32(document["id"]);

            var stored = document["stored_name"]!.ToString()!;
            var original = document["original_name"]!.ToString()!;

            Assert.Multiple(() =>
            {
                Assert.That(stored, Does.Not.Contain(".."), "Traversal survived into the stored path.");
                Assert.That(stored, Does.Not.Contain("/").And.Not.Contain("\\"));
                Assert.That(stored, Does.Match(@"^[0-9a-f]{40}\.pdf$"),
                    "The stored filename is not a randomised safe name.");
                Assert.That(original, Does.Not.Contain(".."),
                    "The retained original name still carries traversal sequences.");
            });
        }
        finally
        {
            if (documentId != 0)
                Database.Execute("DELETE FROM user_documents WHERE id = @id", ("@id", documentId));
        }
    }

    /// <summary>
    /// TC-34 — Content type must be decided by inspecting the bytes, not by
    /// trusting the extension or the browser-supplied MIME header.
    /// </summary>
    [Test]
    public async Task Upload_TrustsFileContentRatherThanTheDeclaredMimeType()
    {
        var dashboard = new StudentDashboardPage(Page);
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var before = Database.LatestDocumentFor(studentId);

        await SignInAsStudent();
        await dashboard.Open();
        await dashboard.NsfasFileInput.SetInputFilesAsync(new Microsoft.Playwright.FilePayload
        {
            Name = "proof.pdf",
            MimeType = "application/pdf",     // lies about the content
            Buffer = TestFiles.DisguisedPhpShell()
        });
        await dashboard.UploadButton.ClickAsync();

        await Expect(Flash("error")).ToContainTextAsync("Only PDF, JPG and PNG files are accepted");

        var after = Database.LatestDocumentFor(studentId);
        var beforeId = before is null ? 0 : Convert.ToInt32(before["id"]);
        var afterId = after is null ? 0 : Convert.ToInt32(after["id"]);

        Assert.That(afterId, Is.EqualTo(beforeId),
            "A file whose declared type contradicted its content was stored.");
    }

    // ------------------------------------------------ Transport & cache --

    /// <summary>
    /// TC-35 — The session identifier must never travel in a URL, where it would
    /// be captured by server logs, browser history and Referer headers.
    /// </summary>
    [Test]
    public async Task SessionIdentifier_NeverAppearsInAUrl()
    {
        var seenUrls = new List<string>();
        Page.Request += (_, request) => seenUrls.Add(request.Url);

        await SignInAsStudent();
        await GoTo("properties.php");
        await GoTo("dashboard.php");

        var offending = seenUrls
            .Where(u => u.Contains("emergex_session", StringComparison.OrdinalIgnoreCase)
                     || u.Contains("PHPSESSID", StringComparison.OrdinalIgnoreCase))
            .ToList();

        Assert.That(offending, Is.Empty,
            "The session id was placed in a URL: " + string.Join(", ", offending));
    }

    /// <summary>
    /// TC-35 — Signed-in pages must not be stored by shared caches. Without this,
    /// a back-button press on a library computer redisplays the previous
    /// student's dashboard.
    /// </summary>
    [Test]
    public async Task AuthenticatedPages_AreNotCacheable()
    {
        await SignInAsStudent();
        var response = await GoTo("dashboard.php");
        var headers = await response!.AllHeadersAsync();

        Assert.That(headers, Does.ContainKey("cache-control"),
            "dashboard.php sends no Cache-Control header, so a shared cache or " +
            "the browser back button may redisplay a previous user's dashboard.");

        var cacheControl = headers["cache-control"].ToLowerInvariant();
        Assert.That(cacheControl, Does.Contain("no-store").Or.Contain("no-cache"),
            $"Cache-Control was '{cacheControl}', which permits storage of an " +
            "authenticated page.");
    }

    // --------------------------------------------------- Error handling --

    /// <summary>
    /// TC-36 — Error pages must not disclose stack traces, file paths or SQL.
    /// An attacker learns the directory layout and framework version from these.
    /// </summary>
    [TestCase("property.php?id=999999")]
    [TestCase("property.php?id=notanumber")]
    [TestCase("book.php?property_id=-1")]
    [TestCase("nonexistent-page.php")]
    public async Task ErrorResponses_DoNotLeakInternalDetail(string path)
    {
        await GoTo(path);
        var body = await Page.ContentAsync();

        Assert.Multiple(() =>
        {
            Assert.That(body, Does.Not.Contain("C:\\xampp"), "A server file path was disclosed.");
            Assert.That(body, Does.Not.Contain("Stack trace"));
            Assert.That(body, Does.Not.Contain("SQLSTATE"));
            Assert.That(body, Does.Not.Contain("Warning:"), "A PHP warning was rendered.");
            Assert.That(body, Does.Not.Contain("Notice:"));
            Assert.That(body, Does.Not.Contain("Deprecated:"));
        });
    }
}
