using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.E2ETests
{
    [Parallelizable(ParallelScope.Self)]
    public class AccommodationRequestFlowE2ETest : PageTest
    {
        [Test]
        public async Task Complete_Accommodation_Request_Flow()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/login.php");
            await Page.FillAsync("input[name='email']", "student@emergex.test");
            await Page.FillAsync("input[name='password']", "Password123!");
            await Page.ClickAsync("button[type='submit']");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");
            await Page.ClickAsync(".property-card:first-child a.view-details-btn");

            await Page.FillAsync("textarea[name='request_message']", "I would like to request accommodation starting next month.");
            await Page.ClickAsync("button#submit-request-btn");

            var confirmationMsg = await Page.Locator(".alert-success").InnerTextAsync();
            StringAssert.Contains("Request submitted successfully", confirmationMsg);
        }
    }
}