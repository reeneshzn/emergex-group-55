using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.UITests
{
    [Parallelizable(ParallelScope.Self)]
    public class PaymentProcessingLoadingUiTest : PageTest
    {
        [Test]
        public async Task Payment_Processing_Shows_Loading_Indicator()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/checkout.php");
            await Page.ClickAsync("button#pay-now-btn");

            var spinner = Page.Locator(".spinner-border, .loading-indicator");
            await Expect(spinner).ToBeVisibleAsync();
        }
    }
}