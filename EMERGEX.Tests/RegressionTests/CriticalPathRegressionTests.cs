using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;

namespace EMERGEX.Tests.RegressionTests;

/// <summary>
/// Regression suite — the smoke pack that must pass before any build is
/// demonstrated or handed in. It has two parts:
///
///   1. Smoke checks: every page loads, the critical paths still work.
///   2. Defect guards: one test per previously-fixed defect, so a fix cannot
///      silently regress. Each carries its defect id from the test management
///      tool in a [Property("Defect", ...)] attribute.
///
/// Run with:  dotnet test --filter "Category=Regression"
/// </summary>
[TestFixture]
[Category("Regression")]
public class CriticalPathRegressionTests : BaseTest
{
    // ---------------------------------------------------------------- Smoke --

    // TC-R01 — Every public page returns 200 and renders its main landmark
    [TestCase("index.php")]
    [TestCase("properties.php")]
    [TestCase("how-it-works.php")]
    [TestCase("login.php")]
    [TestCase("register.php")]
    [TestCase("privacy.php")]
    public async Task PublicPages_LoadSuccessfully(string path)
    {
        var response = await GoTo(path);

        Assert.That(response!.Status, Is.EqualTo(200), $"{path} did not return 200.");
        await Expect(Page.Locator("main")).ToBeVisibleAsync();
        await Expect(Page.Locator(".site-header .brand")).ToBeVisibleAsync();
    }

    // TC-R02 — Every authenticated page loads for its own role
    [Test]
    public async Task RoleDashboards_LoadForTheirOwnRole()
    {
        await SignInAsStudent();
        await Expect(Page.Locator(".dashboard-head h1")).ToContainTextAsync("Hi,");
        await SignOut();

        await SignInAsLandlord();
        await Expect(Page.Locator(".dashboard-head h1")).ToContainTextAsync("Welcome,");
        await SignOut();

        await SignInAsAdmin();
        await Expect(Page.Locator(".dashboard-head h1")).ToHaveTextAsync("Platform assurance.");
    }

    // TC-R03 — No page ships a broken stylesheet, script or image
    [TestCase("index.php")]
    [TestCase("properties.php")]
    public async Task Pages_LoadEveryStaticAsset(string path)
    {
        var failures = new List<string>();
        Page.Response += (_, response) =>
        {
            if (response.Status >= 400) failures.Add($"{response.Status} {response.Url}");
        };

        await GoTo(path);
        await Page.WaitForLoadStateAsync(Microsoft.Playwright.LoadState.NetworkIdle);

        Assert.That(failures, Is.Empty,
            $"{path} requested assets that failed:\n  " + string.Join("\n  ", failures));
    }

    // TC-R04 — The browser console stays clean
    [TestCase("index.php")]
    [TestCase("properties.php")]
    [TestCase("register.php")]
    public async Task Pages_LogNoConsoleErrors(string path)
    {
        var errors = new List<string>();
        Page.Console += (_, message) =>
        {
            if (message.Type == "error") errors.Add(message.Text);
        };
        Page.PageError += (_, error) => errors.Add(error);

        await GoTo(path);
        await Page.WaitForLoadStateAsync(Microsoft.Playwright.LoadState.NetworkIdle);

        Assert.That(errors, Is.Empty, $"{path} logged: " + string.Join(" | ", errors));
    }

    // TC-R05 — The core search-to-listing path still works
    [Test]
    public async Task CoreSearchPath_StillReachesAListing()
    {
        var properties = new PropertiesPage(Page);
        var detail = new PropertyDetailPage(Page);

        await GoTo("index.php");
        await Page.FillAsync("#home-search", "Pretoria");
        await Page.ClickAsync(".hero-search button[type='submit']");

        await Expect(Page).ToHaveURLAsync(new Regex(@"properties\.php\?q=Pretoria"));
        await Expect(properties.PropertyCards.First).ToBeVisibleAsync();

        await properties.OpenFirstResult();
        await Expect(detail.Title).ToBeVisibleAsync();
        await Expect(detail.BookButton).ToBeVisibleAsync();
    }

    // -------------------------------------------------------- Defect guards --

    // TC-R06 — Security headers must remain on every response.
    [Test]
    [Property("Defect", "DEF-001")]
    [Category("Security")]
    public async Task SecurityHeaders_ArePresentOnEveryResponse()
    {
        var response = await GoTo("index.php");
        var headers = await response!.AllHeadersAsync();

        Assert.Multiple(() =>
        {
            Assert.That(headers, Does.ContainKey("x-frame-options"));
            Assert.That(headers["x-frame-options"], Is.EqualTo("DENY"));
            Assert.That(headers["x-content-type-options"], Is.EqualTo("nosniff"));
            Assert.That(headers, Does.ContainKey("content-security-policy"));
            Assert.That(headers["content-security-policy"], Does.Contain("frame-ancestors 'none'"));
            Assert.That(headers, Does.ContainKey("referrer-policy"));
        });
    }

    // TC-R07 — The session cookie must stay HttpOnly.
    [Test]
    [Property("Defect", "DEF-002")]
    [Category("Security")]
    public async Task SessionCookie_RemainsHttpOnlyAndSameSiteLax()
    {
        await SignInAsStudent();

        var cookies = await Page.Context.CookiesAsync();
        var session = cookies.SingleOrDefault(c => c.Name == "emergex_session");

        Assert.That(session, Is.Not.Null, "The session cookie was not issued.");
        Assert.Multiple(() =>
        {
            Assert.That(session!.HttpOnly, Is.True,
                "The session cookie is readable from JavaScript.");
            Assert.That(session.SameSite.ToString(), Is.EqualTo("Lax").IgnoreCase);
        });

        // Confirm it is genuinely invisible to page scripts.
        var visible = await Page.EvaluateAsync<string>("() => document.cookie");
        Assert.That(visible, Does.Not.Contain("emergex_session"));
    }

