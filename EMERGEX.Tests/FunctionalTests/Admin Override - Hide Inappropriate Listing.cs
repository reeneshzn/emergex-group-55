using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class AdminOverrideHideInappropriateListingTest : PageTest
    {
        [Test]
        public async Task Admin_Override_Hide_Inappropriate_Listing()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/admin/manage-listings.php");
            await Page.ClickAsync("tr[data-listing-id='42'] button.hide-listing-btn");
            var visibilityStatus = await Page.Locator("tr[data-listing-id='42'] .visibility-state").InnerTextAsync();
            Assert.AreEqual("Hidden", visibilityStatus);
        }
    }
}