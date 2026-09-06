using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;
using Microsoft.Playwright;

namespace EMERGEX.Tests.UITests;

/// <summary>
/// UI-layer coverage: rendering, layout, responsiveness and accessible structure.
/// These tests deliberately assert on what the user sees, not on data or workflow.
/// Maps to test cases: Verified Landlord Badge Rendering, Search Grid Mobile
/// Responsiveness, plus navigation and empty-state presentation.
/// </summary>
[TestFixture]
[Category("UI")]
public class PresentationTests : BaseTest
{
    private PropertiesPage Properties => new(Page);
    private PropertyDetailPage Detail => new(Page);

    // TC-U01 — Verified Landlord Badge Rendering
    [Test]
    public async Task PropertyDetail_ForVerifiedLandlord_RendersTheTrustBadge()
    {
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);
        await Detail.Open(propertyId);

        await Expect(Detail.VerifiedBadge).ToBeVisibleAsync();
        await Expect(Detail.VerifiedBadge).ToHaveTextAsync("Verified property");

        // The landlord card must state the verification outcome, not stay ambiguous.
        await Expect(Detail.LandlordCard).ToBeVisibleAsync();
        await Expect(Detail.LandlordCard).ToContainTextAsync("Identity verified");
        await Expect(Detail.LandlordCard).Not.ToContainTextAsync("Verification pending");
    }

    // TC-U02 — The NSFAS badge appears only on accredited listings
    [Test]
    public async Task PropertyDetail_NsfasBadge_AppearsOnlyWhenAccredited()
    {
        await Detail.Open(Database.PropertyId(TestConfig.Seed.InstantNsfasProperty));
        await Expect(Detail.NsfasBadge).ToBeVisibleAsync();

        await Detail.Open(Database.PropertyId(TestConfig.Seed.InstantNonNsfasProperty));
        await Expect(Detail.NsfasBadge).ToHaveCountAsync(0);
    }

    // TC-U03 — The booking CTA changes label with the booking mode
    [TestCase(TestConfig.Seed.InstantNsfasProperty, "Instant Book")]
    [TestCase(TestConfig.Seed.RequestNsfasProperty, "Request booking")]
    public async Task PropertyDetail_BookingCta_MatchesTheBookingMode(
        string propertyTitle, string expectedLabel)
    {
        await Detail.Open(Database.PropertyId(propertyTitle));

        await Expect(Detail.BookButton).ToBeVisibleAsync();
        await Expect(Detail.BookButton).ToHaveTextAsync(expectedLabel);
    }

    // TC-U04 — The academic-prototype disclaimer is always visible before booking
    [Test]
    public async Task PropertyDetail_ShowsTheNoRealPaymentDisclaimer()
    {
        await Detail.Open(Database.PropertyId(TestConfig.Seed.InstantNsfasProperty));

        await Expect(Detail.SafeNote).ToBeVisibleAsync();
        await Expect(Detail.SafeNote).ToContainTextAsync("no real money is collected");
    }

    // TC-U05 — Search Grid Mobile Responsiveness
    [Test]
    public async Task SearchGrid_OnMobileViewport_StacksIntoASingleColumn()
    {
        await Page.SetViewportSizeAsync(375, 812); // iPhone X class device
        await Properties.Open();

        await Expect(Properties.PropertyGrid).ToBeVisibleAsync();

        // Cards must stack: every card shares the same left edge and roughly
        // the full content width.
        var cards = await Properties.PropertyCards.AllAsync();
        Assert.That(cards, Has.Count.GreaterThan(1), "Need more than one card to test stacking.");

        var first = await cards[0].BoundingBoxAsync();
        var second = await cards[1].BoundingBoxAsync();

        Assert.Multiple(() =>
        {
            Assert.That(second!.Y, Is.GreaterThan(first!.Y + first.Height - 1),
                "Cards sit side by side on a 375px viewport instead of stacking.");
            Assert.That(first.X, Is.EqualTo(second.X).Within(2),
                "Stacked cards are not left-aligned to each other.");
            Assert.That(first.Width, Is.GreaterThan(280),
                "Cards are too narrow on mobile — the grid did not collapse.");
        });
    }

    // TC-U06 — The page must never scroll horizontally on a phone
    [TestCase("index.php")]
    [TestCase("properties.php")]
    [TestCase("login.php")]
    [TestCase("register.php")]
    public async Task Pages_OnMobileViewport_DoNotScrollHorizontally(string path)
    {
        await Page.SetViewportSizeAsync(375, 812);
        await GoTo(path);

        var overflow = await Page.EvaluateAsync<int>(
            "() => document.documentElement.scrollWidth - document.documentElement.clientWidth");

        Assert.That(overflow, Is.LessThanOrEqualTo(1),
            $"{path} overflows the viewport horizontally by {overflow}px.");
    }

    // TC-U07 — The collapsed navigation toggle is exposed to assistive technology
    [Test]
    public async Task MobileNavigation_ToggleIsAccessible()
    {
        await Page.SetViewportSizeAsync(375, 812);
        await GoTo("index.php");

        var toggle = Page.Locator(".nav-toggle");
        await Expect(toggle).ToBeVisibleAsync();
        await Expect(toggle).ToHaveAttributeAsync("aria-controls", "main-nav");
        await Expect(toggle).ToHaveAttributeAsync("aria-expanded", "false");

        await toggle.ClickAsync();
        await Expect(toggle).ToHaveAttributeAsync("aria-expanded", "true");
        await Expect(Page.Locator("#main-nav")).ToBeVisibleAsync();
    }

    // TC-U08 — The header reflects the signed-in state
    [Test]
    public async Task Header_AfterSignIn_ShowsDashboardAndSignOut()
    {
        await GoTo("index.php");
        await Expect(Page.Locator(".main-nav a:has-text(\"Sign in\")")).ToBeVisibleAsync();
        await Expect(Page.Locator(".main-nav a:has-text(\"Dashboard\")")).ToHaveCountAsync(0);

        await SignInAsStudent();

        await Expect(Page.Locator(".main-nav a:has-text(\"Dashboard\")")).ToBeVisibleAsync();
        await Expect(Page.Locator(".main-nav a:has-text(\"Sign out\")")).ToBeVisibleAsync();
        await Expect(Page.Locator(".main-nav a:has-text(\"Sign in\")")).ToHaveCountAsync(0);
    }

    // TC-U09 — An admin sees the Reports link; other roles do not
    [Test]
    public async Task Header_ReportsLink_IsAdminOnly()
    {
        await SignInAsStudent();
        await Expect(Page.Locator(".main-nav a:has-text(\"Reports\")")).ToHaveCountAsync(0);

        await SignOut();
        await SignInAsAdmin();
        await Expect(Page.Locator(".main-nav a:has-text(\"Reports\")")).ToBeVisibleAsync();
    }

    // TC-U10 — Every property image carries meaningful alternative text
    [Test]
    public async Task PropertyGrid_ImagesHaveDescriptiveAltText()
    {
        await Properties.Open();

        var images = await Page.Locator(".property-card img").AllAsync();
        Assert.That(images, Is.Not.Empty);

        foreach (var image in images)
        {
            var alt = await image.GetAttributeAsync("alt");
            Assert.That(alt, Is.Not.Null.And.Not.Empty, "A property image has no alt text.");
            Assert.That(alt, Does.StartWith("Exterior illustration of"),
                "Alt text does not describe the image content.");
        }
    }

    // TC-U11 — The document title is set per page (browser tab and SEO)
    [TestCase("index.php", "Secure student accommodation | EMERGEX")]
    [TestCase("properties.php", "Find accommodation | EMERGEX")]
    [TestCase("login.php", "Sign in | EMERGEX")]
    [TestCase("register.php", "Create account | EMERGEX")]
    public async Task Pages_HaveASpecificDocumentTitle(string path, string expectedTitle)
    {
        await GoTo(path);
        await Expect(Page).ToHaveTitleAsync(expectedTitle);
    }

    // TC-U12 — The empty state is presented as guidance, not as an error
    [Test]
    public async Task EmptyState_IsRenderedWithGuidanceCopy()
    {
        await Properties.OpenWithQuery("q=ZZZNoSuchPlace");

        await Expect(Properties.EmptyState).ToBeVisibleAsync();
        await Expect(Properties.EmptyStateHeading).ToHaveTextAsync("No exact matches");
        await Expect(Properties.EmptyState).ToContainTextAsync("Try widening");
    }

    // TC-U13 — Flash messages are announced to screen readers
    [Test]
    public async Task FlashMessage_HasStatusRoleForScreenReaders()
    {
        await SignInAsStudent();

        var flash = Flash();
        await Expect(flash).ToBeVisibleAsync();
        await Expect(flash).ToHaveAttributeAsync("role", "status");
    }

    // TC-U14 — Booking status chips render with the correct state class
    [Test]
    public async Task Dashboard_BookingStatusChip_UsesTheMatchingStateClass()
    {
        await SignInAsStudent();
        var dashboard = new StudentDashboardPage(Page);
        await dashboard.Open();

        var chip = dashboard.StatusFor(TestConfig.Seed.RequestNsfasProperty);
        await Expect(chip).ToBeVisibleAsync();

        var className = await chip.GetAttributeAsync("class");
        Assert.That(className, Does.Match(@"status-(pending|approved|deposit_held|completed|rejected)"),
            "The status chip is missing its state modifier class.");
    }

    // TC-U15 — The prototype must not leak PHP warnings or notices into the page
    [TestCase("index.php")]
    [TestCase("properties.php")]
    [TestCase("how-it-works.php")]
    [TestCase("privacy.php")]
    public async Task Pages_DoNotRenderPhpErrors(string path)
    {
        await GoTo(path);
        var body = await Page.Locator("body").InnerTextAsync();

        Assert.Multiple(() =>
        {
            Assert.That(body, Does.Not.Contain("Warning:"));
            Assert.That(body, Does.Not.Contain("Notice:"));
            Assert.That(body, Does.Not.Contain("Fatal error"));
            Assert.That(body, Does.Not.Contain("Deprecated:"));
        });
    }
}
