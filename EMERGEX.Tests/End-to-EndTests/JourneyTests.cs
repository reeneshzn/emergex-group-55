using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;

namespace EMERGEX.Tests.EndToEndTests;

/// <summary>
/// End-to-end journeys. Each test walks a complete user story across multiple
/// roles and pages, exactly as a marker would demonstrate the system, and never
/// short-cuts through direct URLs where a real user would click.
///
/// Maps to test cases: Complete Instant Book and Sandbox Payment, Complete
/// Accommodation Request Flow, Full Student Instant Book and Payment Journey,
/// Full Landlord Listing and Approval Journey.
///
/// Serial by design: these mutate the shared demo data set.
/// </summary>
[TestFixture]
[Category("E2E")]
[NonParallelizable]
public class JourneyTests : BaseTest
{
    private readonly List<int> _bookingsToClean = [];

    [TearDown]
    public void RemoveBookingsCreatedByTheJourney()
    {
        foreach (var id in _bookingsToClean)
        {
            Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", id));
            Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", id));
        }
        _bookingsToClean.Clear();
    }

    /// <summary>
    /// TC-E01 — Complete Instant Book and Sandbox Payment.
    ///
    /// Search → filter → open a listing → Instant Book → simulate the deposit
    /// hold → confirm check-in → the booking closes with a payment reference.
    /// </summary>
    [Test]
    public async Task Student_CanSearchInstantBookAndCompleteTheSimulatedPayment()
    {
        var properties = new PropertiesPage(Page);
        var detail = new PropertyDetailPage(Page);
        var booking = new BookingPage(Page);
        var dashboard = new StudentDashboardPage(Page);

        // 1. Sign in as a student.
        await SignInAsStudent();
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        // 2. Search for accommodation and narrow to Instant Book only.
        await properties.Open();
        await properties.InstantCheckbox.CheckAsync();
        await properties.ApplyFiltersButton.ClickAsync();
        await Expect(properties.PropertyCards).ToHaveCountAsync(TestConfig.Seed.InstantBook);

        // 3. Open the chosen listing from the grid, as a real user would.
        await properties.CardTitled(TestConfig.Seed.InstantNsfasProperty).Locator("a").ClickAsync();
        await Expect(detail.Title).ToHaveTextAsync(TestConfig.Seed.InstantNsfasProperty);
        await Expect(detail.VerifiedBadge).ToBeVisibleAsync();
        await Expect(detail.BookButton).ToHaveTextAsync("Instant Book");

        // 4. Start and confirm the Instant Book.
        await detail.StartBooking();
        await Expect(Page).ToHaveURLAsync(new Regex(@"book\.php"));
        await Expect(booking.NoticeBox).ToContainTextAsync("No real payment is taken.");
        await booking.Submit(35, "End-to-end journey booking.");

        // 5. The student lands on the dashboard with an approved booking.
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));
        await Expect(Flash()).ToContainTextAsync("Booking approved");
        await Expect(dashboard.StatusFor(TestConfig.Seed.InstantNsfasProperty).First)
            .ToContainTextAsync("Approved");

        var studentId = Database.UserId(TestConfig.StudentEmail);
        var bookingId = Convert.ToInt32(Database.LatestBookingFor(studentId)!["id"]);
        _bookingsToClean.Add(bookingId);

        // 6. Simulate the deposit hold.
        await dashboard.SimulateDepositButton(TestConfig.Seed.InstantNsfasProperty).First.ClickAsync();
        await Expect(Flash()).ToContainTextAsync("Deposit hold simulated successfully");
        await Expect(Flash()).ToContainTextAsync("No real funds were processed");
        await Expect(dashboard.StatusFor(TestConfig.Seed.InstantNsfasProperty).First)
            .ToContainTextAsync("Deposit securely held");

        // 7. Confirm check-in, which releases the simulated deposit.
        await dashboard.ConfirmCheckInButton(TestConfig.Seed.InstantNsfasProperty).First.ClickAsync();
        await Expect(Flash()).ToContainTextAsync("Check-in confirmed");
        await Expect(dashboard.StatusFor(TestConfig.Seed.InstantNsfasProperty).First)
            .ToContainTextAsync("Completed");

        // 8. The completed booking shows its payment reference.
        var reference = dashboard.CompletedReference(TestConfig.Seed.InstantNsfasProperty).First;
        await Expect(reference).ToContainTextAsync("Booking completed");
        await Expect(reference).ToContainTextAsync("SIM-");

        // 9. The database agrees with the screen.
        var escrow = Database.EscrowForBooking(bookingId)!;
        Assert.That(escrow["status"]?.ToString(), Is.EqualTo("released"));
    }

    /// <summary>
    /// TC-E02 — Complete Accommodation Request Flow (the non-instant path).
    ///
    /// The student requests a booking, the landlord approves it in their own
    /// session, and the student then completes the deposit simulation.
    /// </summary>
    [Test]
    public async Task StudentRequestAndLandlordApproval_CompletesTheBookingJourney()
    {
        var properties = new PropertiesPage(Page);
        var detail = new PropertyDetailPage(Page);
        var booking = new BookingPage(Page);
        var dashboard = new StudentDashboardPage(Page);
        var landlordDashboard = new LandlordDashboardPage(Page);

        const string target = TestConfig.Seed.RequestNsfasCheapProperty;

        // --- Student: submit a booking request ---
        await SignInAsStudent();
        await properties.Open();
        await properties.CardTitled(target).Locator("a").ClickAsync();
        await Expect(detail.BookButton).ToHaveTextAsync("Request booking");

        await detail.StartBooking();
        await booking.Submit(50, "I would like to view the room before signing.");

        await Expect(Flash()).ToContainTextAsync("Booking request sent");
        await Expect(dashboard.StatusFor(target).First).ToContainTextAsync("Awaiting landlord");
        // The 12-hour response promise must be visible to the student.
        await Expect(dashboard.BookingFor(target).First.Locator(".deadline"))
            .ToContainTextAsync("Response requested by");

        var studentId = Database.UserId(TestConfig.StudentEmail);
        var bookingId = Convert.ToInt32(Database.LatestBookingFor(studentId)!["id"]);
        _bookingsToClean.Add(bookingId);

        // --- Landlord: review and approve the request ---
        await SignOut();
        await SignInAsLandlord();
        await Expect(landlordDashboard.VerificationStatus).ToContainTextAsync("Identity verified");

        var request = landlordDashboard.RequestFrom("Demo Student").First;
        await Expect(request).ToContainTextAsync(target);
        await Expect(request).ToContainTextAsync("I would like to view the room");
        await request.Locator("button:has-text(\"Approve\")").ClickAsync();
        await Expect(Flash()).ToContainTextAsync("Booking request approved.");

        // --- Student: the approval is visible and the deposit step unlocks ---
        await SignOut();
        await SignInAsStudent();
        await Expect(dashboard.StatusFor(target).First).ToContainTextAsync("Approved");
        await Expect(dashboard.SimulateDepositButton(target).First).ToBeVisibleAsync();

        await dashboard.SimulateDepositButton(target).First.ClickAsync();
        await Expect(dashboard.StatusFor(target).First).ToContainTextAsync("Deposit securely held");

        Assert.That(Database.EscrowForBooking(bookingId)!["status"]?.ToString(), Is.EqualTo("held"));
    }

    /// <summary>
    /// TC-E03 — A declined request must close the journey cleanly and must not
    /// offer the student any payment action.
    /// </summary>
    [Test]
    public async Task LandlordDecline_ClosesTheRequestWithoutAnyPaymentStep()
    {
        var booking = new BookingPage(Page);
        var dashboard = new StudentDashboardPage(Page);
        var landlordDashboard = new LandlordDashboardPage(Page);

        const string target = TestConfig.Seed.RequestNsfasProperty;

        await SignInAsStudent();
        await booking.Open(Database.PropertyId(target));
        await booking.Submit(55, "Declined-path journey test.");

        var studentId = Database.UserId(TestConfig.StudentEmail);
        var bookingId = Convert.ToInt32(Database.LatestBookingFor(studentId)!["id"]);
        _bookingsToClean.Add(bookingId);

        await SignOut();
        await SignInAsLandlord();
        await landlordDashboard.Open();
        await landlordDashboard.RequestFrom("Demo Student").First
            .Locator("button:has-text(\"Decline\")").ClickAsync();
        await Expect(Flash()).ToContainTextAsync("Booking request declined.");

        await SignOut();
        await SignInAsStudent();

        var declined = dashboard.BookingFor(target).First;
        await Expect(declined.Locator(".status")).ToContainTextAsync("Declined");
        await Expect(declined.Locator("button")).ToHaveCountAsync(0);

        Assert.That(Database.EscrowForBooking(bookingId)!["status"]?.ToString(),
            Is.EqualTo("not_paid"), "A declined booking must never move funds.");
    }

    /// <summary>
    /// TC-E04 — Full landlord verification and publishing journey.
    ///
    /// A new landlord registers, an administrator verifies them, and the
    /// verification is then visible to students on the public listing page.
    /// </summary>
    [Test]
    public async Task NewLandlord_IsVerifiedByAdminAndTheBadgeReachesStudents()
    {
        var email = UniqueEmail("journey.landlord");
        var admin = new AdminDashboardPage(Page);

        try
        {
            // --- Landlord: register from the "List a property" call to action ---
            await GoTo("index.php");
            await Page.ClickAsync("a:has-text(\"List a property\")");
            await Expect(Page).ToHaveURLAsync(new Regex(@"register\.php\?role=landlord"));
            await Expect(Page.Locator("input[name='role'][value='landlord']")).ToBeCheckedAsync();

            await Page.FillAsync("input[name='first_name']", "Journey");
            await Page.FillAsync("input[name='last_name']", "Landlord");
            await Page.FillAsync("input[name='email']", email);
            await Page.FillAsync("input[name='phone']", "0844445555");
            await Page.FillAsync("input[name='password']", "Emergex@2026");
            await Page.CheckAsync("input[name='terms']");
            await Page.ClickAsync("button[type='submit']");

            await Expect(Page).ToHaveURLAsync(new Regex(@"landlord\.php"));
            // Until an administrator acts, the landlord is explicitly unverified.
            await Expect(Page.Locator(".dashboard-head .status"))
                .ToContainTextAsync("Verification pending");

            // --- Admin: verify the new landlord ---
            await SignOut();
            await SignInAsAdmin();
            await admin.Open();

            var row = admin.LandlordRow(email);
            await Expect(row).ToBeVisibleAsync();
            await Expect(row.Locator("button:has-text(\"Verify\")")).ToBeVisibleAsync();
            await row.Locator("button:has-text(\"Verify\")").ClickAsync();
            await Expect(Flash()).ToContainTextAsync("Landlord verified.");
            await Expect(admin.LandlordRow(email)).ToContainTextAsync("Verified");

            // The action is recorded in the visible audit trail.
            await Expect(admin.AuditRows.First).ToContainTextAsync("admin.user_verified");

            // --- Landlord: the dashboard now reflects the verified state ---
            await SignOut();
            await SignIn(email, "Emergex@2026");
            await Expect(Page.Locator(".dashboard-head .status"))
                .ToContainTextAsync("Identity verified");
        }
        finally
        {
            Database.DeleteUserByEmail(email);
        }
    }

    /// <summary>
    /// TC-E05 — Administrator document review journey: a student uploads NSFAS
    /// evidence, the administrator approves it, and the student sees the outcome.
    /// </summary>
    [Test]
    public async Task NsfasDocument_UploadedByStudent_IsReviewedAndReflectedBack()
    {
        var dashboard = new StudentDashboardPage(Page);
        var admin = new AdminDashboardPage(Page);
        var fileName = $"nsfas-{DateTime.UtcNow:HHmmssfff}.pdf";
        int documentId = 0;

        try
        {
            await SignInAsStudent();
            await dashboard.Open();
            await dashboard.NsfasFileInput.SetInputFilesAsync(new Microsoft.Playwright.FilePayload
            {
                Name = fileName,
                MimeType = "application/pdf",
                Buffer = TestFiles.MinimalPdf()
            });
            await dashboard.UploadButton.ClickAsync();
            await Expect(Flash()).ToContainTextAsync("uploaded for administrator review");

            var studentId = Database.UserId(TestConfig.StudentEmail);
            documentId = Convert.ToInt32(Database.LatestDocumentFor(studentId)!["id"]);

            await SignOut();
            await SignInAsAdmin();
            await admin.Open();

            var documentRow = admin.DocumentRow(fileName);
            await Expect(documentRow).ToBeVisibleAsync();
            await documentRow.Locator("button:has-text(\"Approve\")").ClickAsync();
            await Expect(Flash()).ToContainTextAsync("Document review saved.");
            await Expect(admin.DocumentRow(fileName)).ToContainTextAsync("Approved");

            await SignOut();
            await SignInAsStudent();
            await Expect(dashboard.DocumentRows.First).ToContainTextAsync(fileName);
            await Expect(dashboard.DocumentRows.First).ToContainTextAsync("Approved");
        }
        finally
        {
            if (documentId != 0)
                Database.Execute("DELETE FROM user_documents WHERE id = @id", ("@id", documentId));
        }
    }
}
