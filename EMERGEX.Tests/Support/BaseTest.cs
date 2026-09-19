using Microsoft.Playwright;
using Microsoft.Playwright.NUnit;

namespace EMERGEX.Tests.Support;

/// <summary>
/// Base class for every EMERGEX test. Gives each test a fresh browser context
/// (so no session leaks between tests) plus shared sign-in and assertion helpers.
/// </summary>
public abstract class BaseTest : PageTest
{
    /// <summary>Navigates to a page relative to the application root.</summary>
    protected Task<IResponse?> GoTo(string path) =>
        Page.GotoAsync(TestConfig.Url(path));

    /// <summary>
    /// Signs in through the real login form. The app issues a CSRF token on
    /// page load, so we must load login.php rather than POST directly.
    /// </summary>
    protected async Task SignIn(string email, string password)
    {
        await GoTo("login.php");
        await Page.FillAsync("#email", email);
        await Page.FillAsync("#password", password);
        await Page.ClickAsync("button[type='submit']");
        // The app redirects to a role dashboard on success.
        await Page.WaitForURLAsync(u => !u.Contains("login.php"));
    }

    protected Task SignInAsStudent() =>
        SignIn(TestConfig.StudentEmail, TestConfig.StudentPassword);

    protected Task SignInAsLandlord() =>
        SignIn(TestConfig.LandlordEmail, TestConfig.LandlordPassword);

    protected Task SignInAsAdmin() =>
        SignIn(TestConfig.AdminEmail, TestConfig.AdminPassword);

    protected Task SignOut() => GoTo("logout.php");

    /// <summary>Locator for the flash banner rendered by header.php.</summary>
    protected ILocator Flash(string type = "success") =>
        Page.Locator($".flash.flash-{type}");

    /// <summary>Locator for inline validation errors rendered by the forms.</summary>
    protected ILocator FormErrors => Page.Locator(".form-error");

    /// <summary>Unique email so registration tests can run repeatedly.</summary>
    protected static string UniqueEmail(string prefix = "auto") =>
        $"{prefix}.{DateTime.UtcNow:yyyyMMddHHmmssfff}@emergex.test";

    /// <summary>Unique student number for the same reason.</summary>
    protected static string UniqueStudentNumber() =>
        $"4{DateTime.UtcNow:HHmmssfff}";

    /// <summary>
    /// Fetches a URL without following the browser's rendering pipeline, so the
    /// raw HTTP status code can be asserted (used for 403 / 419 / 429 checks).
    /// </summary>
    protected async Task<int> StatusOf(string path)
    {
        var response = await Page.APIRequest.GetAsync(TestConfig.Url(path));
        return response.Status;
    }

    /// <summary>
    /// Registers a brand-new student through the real form and leaves the
    /// browser signed in as that student. Used where a test needs a second,
    /// isolated account rather than the shared demo one.
    /// </summary>
    protected async Task RegisterStudent(string email, string password)
    {
        await GoTo("register.php");
        await Page.CheckAsync("input[name='role'][value='student']");
        await Page.FillAsync("input[name='first_name']", "Auto");
        await Page.FillAsync("input[name='last_name']", "Student");
        await Page.FillAsync("input[name='email']", email);
        await Page.FillAsync("input[name='phone']", "0820001111");
        await Page.FillAsync("input[name='student_number']", UniqueStudentNumber());
        await Page.FillAsync("input[name='password']", password);
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");
        await Page.WaitForURLAsync(u => !u.Contains("register.php"));
    }

    /// <summary>
    /// Submits a POST to a page using the current session, carrying a CSRF token
    /// freshly read from that page. Returns the raw response so its status code
    /// can be asserted.
    ///
    /// This exists so security tests can exercise a handler directly — sending a
    /// booking id the UI would never offer, for example — without the browser
    /// re-rendering and hiding the status code.
    /// </summary>
    protected async Task<IAPIResponse> PostAction(string path, Dictionary<string, string> fields)
    {
        await GoTo(path);
        var token = await Page.GetAttributeAsync("input[name='csrf_token']", "value")
                    ?? string.Empty;

        var form = new Dictionary<string, string>(fields) { ["csrf_token"] = token };
        var body = string.Join("&", form.Select(pair =>
            $"{Uri.EscapeDataString(pair.Key)}={Uri.EscapeDataString(pair.Value)}"));

        return await Page.APIRequest.PostAsync(TestConfig.Url(path), new APIRequestContextOptions
        {
            Headers = new Dictionary<string, string>
            {
                ["Content-Type"] = "application/x-www-form-urlencoded"
            },
            DataString = body,
            MaxRedirects = 0
        });
    }
}
