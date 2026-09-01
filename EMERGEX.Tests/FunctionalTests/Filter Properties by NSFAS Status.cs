using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class FilterPropertiesByNsfasTests : PageTest
    {
        [Test]
        public async Task TestFilterPropertiesByNsfasStatus()
        {
            // Open properties catalog
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");

            // Check the NSFAS accredited checkbox instead of selecting an option
            await Page.CheckAsync("input[name='nsfas']");

            // Click the submit button to apply filters
            await Page.ClickAsync("button[type='submit']");

            // Verify results grid or property cards appear
            var propertyCards = Page.Locator(".property-card, .empty-state");
            await Expect(propertyCards.First).ToBeVisibleAsync();
        }
    }
}