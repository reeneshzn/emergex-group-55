using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class EmptySearchResultsHandlingTest : PageTest
    {
        [Test]
        public async Task Empty_Search_Results_Handling()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");
            await Page.FillAsync("input[name='location']", "NonExistentLocationXYZ");
            await Page.ClickAsync("button#apply-filters");
            var noResultsText = await Page.Locator(".no-results-message").InnerTextAsync();
            StringAssert.Contains("0 homes", noResultsText);
        }
    }
}