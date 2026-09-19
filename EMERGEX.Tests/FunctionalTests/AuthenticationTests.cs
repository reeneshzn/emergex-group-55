using EMERGEX.Tests.Support;
using Microsoft.Playwright;

namespace EMERGEX.Tests.FunctionalTests;

/// <summary>
/// Functional coverage for registration, sign-in, session handling and access control.
/// Maps to test cases: Register New Student Account, Reject Invalid Login Credentials,
/// Student Logout and Session Termination, Block Unauthorized URL Access.
/// </summary>
[TestFixture]
[Category("Functional")]
[Parallelizable(ParallelScope.Self)]
public class AuthenticationTests : BaseTest
{
    // TC-F01 — Register New Student Account
    [Test]
    public async Task Register_WithValidStudentDetails_CreatesAccountAndSignsIn()
    {
        var email = UniqueEmail("student");

        await GoTo("register.php");

        await Page.CheckAsync("input[name='role'][value='student']");
        await Page.FillAsync("input[name='first_name']", "Thandi");
        await Page.FillAsync("input[name='last_name']", "Mokoena");
        await Page.FillAsync("input[name='email']", email);
        await Page.FillAsync("input[name='phone']", "0821234567");
        await Page.FillAsync("input[name='student_number']", UniqueStudentNumber());
        await Page.FillAsync("input[name='password']", "Emergex@2026");
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");

        // The app redirects straight into the student dashboard on success.
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));
        await Expect(Flash()).ToContainTextAsync("Your account has been created.");
        await Expect(Page.Locator(".dashboard-head h1")).ToContainTextAsync("Thandi");

        Database.DeleteUserByEmail(email); // keep the suite repeatable
    }

    // TC-F02 — Registration rejects a password that fails the policy
    [Test]
    public async Task Register_WithWeakPassword_ShowsPolicyError()
    {
        await GoTo("register.php");

        await Page.FillAsync("input[name='first_name']", "Weak");
        await Page.FillAsync("input[name='last_name']", "Password");
        await Page.FillAsync("input[name='email']", UniqueEmail("weak"));
        await Page.FillAsync("input[name='phone']", "0821234567");
        await Page.FillAsync("input[name='student_number']", UniqueStudentNumber());
        await Page.FillAsync("input[name='password']", "short1A");
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");

        await Expect(FormErrors.First).ToContainTextAsync("at least 10 characters");
        await Expect(Page).ToHaveURLAsync(new Regex(@"register\.php"));
    }

    // TC-F03 — Registration blocks a duplicate email address
    [Test]
    public async Task Register_WithExistingEmail_IsRejected()
    {
        await GoTo("register.php");

        await Page.FillAsync("input[name='first_name']", "Duplicate");
        await Page.FillAsync("input[name='last_name']", "Account");
        await Page.FillAsync("input[name='email']", TestConfig.StudentEmail);
        await Page.FillAsync("input[name='phone']", "0821234567");
        await Page.FillAsync("input[name='student_number']", UniqueStudentNumber());
        await Page.FillAsync("input[name='password']", "Emergex@2026");
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");

        await Expect(FormErrors.First).ToContainTextAsync("already uses that email address");
    }

    // TC-F04 — Reject Invalid Login Credentials
    [Test]
    public async Task SignIn_WithWrongPassword_IsRejectedWithGenericMessage()
    {
        await GoTo("login.php");
        await Page.FillAsync("#email", TestConfig.StudentEmail);
        await Page.FillAsync("#password", "NotTheRightPassword1");
        await Page.ClickAsync("button[type='submit']");

        await Expect(FormErrors.First).ToHaveTextAsync("The email or password is incorrect.");
        // The message must not reveal whether the email exists.
        await Expect(FormErrors.First).Not.ToContainTextAsync("password is wrong");
        await Expect(Page).ToHaveURLAsync(new Regex(@"login\.php"));
    }

    // TC-F05 — Sign-in succeeds and routes each role to the correct dashboard
    [TestCase(TestConfig.StudentEmail, TestConfig.StudentPassword, "dashboard.php")]
    [TestCase(TestConfig.LandlordEmail, TestConfig.LandlordPassword, "landlord.php")]
    [TestCase(TestConfig.AdminEmail, TestConfig.AdminPassword, "admin.php")]
    public async Task SignIn_WithValidCredentials_RedirectsToRoleDashboard(
        string email, string password, string expectedPage)
    {
        await SignIn(email, password);
        await Expect(Page).ToHaveURLAsync(new Regex(Regex.Escape(expectedPage)));
        await Expect(Flash()).ToContainTextAsync("Welcome back.");
    }

    // TC-F06 — Repeated failures trigger the throttle (HTTP 429)
    [Test]
    [Category("Security")]
    public async Task SignIn_AfterFiveFailures_IsThrottled()
    {
        for (var attempt = 1; attempt <= 5; attempt++)
        {
            await GoTo("login.php");
            await Page.FillAsync("#email", TestConfig.StudentEmail);
            await Page.FillAsync("#password", $"WrongPassword{attempt}");
            await Page.ClickAsync("button[type='submit']");
        }

        await GoTo("login.php");
        await Page.FillAsync("#email", TestConfig.StudentEmail);
        await Page.FillAsync("#password", "WrongPassword6");
        var response = await Page.RunAndWaitForResponseAsync(
            () => Page.ClickAsync("button[type='submit']"),
            r => r.Url.Contains("login.php") && r.Request.Method == "POST");

        Assert.That(response.Status, Is.EqualTo(429), "The sixth attempt should be throttled.");
        await Expect(Page.Locator("body")).ToContainTextAsync("Too many sign-in attempts");
    }

    // TC-F07 — Student Logout and Session Termination
    [Test]
    [Category("Security")]
    public async Task SignOut_TerminatesSessionAndBlocksProtectedPages()
    {
        await SignInAsStudent();
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        await SignOut();

        // The header must show the signed-out navigation again.
        await Expect(Page.Locator(".main-nav a:has-text(\"Sign in\")")).ToBeVisibleAsync();

        // Returning to the dashboard must bounce back to the login form.
        await GoTo("dashboard.php");
        await Expect(Page).ToHaveURLAsync(new Regex(@"login\.php"));
        await Expect(Flash("warning")).ToContainTextAsync("Please sign in to continue.");
    }

    // TC-F08 — Block Unauthorized URL Access (horizontal privilege escalation)
    [Test]
    [Category("Security")]
    public async Task Student_CannotOpenAdminOrLandlordPages()
    {
        await SignInAsStudent();

        var adminResponse = await GoTo("admin.php");
        Assert.That(adminResponse!.Status, Is.EqualTo(403));
        await Expect(Page.Locator("body")).ToContainTextAsync("do not have permission");

        var landlordResponse = await GoTo("landlord.php");
        Assert.That(landlordResponse!.Status, Is.EqualTo(403));
    }

    // TC-F09 — Anonymous users are redirected, not shown a raw error
    [TestCase("dashboard.php")]
    [TestCase("landlord.php")]
    [TestCase("admin.php")]
    [TestCase("reports.php")]
    public async Task AnonymousUser_IsRedirectedToLogin(string protectedPage)
    {
        await GoTo(protectedPage);
        await Expect(Page).ToHaveURLAsync(new Regex(@"login\.php"));
    }

    // TC-12 — Forms are CSRF protected.
    //
    // This is the control itself: a POST without a valid token must not sign the
    // user in, whatever status code comes back.
    [Test]
    [Category("Security")]
    [Property("TestCase", "TC-12")]
    public async Task LoginForm_WithoutCsrfToken_DoesNotAuthenticate()
    {
        var response = await SubmitLoginWithoutCsrfToken();

        Assert.That(response.Status, Is.GreaterThanOrEqualTo(400),
            "A form posted without a CSRF token was accepted.");

        // No session may have been issued.
        await GoTo("dashboard.php");
        await Expect(Page).ToHaveURLAsync(new Regex(@"login\.php"),
            new() { Timeout = 5000 });
    }

    // TC-12 / DEF-012 — the rejection must also be a clean, standard response.
    //
    // EXPECTED TO FAIL. bootstrap.php calls http_response_code(419). 419 is a
    // Laravel convention, not an IANA-registered code, so Apache does not
    // recognise the status line and substitutes 500 Internal Server Error.
    //
    // The security control is unaffected — the request is still refused — but
    // every blocked submission is reported to the user and to monitoring as a
    // server crash, and the "The form expired" guidance never reaches the page.
    [Test]
    [Category("Security")]
    [Property("Defect", "DEF-012")]
    public async Task LoginForm_WithoutCsrfToken_ReturnsACleanRejectionStatus()
    {
        var response = await SubmitLoginWithoutCsrfToken();

        Assert.That(response.Status, Is.Not.EqualTo(500),
            "DEF-012: verify_csrf() in bootstrap.php sets HTTP 419, which Apache " +
            "does not recognise and rewrites to 500 Internal Server Error. The " +
            "CSRF block itself works, but it surfaces as a server error and the " +
            "'The form expired' message is lost. Use 403 Forbidden instead.");

        await Expect(Page.Locator("body")).ToContainTextAsync("The form expired");
    }

    /// <summary>
    /// Strips the hidden CSRF field and submits the sign-in form, simulating a
    /// cross-site post. Shared by the two tests above.
    /// </summary>
    private async Task<IResponse> SubmitLoginWithoutCsrfToken()
    {
        await GoTo("login.php");
        await Page.EvaluateAsync("document.querySelector(\"input[name='csrf_token']\").remove()");
        await Page.FillAsync("#email", TestConfig.StudentEmail);
        await Page.FillAsync("#password", TestConfig.StudentPassword);

        return await Page.RunAndWaitForResponseAsync(
            () => Page.ClickAsync("button[type='submit']"),
            r => r.Request.Method == "POST");
    }
}
