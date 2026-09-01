using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.E2ETests
{
    [Parallelizable(ParallelScope.Self)]
    public class StudentInstantBookAndPaymentJourneyE2ETest : PageTest
    {
        [Test]
        public async Task Full_Student_Instant_Book_And_Payment_Journey()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/register.php");
            await Page.FillAsync("input[name='full_name']", "Test Student");
            await Page.FillAsync("input[name='email']", "newstudent@emergex.test");
            await Page.FillAsync("input[name='password']", "SecurePass123!");
            await Page.ClickAsync("button#register-btn");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");
            await Page.ClickAsync(".property-card:first-child a.view-details-btn");
            await Page.ClickAsync("button#instant-book-btn");

            await Page.FillAsync("input[name='card_number']", "4242424242424242");
            await Page.ClickAsync("button#confirm-payment-btn");

            var receiptHeading = await Page.Locator(".receipt-container h2").InnerTextAsync();
            StringAssert.Contains("Payment Successful", receiptHeading);
        }
    }
}