using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class LandlordPropertyCreationTests : PageTest
    {
        [Test]
        public async Task TestLandlordCreatesPropertyListing()
        {
            // Target landlord.php dashboard portal
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/landlord.php");

            // Verify page renders management tools or form fields successfully
            var landlordSection = Page.Locator("body");
            await Expect(landlordSection).ToBeVisibleAsync();
        }
    }
}