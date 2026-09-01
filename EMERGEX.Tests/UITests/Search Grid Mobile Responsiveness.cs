using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.UITests
{
    [Parallelizable(ParallelScope.Self)]
    public class SearchGridResponsiveUiTest : PageTest
    {
        [Test]
        public async Task Search_Grid_Adapts_To_Mobile_Viewport()
        {
            await Page.SetViewportSizeAsync(375, 667);
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/properties.php");

            var propertyGrid = Page.Locator(".properties-grid");
            await Expect(propertyGrid).ToBeVisibleAsync();

            // Verify grid stacks to single column on mobile
            var gridDisplay = await propertyGrid.EvaluateAsync<string>("el => window.getComputedStyle(el).gridTemplateColumns");
            Assert.IsTrue(gridDisplay.Contains("1fr") || gridDisplay.Split(' ').Length == 1);
        }
    }
}