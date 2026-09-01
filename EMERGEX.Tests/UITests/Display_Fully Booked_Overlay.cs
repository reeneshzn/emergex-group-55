using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.UITests
{
    [Parallelizable(ParallelScope.Self)]
    public class FullyBookedOverlayUiTest : PageTest
    {
        [Test]
        public async Task Fully_Booked_Overlay_Appears_On_Full_Property()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");
            var propertyCard = Page.Locator(".property-card[data-status='full']").First;

            var overlay = propertyCard.Locator(".fully-booked-overlay");
            await Expect(overlay).ToBeVisibleAsync();
        }
    }
}