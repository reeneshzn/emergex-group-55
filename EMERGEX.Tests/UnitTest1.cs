using Microsoft.Playwright;
using NUnit.Framework;
using NUnit.Framework.Legacy;
using System.Threading.Tasks;

namespace EMERGEX.Tests;

public class Tests
{
    private IPlaywright _playwright;
    private IBrowser _browser;
    private IPage _page;

    [SetUp]
    public async Task Setup()
    {
        _playwright = await Playwright.CreateAsync();
        // Set Headless = false if you want to watch the browser run live
        _browser = await _playwright.Chromium.LaunchAsync(new BrowserTypeLaunchOptions { Headless = false });
        _page = await _browser.NewPageAsync();
    }

    [Test]
    public async Task TestEmergexHomepageLoads()
    {
        // Replace with your local project URL when ready
        await _page.GotoAsync("http://localhost/emergex-group-55/application/public/");

        // Assert that the title contains EMERGEX
        string title = await _page.TitleAsync();
        StringAssert.Contains("EMERGEX", title);
    }

    [TearDown]
    public async Task TearDown()
    {
        await _browser.CloseAsync();
        _playwright.Dispose();
    }
}