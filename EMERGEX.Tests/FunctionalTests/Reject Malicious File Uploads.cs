using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class RejectMaliciousFileUploadsTest : PageTest
    {
        [Test]
        public async Task Reject_Malicious_File_Uploads()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/landlord-upload.php");
            await Page.SetInputFilesAsync("input[name='property_document']", "malicious_script.php");
            await Page.ClickAsync("button[type='submit']");
            var errorMessage = await Page.Locator(".error-banner").InnerTextAsync();
            StringAssert.Contains("Invalid file type", errorMessage);
        }
    }
}