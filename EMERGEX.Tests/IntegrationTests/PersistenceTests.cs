using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;
using Microsoft.Playwright;

namespace EMERGEX.Tests.IntegrationTests;

/// <summary>
/// Integration coverage: drive the UI, then read MySQL directly to confirm the
/// PHP layer persisted the correct rows. These tests prove the browser, the PHP
/// application and the database agree with each other — a UI assertion alone
/// cannot do that.
///
/// Maps to test cases: DB Connection for Search Grid, Image Upload File Path
/// Storage, Escrow Status Transition, Audit Trail Recording.
///
/// These run serially because they mutate shared seed data.
/// </summary>
[TestFixture]
[Category("Integration")]
[NonParallelizable]
public class PersistenceTests : BaseTest
{
    private readonly List<string> _createdEmails = [];

    [TearDown]
    public void CleanUpCreatedAccounts()
    {
        foreach (var email in _createdEmails) Database.DeleteUserByEmail(email);
        _createdEmails.Clear();
    }

    // TC-I01 — DB Connection for Search Grid
    // The grid must be driven by the database, not by hard-coded markup.
    [Test]
    public async Task SearchGrid_RendersExactlyWhatTheDatabaseReturns()
    {
        var expectedTitles = Database
            .Query("SELECT title FROM properties WHERE status = 'published' AND verified_at IS NOT NULL")
            .Select(row => row["title"]!.ToString()!)
            .OrderBy(title => title)
            .ToList();

        Assert.That(expectedTitles, Is.Not.Empty,
            "The database has no published properties — run application/database/install.php first.");

        var properties = new PropertiesPage(Page);
        await properties.Open();

        var renderedTitles = (await properties.PropertyCards.Locator("h3").AllInnerTextsAsync())
            .Select(t => t.Trim())
            .OrderBy(t => t)
            .ToList();

        Assert.That(renderedTitles, Is.EqualTo(expectedTitles),
            "The rendered grid does not match the published properties in MySQL.");
    }

    // TC-I02 — An unpublished property must never reach the grid
    [Test]
    public async Task SearchGrid_ExcludesUnpublishedProperties()
    {
        var propertyId = Database.PropertyId(TestConfig.Seed.RequestNsfasCheapProperty);
        Database.Execute("UPDATE properties SET status = 'draft' WHERE id = @id", ("@id", propertyId));

        try
        {
            var properties = new PropertiesPage(Page);
            await properties.Open();

            await Expect(properties.CardTitled(TestConfig.Seed.RequestNsfasCheapProperty))
                .ToHaveCountAsync(0);
            await Expect(properties.PropertyCards)
                .ToHaveCountAsync(TestConfig.Seed.TotalPublished - 1);
        }
        finally
        {
            Database.Execute("UPDATE properties SET status = 'published' WHERE id = @id",
                ("@id", propertyId));
        }
    }

    // TC-I03 — Registration writes a hashed password, never plain text
    [Test]
    [Category("Security")]
    public async Task Registration_StoresAHashedPasswordAndTheCorrectRole()
    {
        var email = UniqueEmail("integration");
        _createdEmails.Add(email);
        const string password = "Emergex@2026";

        await GoTo("register.php");
        await Page.CheckAsync("input[name='role'][value='landlord']");
        await Page.FillAsync("input[name='first_name']", "Sipho");
        await Page.FillAsync("input[name='last_name']", "Ndlovu");
        await Page.FillAsync("input[name='email']", email);
        await Page.FillAsync("input[name='phone']", "0837654321");
        await Page.FillAsync("input[name='password']", password);
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");
        await Expect(Page).ToHaveURLAsync(new Regex(@"landlord\.php"));

        var user = Database
            .Query("SELECT * FROM users WHERE email = @email", ("@email", email))
            .SingleOrDefault();

        Assert.That(user, Is.Not.Null, "The account was not written to the database.");
        Assert.Multiple(() =>
        {
            Assert.That(user!["role"]?.ToString(), Is.EqualTo("landlord"));
            Assert.That(user["status"]?.ToString(), Is.EqualTo("active"));
            Assert.That(user["password_hash"]?.ToString(), Is.Not.EqualTo(password),
                "The password was stored in plain text.");
            Assert.That(user["password_hash"]?.ToString(), Does.StartWith("$2y$"),
                "The password was not hashed with bcrypt.");
            Assert.That(user["verified_at"], Is.Null,
                "A brand-new landlord must not be pre-verified.");
        });
    }

