using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class PriceFilterTests : PageTest
    {
        [Test]
        public async Task TestFilterPropertiesByRentPrice()
        {
            // Access properties catalog
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");

            await Page.FillAsync("#max-price", "5000");
            await Page.ClickAsync("#apply-filter-btn");

            var resultsGrid = Page.Locator(".property-grid");
            await Expect(resultsGrid).ToBeVisibleAsync();
        }
    }
}