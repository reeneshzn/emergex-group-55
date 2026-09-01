using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.UITests
{
    [Parallelizable(ParallelScope.Self)]
    public class VerifiedLandlordBadgeUiTest : PageTest
    {
        [Test]
        public async Task Verified_Landlord_Badge_Renders_Correctly()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/property-details.php?id=1");
            var badge = Page.Locator(".verified-landlord-badge");

            await Expect(badge).ToBeVisibleAsync();
            var badgeText = await badge.InnerTextAsync();
            StringAssert.Contains("Verified", badgeText);
        }
    }
}