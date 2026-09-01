using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.E2ETests
{
    [Parallelizable(ParallelScope.Self)]
    public class LandlordListingAndApprovalJourneyE2ETest : PageTest
    {
        [Test]
        public async Task Full_Landlord_Listing_And_Approval_Journey()
        {
            // Step 1: Landlord logs in and creates a listing
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/login.php");
            await Page.FillAsync("input[name='email']", "landlord@emergex.test");
            await Page.FillAsync("input[name='password']", "Password123!");
            await Page.ClickAsync("button[type='submit']");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/landlord-create-listing.php");
            await Page.FillAsync("input[name='property_title']", "Campus View Res");
            await Page.FillAsync("input[name='rent_price']", "3500");
            await Page.ClickAsync("button#submit-listing");

            // Step 2: Logout landlord and login as admin to approve
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/logout.php");
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/login.php");
            await Page.FillAsync("input[name='email']", "admin@emergex.test");
            await Page.FillAsync("input[name='password']", "Password123!");
            await Page.ClickAsync("button[type='submit']");

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/admin/manage-listings.php");
            await Page.ClickAsync("tr:has-text('Campus View Res') button.approve-btn");

            var statusText = await Page.Locator("tr:has-text('Campus View Res') .status-badge").InnerTextAsync();
            Assert.AreEqual("Active", statusText);
        }
    }
}