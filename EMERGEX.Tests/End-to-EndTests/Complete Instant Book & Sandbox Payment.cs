using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.E2ETests
{
    [Parallelizable(ParallelScope.Self)]
    public class InstantBookAndPaymentE2ETest : PageTest
    {
        [Test]
        public async Task Complete_Instant_Book_And_Sandbox_Payment()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/login.php");
            await Page.FillAsync("input[name='email']", "student@emergex.test");
            await Page.FillAsync("input[name='password']", "Password123!");
            await Page.ClickAsync("button[type='submit']");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/property-details.php?id=1");
            await Page.ClickAsync("button#instant-book-btn");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/checkout.php");
            await Page.ClickAsync("button#sandbox-pay-btn");

            var successHeading = await Page.Locator(".success-banner h1").InnerTextAsync();
            StringAssert.Contains("Booking Confirmed", successHeading);
        }
    }
}