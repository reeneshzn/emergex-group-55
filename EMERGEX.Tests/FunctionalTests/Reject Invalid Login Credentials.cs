using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class RejectInvalidLoginTests : PageTest
    {
        [Test]
        public async Task TestRejectInvalidLoginCredentials()
        {
            // Access login.php directly
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/login.php");

            await Page.FillAsync("#email", "invalid@emergex.com");
            await Page.FillAsync("#password", "WrongPass");
            await Page.ClickAsync("button[type='submit']");

            var errorAlert = Page.Locator(".alert-danger, .error-message");
            await Expect(errorAlert).ToBeVisibleAsync();
        }
    }
}