    // TC-I04 — An Instant Book booking creates a booking AND its escrow row
    [Test]
    public async Task InstantBooking_CreatesApprovedBookingWithUnpaidEscrow()
    {
        await SignInAsStudent();
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNonNsfasProperty);

        var booking = new BookingPage(Page);
        await booking.Open(propertyId);
        var moveIn = DateTime.Today.AddDays(45);
        await booking.Submit(45, "Integration test booking.");
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        var row = Database.LatestBookingFor(studentId);
        Assert.That(row, Is.Not.Null);
        var bookingId = Convert.ToInt32(row!["id"]);

        try
        {
            Assert.Multiple(() =>
            {
                Assert.That(Convert.ToInt32(row["property_id"]), Is.EqualTo(propertyId));
                Assert.That(row["status"]?.ToString(), Is.EqualTo("approved"),
                    "Instant Book properties must skip the pending state.");
                Assert.That(row["response_deadline"], Is.Null,
                    "Instant Book bookings must not carry a landlord response deadline.");
                Assert.That(Convert.ToDateTime(row["move_in_date"]).Date, Is.EqualTo(moveIn));
            });

            var escrow = Database.EscrowForBooking(bookingId);
            Assert.That(escrow, Is.Not.Null, "No escrow row was created alongside the booking.");
            Assert.Multiple(() =>
            {
                Assert.That(escrow!["status"]?.ToString(), Is.EqualTo("not_paid"));
                Assert.That(Convert.ToDecimal(escrow["amount"]),
                    Is.EqualTo(Convert.ToDecimal(row["deposit_amount"])),
                    "The escrow amount does not match the booking deposit.");
            });
        }
        finally
        {
            Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", bookingId));
            Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", bookingId));
        }
    }

    // TC-I05 — A request-flow booking starts pending with a 12-hour deadline
    [Test]
    public async Task RequestBooking_StartsPendingWithATwelveHourDeadline()
    {
        await SignInAsStudent();
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var propertyId = Database.PropertyId(TestConfig.Seed.RequestNsfasCheapProperty);

        var booking = new BookingPage(Page);
        await booking.Open(propertyId);
        await booking.Submit(60, "Integration test request.");
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        var row = Database.LatestBookingFor(studentId);
        var bookingId = Convert.ToInt32(row!["id"]);

        try
        {
            Assert.That(row["status"]?.ToString(), Is.EqualTo("pending"));
            Assert.That(row["response_deadline"], Is.Not.Null,
                "A non-instant booking must record the landlord response deadline.");

            var deadline = Convert.ToDateTime(row["response_deadline"]);
            var created = Convert.ToDateTime(row["created_at"]);
            Assert.That((deadline - created).TotalHours, Is.EqualTo(12).Within(0.05),
                "The response deadline is not 12 hours after creation.");
        }
        finally
        {
            Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", bookingId));
            Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", bookingId));
        }
    }

    // TC-I06 — Escrow state machine: not_paid -> held -> released
    [Test]
    public async Task EscrowSimulation_MovesThroughEveryStateInOrder()
    {
        await SignInAsStudent();
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);

        var booking = new BookingPage(Page);
        await booking.Open(propertyId);
        await booking.Submit(40, "Escrow state machine test.");

        var row = Database.LatestBookingFor(studentId);
        var bookingId = Convert.ToInt32(row!["id"]);

        try
        {
            Assert.That(Database.EscrowForBooking(bookingId)!["status"]?.ToString(),
                Is.EqualTo("not_paid"));

            var dashboard = new StudentDashboardPage(Page);
            await dashboard.Open();
            await dashboard.SimulateDepositButton(TestConfig.Seed.InstantNsfasProperty).First.ClickAsync();
            await Expect(Flash()).ToContainTextAsync("Deposit hold simulated");

            var afterHold = Database.EscrowForBooking(bookingId)!;
            Assert.Multiple(() =>
            {
                Assert.That(afterHold["status"]?.ToString(), Is.EqualTo("held"));
                Assert.That(afterHold["paid_at"], Is.Not.Null);
                Assert.That(afterHold["reference_code"]?.ToString(), Does.StartWith("SIM-"),
                    "The simulated payment reference is missing or wrongly formatted.");
                Assert.That(Database.Query("SELECT status FROM bookings WHERE id = @id", ("@id", bookingId))
                        .Single()["status"]?.ToString(),
                    Is.EqualTo("deposit_held"));
            });

            await dashboard.ConfirmCheckInButton(TestConfig.Seed.InstantNsfasProperty).First.ClickAsync();
            await Expect(Flash()).ToContainTextAsync("Check-in confirmed");

            var afterRelease = Database.EscrowForBooking(bookingId)!;
            var finalBooking = Database
                .Query("SELECT * FROM bookings WHERE id = @id", ("@id", bookingId)).Single();
            Assert.Multiple(() =>
            {
                Assert.That(afterRelease["status"]?.ToString(), Is.EqualTo("released"));
                Assert.That(afterRelease["released_at"], Is.Not.Null);
                Assert.That(finalBooking["status"]?.ToString(), Is.EqualTo("completed"));
                Assert.That(finalBooking["checked_in_at"], Is.Not.Null);
            });
        }
        finally
        {
            Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", bookingId));
            Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", bookingId));
        }
    }

    // TC-I07 — Image Upload File Path Storage
    // The stored name must be randomised, keep the correct extension, and the
    // original filename must be preserved separately for the audit trail.
    [Test]
    public async Task DocumentUpload_StoresARandomisedFilenameAndTheOriginalName()
    {
        await SignInAsStudent();
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var dashboard = new StudentDashboardPage(Page);
        await dashboard.Open();

        await dashboard.NsfasFileInput.SetInputFilesAsync(new FilePayload
        {
            Name = "my nsfas letter.png",
            MimeType = "image/png",
            Buffer = TestFiles.TinyPng()
        });
        await dashboard.UploadButton.ClickAsync();
        await Expect(Flash()).ToContainTextAsync("uploaded for administrator review");

        var document = Database.LatestDocumentFor(studentId)!;
        var documentId = Convert.ToInt32(document["id"]);

        try
        {
            var storedName = document["stored_name"]!.ToString()!;
            Assert.Multiple(() =>
            {
                Assert.That(document["original_name"]?.ToString(), Is.EqualTo("my nsfas letter.png"),
                    "The original filename was not preserved for the reviewer.");
                Assert.That(storedName, Is.Not.EqualTo("my nsfas letter.png"),
                    "The user-supplied filename was used on disk — a path traversal risk.");
                Assert.That(storedName, Does.Match(@"^[0-9a-f]{40}\.png$"),
                    "The stored filename is not a 40-character random hex name with the right extension.");
                Assert.That(document["mime_type"]?.ToString(), Is.EqualTo("image/png"),
                    "The MIME type was taken from the request rather than from the file contents.");
                Assert.That(document["status"]?.ToString(), Is.EqualTo("pending"));
                Assert.That(Convert.ToInt64(document["file_size"]), Is.GreaterThan(0));
            });
        }
        finally
        {
            Database.Execute("DELETE FROM user_documents WHERE id = @id", ("@id", documentId));
        }
    }

    // TC-I08 — Audit trail records privileged actions with the acting user
    [Test]
    public async Task AdminVerification_IsRecordedInTheAuditLog()
    {
        // Create an unverified landlord to act on.
        var email = UniqueEmail("landlord");
        _createdEmails.Add(email);
        await GoTo("register.php");
        await Page.CheckAsync("input[name='role'][value='landlord']");
        await Page.FillAsync("input[name='first_name']", "Unverified");
        await Page.FillAsync("input[name='last_name']", "Provider");
        await Page.FillAsync("input[name='email']", email);
        await Page.FillAsync("input[name='phone']", "0839998877");
        await Page.FillAsync("input[name='password']", "Emergex@2026");
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");
        await SignOut();

        var landlordId = Database.UserId(email);
        var before = Database.AuditCount("admin.user_verified");

        await SignInAsAdmin();
        var admin = new AdminDashboardPage(Page);
        await admin.Open();
        await admin.LandlordRow(email).Locator("button:has-text(\"Verify\")").ClickAsync();
        await Expect(Flash()).ToContainTextAsync("Landlord verified.");

        Assert.Multiple(() =>
        {
            Assert.That(Database.AuditCount("admin.user_verified"), Is.EqualTo(before + 1),
                "The verification was not written to the audit log.");
            Assert.That(Database.Query("SELECT verified_at FROM users WHERE id = @id", ("@id", landlordId))
                    .Single()["verified_at"], Is.Not.Null,
                "The landlord record was not marked as verified.");
        });

        var entry = Database.Query(
            "SELECT * FROM audit_logs WHERE action = 'admin.user_verified' ORDER BY id DESC LIMIT 1").Single();
        Assert.Multiple(() =>
        {
            Assert.That(Convert.ToInt32(entry["entity_id"]), Is.EqualTo(landlordId));
            Assert.That(entry["entity_type"]?.ToString(), Is.EqualTo("user"));
            Assert.That(Convert.ToInt32(entry["user_id"]), Is.EqualTo(Database.UserId(TestConfig.AdminEmail)),
                "The audit entry does not identify the acting administrator.");
        });
    }

    // TC-I09 — A student cannot act on another student's booking
    [Test]
    [Category("Security")]
    public async Task DepositSimulation_OnSomeoneElsesBooking_Returns404()
    {
        // The seeded booking belongs to the demo student; create a second student.
        var email = UniqueEmail("intruder");
        _createdEmails.Add(email);
        await GoTo("register.php");
        await Page.FillAsync("input[name='first_name']", "Nosy");
        await Page.FillAsync("input[name='last_name']", "Student");
        await Page.FillAsync("input[name='email']", email);
        await Page.FillAsync("input[name='phone']", "0831112222");
        await Page.FillAsync("input[name='student_number']", UniqueStudentNumber());
        await Page.FillAsync("input[name='password']", "Emergex@2026");
        await Page.CheckAsync("input[name='terms']");
        await Page.ClickAsync("button[type='submit']");

        var victimId = Database.UserId(TestConfig.StudentEmail);
        var victimBookingId = Convert.ToInt32(Database.LatestBookingFor(victimId)!["id"]);

        var token = await Page.Locator("input[name='csrf_token']").First.GetAttributeAsync("value");
        var response = await Page.APIRequest.PostAsync(TestConfig.Url("dashboard.php"),
            new APIRequestContextOptions
            {
                Form = Page.APIRequest.CreateFormData()
                    .Set("csrf_token", token!)
                    .Set("action", "simulate_deposit")
                    .Set("booking_id", victimBookingId.ToString())
            });

        Assert.That(response.Status, Is.EqualTo(404),
            "One student was able to reach another student's booking.");
        Assert.That(Database.EscrowForBooking(victimBookingId)!["status"]?.ToString(),
            Is.EqualTo("not_paid"), "The other student's escrow row was modified.");
    }
}
