using Microsoft.Playwright;

namespace EMERGEX.Tests.Support.Pages;

/// <summary>Page object for property.php — the listing detail page.</summary>
public sealed class PropertyDetailPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open(int id) => _page.GotoAsync(TestConfig.Url($"property.php?id={id}"));

    public ILocator Title => _page.Locator(".detail-summary h1");
    public ILocator Location => _page.Locator(".detail-summary .location");
    public ILocator Price => _page.Locator(".detail-summary .price strong");
    public ILocator VerifiedBadge => _page.Locator(".badge-verified");
    public ILocator NsfasBadge => _page.Locator(".badges .badge:has-text(\"NSFAS\")");
    public ILocator AmenityList => _page.Locator(".amenity-list li");
    public ILocator DepositFact => _page.Locator(".facts div:has(dt:text-is(\"Deposit\")) dd");
    public ILocator RoomsFact => _page.Locator(".facts div:has(dt:text-is(\"Rooms available\")) dd");

    /// <summary>The single CTA. Its label is "Instant Book" or "Request booking".</summary>
    public ILocator BookButton => _page.Locator(".detail-summary a.button");

    public ILocator LandlordCard => _page.Locator(".landlord-card");
    public ILocator SafeNote => _page.Locator(".safe-note");

    public Task StartBooking() => BookButton.ClickAsync();
}

/// <summary>Page object for book.php — the booking/request form.</summary>
public sealed class BookingPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open(int propertyId) =>
        _page.GotoAsync(TestConfig.Url($"book.php?property_id={propertyId}"));

    public ILocator MoveInDate => _page.Locator("#move-in");
    public ILocator Message => _page.Locator("#message");
    public ILocator SubmitButton => _page.Locator("form button[type='submit']");
    public ILocator CsrfToken => _page.Locator("input[name='csrf_token']");
    public ILocator NoticeBox => _page.Locator(".notice-box");
    public ILocator Errors => _page.Locator(".form-error");

    /// <summary>Fills and submits the booking form with a valid future date.</summary>
    public async Task Submit(int daysFromNow = 30, string message = "Automated test booking.")
    {
        await MoveInDate.FillAsync(DateTime.Today.AddDays(daysFromNow).ToString("yyyy-MM-dd"));
        await Message.FillAsync(message);
        await SubmitButton.ClickAsync();
    }
}

/// <summary>Page object for dashboard.php — the student dashboard.</summary>
public sealed class StudentDashboardPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open() => _page.GotoAsync(TestConfig.Url("dashboard.php"));

    public ILocator Greeting => _page.Locator(".dashboard-head h1");
    public ILocator BookingCards => _page.Locator(".booking-card");
    public ILocator EmptyState => _page.Locator(".dashboard-main .empty-state");

    public ILocator BookingFor(string propertyTitle) =>
        _page.Locator($".booking-card:has-text(\"{propertyTitle}\")");

    public ILocator StatusFor(string propertyTitle) =>
        BookingFor(propertyTitle).Locator(".status");

    public ILocator SimulateDepositButton(string propertyTitle) =>
        BookingFor(propertyTitle).Locator("button:has-text(\"Simulate deposit hold\")");

    public ILocator ConfirmCheckInButton(string propertyTitle) =>
        BookingFor(propertyTitle).Locator("button:has-text(\"Confirm check-in\")");

    public ILocator CompletedReference(string propertyTitle) =>
        BookingFor(propertyTitle).Locator(".success-line");

    // NSFAS document upload panel
    public ILocator NsfasFileInput => _page.Locator("#nsfas-document");
    public ILocator UploadButton => _page.Locator("button:has-text(\"Upload securely\")");
    public ILocator DocumentRows => _page.Locator(".document-row");
}

/// <summary>Page object for landlord.php — the landlord dashboard.</summary>
public sealed class LandlordDashboardPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open() => _page.GotoAsync(TestConfig.Url("landlord.php"));

    public ILocator VerificationStatus => _page.Locator(".dashboard-head .status");
    public ILocator RequestCards => _page.Locator(".request-card");
    public ILocator OpenRequestsMetric => _page.Locator(".metric-row article:nth-child(2) strong");
    public ILocator ProtectedBookingsMetric => _page.Locator(".metric-row article:nth-child(3) strong");
    public ILocator MiniProperties => _page.Locator(".mini-property");

    public ILocator RequestFrom(string studentName) =>
        _page.Locator($".request-card:has-text(\"{studentName}\")");

    public ILocator FirstPendingApprove =>
        _page.Locator(".request-card:has(.status-pending) button:has-text(\"Approve\")").First;

    public ILocator FirstPendingDecline =>
        _page.Locator(".request-card:has(.status-pending) button:has-text(\"Decline\")").First;
}

/// <summary>Page object for admin.php — the administrator dashboard.</summary>
public sealed class AdminDashboardPage(IPage page)
{
    private readonly IPage _page = page;

    public Task Open() => _page.GotoAsync(TestConfig.Url("admin.php"));

    public ILocator UsersMetric => _page.Locator(".metric-row article:nth-child(1) strong");
    public ILocator PropertiesMetric => _page.Locator(".metric-row article:nth-child(2) strong");
    public ILocator BookingsMetric => _page.Locator(".metric-row article:nth-child(3) strong");
    public ILocator HeldFundsMetric => _page.Locator(".metric-row article:nth-child(4) strong");

    public ILocator LandlordPanel => _page.Locator(".panel:has(h2:text-is(\"Landlord verification\"))");
    public ILocator PropertyPanel => _page.Locator(".panel:has(h2:text-is(\"Property verification\"))");
    public ILocator DocumentPanel => _page.Locator(".panel:has(h2:text-is(\"Document review\"))");
    public ILocator AuditRows => _page.Locator(".audit-row");

    public ILocator LandlordRow(string email) =>
        LandlordPanel.Locator($".admin-row:has-text(\"{email}\")");

    public ILocator DocumentRow(string fileName) =>
        DocumentPanel.Locator($".admin-row:has-text(\"{fileName}\")");

    public ILocator FirstPendingDocumentApprove =>
        DocumentPanel.Locator("button:has-text(\"Approve\")").First;

    public ILocator FirstPendingDocumentReject =>
        DocumentPanel.Locator("button:has-text(\"Reject\")").First;
}
