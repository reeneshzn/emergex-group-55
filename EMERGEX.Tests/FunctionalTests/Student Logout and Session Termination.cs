using Microsoft.Playwright.NUnit;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests.FunctionalTests
{
    [Parallelizable(ParallelScope.Self)]
    public class StudentLogoutAndSessionTerminationTest : PageTest
    {
        [Test]
        public async Task Student_Logout_And_Session_Termination()
        {
            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/student/dashboard.php");
            await Page.ClickAsync("a#logout-link");
            var currentUrl = Page.Url;
            StringAssert.Contains("login.php", currentUrl);

            await Page.GotoAsync("http://localhost/emergex-group-55/application/public/student/dashboard.php");
            var redirectedUrl = Page.Url;
            StringAssert.Contains("login.php", redirectedUrl);
        }
    }
}