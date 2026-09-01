using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class RegisterNewStudentAccountTests : PageTest
    {
        [Test]
        public async Task TestRegisterNewStudentAccount()
        {
            // Navigate to the flat registration file in public folder
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/register.php");

            await Page.FillAsync("#fullname", "John Doe");
            await Page.FillAsync("#email", "student@emergex.com");
            await Page.FillAsync("#password", "SecurePassword123");
            await Page.ClickAsync("button[type='submit']");

            var successMessage = Page.Locator(".alert-success, .success-message");
            await Expect(successMessage).ToBeVisibleAsync();
        }
    }
}