    // TC-R08 — Session fixation guard: the id must change on sign-in.
    [Test]
    [Property("Defect", "DEF-003")]
    [Category("Security")]
    public async Task SessionId_IsRegeneratedOnSignIn()
    {
        await GoTo("login.php");
        var before = (await Page.Context.CookiesAsync())
            .FirstOrDefault(c => c.Name == "emergex_session")?.Value;

        await Page.FillAsync("#email", TestConfig.StudentEmail);
        await Page.FillAsync("#password", TestConfig.StudentPassword);
        await Page.ClickAsync("button[type='submit']");
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        var after = (await Page.Context.CookiesAsync())
            .First(c => c.Name == "emergex_session").Value;

        Assert.That(after, Is.Not.EqualTo(before),
            "The session id survived sign-in — session fixation is possible.");
    }

    // TC-R09 — Role-based routing must not regress into a shared dashboard.
    [Test]
    [Property("Defect", "DEF-004")]
    [Category("Security")]
    public async Task RoleRouting_KeepsRolesOnTheirOwnDashboard()
    {
        await SignInAsLandlord();
        var studentPage = await GoTo("dashboard.php");
        Assert.That(studentPage!.Status, Is.EqualTo(403));

        await SignOut();
        await SignInAsAdmin();
        var landlordPage = await GoTo("landlord.php");
        Assert.That(landlordPage!.Status, Is.EqualTo(403));
    }

    // TC-R10 — Signed-in users must not be able to reach the auth forms again.
    [Test]
    [Property("Defect", "DEF-005")]
    public async Task SignedInUser_IsBouncedAwayFromLoginAndRegister()
    {
        await SignInAsStudent();

        await GoTo("login.php");
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        await GoTo("register.php");
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));
    }

    // TC-07 / DEF-013 — The student-number field must follow the selected role.
    //
    // EXPECTED TO FAIL. app.js correctly sets the `hidden` property on the
    // .student-only label when Landlord is chosen, but app.css declares
    // `label { display: block }`. An author rule beats the user-agent
    // `[hidden] { display: none }`, so the attribute is set and the field stays
    // on screen regardless.
    //
    // Consequence: a landlord is shown — and can fill in — a student number
    // field that does not apply to them, and the field is still submitted.
    //
    // Fix: add `[hidden] { display: none !important; }` to app.css.
    [Test]
    [Property("Defect", "DEF-013")]
    [Property("TestCase", "TC-07")]
    public async Task RegistrationForm_HidesStudentNumberForLandlords()
    {
        await GoTo("register.php");

        var studentOnly = Page.Locator(".student-only");
        await Expect(studentOnly).ToBeVisibleAsync();
        await Expect(Page.Locator("input[name='student_number']"))
            .ToHaveAttributeAsync("required", "");

        await Page.CheckAsync("input[name='role'][value='landlord']");

        // The script does its job — confirm that before blaming it.
        await Expect(studentOnly).ToHaveAttributeAsync("hidden", "");

        await Expect(studentOnly).ToBeHiddenAsync(new()
        {
            Timeout = 3000
        });

        await Page.CheckAsync("input[name='role'][value='student']");
        await Expect(studentOnly).ToBeVisibleAsync();
    }

    // TC-R12 — Money must always render in the South African format.
    [Test]
    [Property("Defect", "DEF-007")]
    public async Task Prices_RenderInRandWithThousandSeparators()
    {
        var properties = new PropertiesPage(Page);
        await properties.Open();

        var prices = await properties.PropertyCards.Locator(".property-meta strong").AllInnerTextsAsync();
        Assert.That(prices, Is.Not.Empty);

        foreach (var price in prices)
            Assert.That(price.Trim(), Does.Match(@"^R\s\d{1,3}(\s\d{3})*$"),
                $"'{price}' is not formatted as South African Rand.");
    }

    // TC-R13 — Listing data must never be echoed unescaped.
    [Test]
    [Property("Defect", "DEF-008")]
    [Category("Security")]
    public async Task PropertyTitles_AreHtmlEscapedOnRender()
    {
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);
        const string payload = "<img src=x onerror=\"window.__xss=1\">Escaped House";

        Database.Execute("UPDATE properties SET title = @title WHERE id = @id",
            ("@title", payload), ("@id", propertyId));

        try
        {
            await GoTo($"property.php?id={propertyId}");

            var executed = await Page.EvaluateAsync<bool>("() => window.__xss === 1");
            Assert.That(executed, Is.False, "Property data was rendered without escaping.");
            await Expect(Page.Locator(".detail-summary h1")).ToHaveTextAsync(payload);
        }
        finally
        {
            Database.Execute("UPDATE properties SET title = @title WHERE id = @id",
                ("@title", TestConfig.Seed.InstantNsfasProperty), ("@id", propertyId));
        }
    }

    // TC-R14 — Flash messages must be consumed once and not repeat.
    [Test]
    [Property("Defect", "DEF-009")]
    public async Task FlashMessage_IsShownOnceThenCleared()
    {
        await SignInAsStudent();
        await Expect(Flash()).ToContainTextAsync("Welcome back.");

        await Page.ReloadAsync();
        await Expect(Flash()).ToHaveCountAsync(0);
    }
}
