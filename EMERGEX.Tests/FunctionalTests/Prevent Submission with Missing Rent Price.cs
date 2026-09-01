using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class PreventSubmissionWithMissingRentPriceTest : PageTest
    {
        [Test]
        public async Task Prevent_Submission_With_Missing_Rent_Price()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/landlord-create-listing.php");
            await Page.FillAsync("input[name='property_title']", "Student Apartment");
            await Page.ClickAsync("button#submit-listing");
            var validationMessage = await Page.Locator("input[name='rent_price']").EvaluateAsync<string>("el => el.validationMessage");
            Assert.IsNotEmpty(validationMessage);
        }
    }
}