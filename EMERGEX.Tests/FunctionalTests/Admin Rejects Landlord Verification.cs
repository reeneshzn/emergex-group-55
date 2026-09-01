using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class AdminRejectsLandlordVerificationTest : PageTest
    {
        [Test]
        public async Task Admin_Rejects_Landlord_Verification()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/admin/pending-landlords.php");
            await Page.ClickAsync("tr[data-landlord-id='11'] button.reject-btn");
            var statusBadge = await Page.Locator("tr[data-landlord-id='11'] .status-badge").InnerTextAsync();
            Assert.AreEqual("Rejected", statusBadge);
        }
    }
}