using Microsoft.Playwright;

namespace EMERGEX.Tests.Support.Pages;

/// <summary>
/// Page object for properties.php — the verified accommodation search grid.
/// Selectors mirror application/public/properties.php exactly.
/// </summary>
public sealed class PropertiesPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open() => _page.GotoAsync(TestConfig.Url("properties.php"));

    public Task OpenWithQuery(string query) =>
        _page.GotoAsync(TestConfig.Url($"properties.php?{query}"));

    // --- Filter controls (aside.filters form) ---
    public ILocator SearchInput => _page.Locator("#q");
    public ILocator MaxPriceInput => _page.Locator("#max-price");
    public ILocator NsfasCheckbox => _page.Locator("input[name='nsfas']");
    public ILocator InstantCheckbox => _page.Locator("input[name='instant']");
    public ILocator ApplyFiltersButton => _page.Locator(".filters button[type='submit']");
    public ILocator ClearFiltersLink => _page.Locator(".clear-link");

    // --- Results ---
    public ILocator ResultsHeading => _page.Locator(".results-heading h2");
    public ILocator PropertyCards => _page.Locator(".property-card");
    public ILocator EmptyState => _page.Locator(".empty-state");
    public ILocator EmptyStateHeading => _page.Locator(".empty-state h3");
    public ILocator FilterSidebar => _page.Locator("aside.filters");
    public ILocator PropertyGrid => _page.Locator(".property-grid");

    public ILocator CardTitled(string title) =>
        _page.Locator($".property-card:has-text(\"{title}\")");

    /// <summary>Every NSFAS badge currently rendered in the grid.</summary>
    public ILocator NsfasBadges => _page.Locator(".property-card .badge:has-text(\"NSFAS\")");

    public ILocator InstantBookBadges => _page.Locator(".property-card .badge-accent");
    public ILocator VerifiedBadges => _page.Locator(".property-card .badge-verified");

    /// <summary>Applies the NSFAS filter through the UI and waits for the reload.</summary>
    public async Task FilterByNsfas()
    {
        await NsfasCheckbox.CheckAsync();
        await ApplyFiltersButton.ClickAsync();
        await _page.WaitForURLAsync(u => u.Contains("nsfas=1"));
    }

    public async Task FilterByMaxPrice(int rand)
    {
        await MaxPriceInput.FillAsync(rand.ToString());
        await ApplyFiltersButton.ClickAsync();
        await _page.WaitForURLAsync(u => u.Contains("max_price="));
    }

    public async Task SearchFor(string term)
    {
        await SearchInput.FillAsync(term);
        await ApplyFiltersButton.ClickAsync();
        await _page.WaitForURLAsync(u => u.Contains("q="));
    }

    /// <summary>Opens the detail page of the first result in the grid.</summary>
    public Task OpenFirstResult() => PropertyCards.First.Locator("a").ClickAsync();
}
