using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;

namespace EMERGEX.Tests.FunctionalTests;

/// <summary>
/// Functional coverage for the verified accommodation search grid.
/// Maps to test cases: Filter Properties by NSFAS Status, Filter Properties by
/// Rent Price, Empty Search Results Handling.
/// Expected counts come from application/database/seed.sql.
/// </summary>
[TestFixture]
[Category("Functional")]
[Parallelizable(ParallelScope.Self)]
public class PropertySearchTests : BaseTest
{
    private PropertiesPage Properties => new(Page);

    // TC-F11 — Unfiltered grid shows every published, verified property
    [Test]
    public async Task Properties_WithNoFilters_ListsAllVerifiedHomes()
    {
        await Properties.Open();

        await Expect(Properties.PropertyCards).ToHaveCountAsync(TestConfig.Seed.TotalPublished);
        await Expect(Properties.ResultsHeading)
            .ToContainTextAsync($"{TestConfig.Seed.TotalPublished} homes");

        // Only verified properties are ever listed, so every card carries the badge.
        await Expect(Properties.VerifiedBadges).ToHaveCountAsync(TestConfig.Seed.TotalPublished);
    }

    // TC-F12 — Filter Properties by NSFAS Status
    [Test]
    public async Task Properties_FilteredByNsfas_ShowsOnlyAccreditedHomes()
    {
        await Properties.Open();
        await Properties.FilterByNsfas();

        await Expect(Properties.PropertyCards).ToHaveCountAsync(TestConfig.Seed.NsfasAccredited);

        // Every returned card must carry the NSFAS badge — no card without it.
        await Expect(Properties.NsfasBadges).ToHaveCountAsync(TestConfig.Seed.NsfasAccredited);

        // The non-accredited seed property must be absent.
        await Expect(Properties.CardTitled(TestConfig.Seed.InstantNonNsfasProperty))
            .ToHaveCountAsync(0);

        // The filter state must survive the reload so the user can see what is applied.
        await Expect(Properties.NsfasCheckbox).ToBeCheckedAsync();
    }

    // TC-F13 — Filter Properties by Rent Price
    [Test]
    public async Task Properties_FilteredByMaxPrice_ExcludesHomesAboveTheCap()
    {
        await Properties.Open();
        await Properties.FilterByMaxPrice(5000);

        await Expect(Properties.PropertyCards)
            .ToHaveCountAsync(TestConfig.Seed.UnderFiveThousand);

        await Expect(Properties.CardTitled(TestConfig.Seed.InstantNonNsfasProperty))
            .ToBeVisibleAsync();                                   // R4 550
        await Expect(Properties.CardTitled(TestConfig.Seed.RequestNsfasCheapProperty))
            .ToBeVisibleAsync();                                   // R4 800
        await Expect(Properties.CardTitled(TestConfig.Seed.RequestNsfasProperty))
            .ToHaveCountAsync(0);                                  // R6 100 — must be excluded
    }

    // TC-F14 — Instant Book filter
    [Test]
    public async Task Properties_FilteredByInstantBook_ShowsOnlyInstantHomes()
    {
        await Properties.OpenWithQuery("instant=1");

        await Expect(Properties.PropertyCards).ToHaveCountAsync(TestConfig.Seed.InstantBook);
        await Expect(Properties.InstantBookBadges).ToHaveCountAsync(TestConfig.Seed.InstantBook);
    }

    // TC-F15 — Combined filters narrow the result set correctly
    [Test]
    public async Task Properties_WithNsfasAndInstantFilters_ReturnsTheIntersection()
    {
        await Properties.OpenWithQuery("nsfas=1&instant=1");

        // Only Emerald House Hatfield is both NSFAS accredited and Instant Book.
        await Expect(Properties.PropertyCards).ToHaveCountAsync(1);
        await Expect(Properties.CardTitled(TestConfig.Seed.InstantNsfasProperty))
            .ToBeVisibleAsync();
        await Expect(Properties.ResultsHeading).ToContainTextAsync("1 home");
    }

    // TC-F16 — Keyword search matches city, suburb and institution
    [TestCase("Hatfield", TestConfig.Seed.InstantNsfasProperty)]
    [TestCase("Cape Town", TestConfig.Seed.RequestNsfasCheapProperty)]
    [TestCase("Wits", TestConfig.Seed.RequestNsfasProperty)]
    public async Task Properties_SearchedByKeyword_ReturnsTheMatchingHome(
        string term, string expectedTitle)
    {
        await Properties.Open();
        await Properties.SearchFor(term);

        await Expect(Properties.CardTitled(expectedTitle)).ToBeVisibleAsync();
    }

    // TC-F17 — Empty Search Results Handling
    [Test]
    public async Task Properties_WithNoMatches_ShowsHelpfulEmptyState()
    {
        await Properties.OpenWithQuery("q=Nowhereville&max_price=1200");

        await Expect(Properties.EmptyState).ToBeVisibleAsync();
        await Expect(Properties.EmptyStateHeading).ToHaveTextAsync("No exact matches");
        await Expect(Properties.PropertyCards).ToHaveCountAsync(0);
        await Expect(Properties.ResultsHeading).ToContainTextAsync("0 homes");

        // The filters must stay on screen so the user can widen the search.
        await Expect(Properties.FilterSidebar).ToBeVisibleAsync();
        await Expect(Properties.ClearFiltersLink).ToBeVisibleAsync();
    }

    // TC-F18 — Clearing filters restores the full grid
    [Test]
    public async Task Properties_AfterClearingFilters_ShowsEveryHomeAgain()
    {
        await Properties.OpenWithQuery("nsfas=1&max_price=5000");
        await Properties.ClearFiltersLink.ClickAsync();

        await Expect(Properties.PropertyCards).ToHaveCountAsync(TestConfig.Seed.TotalPublished);
        await Expect(Properties.NsfasCheckbox).Not.ToBeCheckedAsync();
    }

    // TC-F19 — Search input is sanitised (stored/reflected XSS guard)
    [Test]
    [Category("Security")]
    public async Task Properties_WithScriptInSearchTerm_EscapesTheInput()
    {
        var payload = "<script>window.__xss = true;</script>";
        await Properties.Open();
        await Properties.SearchInput.FillAsync(payload);
        await Properties.ApplyFiltersButton.ClickAsync();

        // The value is echoed back into the input, but must never execute.
        var executed = await Page.EvaluateAsync<bool>("() => window.__xss === true");
        Assert.That(executed, Is.False, "The search term was not escaped before being re-rendered.");
        await Expect(Properties.SearchInput).ToHaveValueAsync(payload);
    }

    // TC-F20 — An unknown property id returns 404 rather than a PHP error
    [Test]
    public async Task PropertyDetail_WithUnknownId_Returns404()
    {
        var response = await GoTo("property.php?id=999999");

        Assert.That(response!.Status, Is.EqualTo(404));
        await Expect(Page.Locator("body")).ToContainTextAsync("Property not found.");
    }
}
