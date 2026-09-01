using NUnit.Framework;
using Microsoft.Playwright.NUnit;
using System.Threading.Tasks;

namespace EMERGENX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class SecurityAccessTests : PageTest
    {
        [Test]
        public async Task TestBlockUnauthorizedUrlAccess()
        {
            // Attempt direct access to admin.php without an authenticated session
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/admin.php");

            // Verify route security forces a redirect back to login.php
            string currentUrl = Page.Url;
            Assert.That(currentUrl, Does.Contain("login.php"));
        }
    }
}