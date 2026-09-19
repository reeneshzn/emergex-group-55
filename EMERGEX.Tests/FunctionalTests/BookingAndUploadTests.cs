using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;
using Microsoft.Playwright;

namespace EMERGEX.Tests.FunctionalTests;

/// <summary>
/// Functional coverage for the booking form and the NSFAS document upload.
/// Maps to test cases: Prevent Submission with Missing Rent Price (adapted — the
/// prototype has no landlord listing form, so the equivalent server-side
/// validation is the booking date), Reject Malicious File Uploads.
/// </summary>
[TestFixture]
[Category("Functional")]
public class BookingAndUploadTests : BaseTest
{
    private BookingPage Booking => new(Page);
    private StudentDashboardPage Dashboard => new(Page);

    // TC-F21 — The booking form rejects a past move-in date (server-side validation)
    [Test]
    public async Task Booking_WithPastMoveInDate_IsRejected()
    {
        await SignInAsStudent();
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);
        await Booking.Open(propertyId);

        // The date input carries a min attribute, so bypass it the way a
        // determined user would and confirm PHP still refuses the value.
        await Page.EvaluateAsync("document.querySelector('#move-in').removeAttribute('min')");
        await Booking.MoveInDate.FillAsync(DateTime.Today.AddDays(-10).ToString("yyyy-MM-dd"));
        await Booking.SubmitButton.ClickAsync();

        await Expect(Booking.Errors.First).ToContainTextAsync("valid future move-in date");
        await Expect(Page).ToHaveURLAsync(new Regex(@"book\.php"));
    }

    // TC-F22 — The booking form rejects an over-long message
    [Test]
    public async Task Booking_WithOverlongMessage_IsRejected()
    {
        await SignInAsStudent();
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);
        await Booking.Open(propertyId);

        await Booking.MoveInDate.FillAsync(DateTime.Today.AddDays(30).ToString("yyyy-MM-dd"));
        // maxlength stops typing, so set the value directly to test the server rule.
        await Page.EvaluateAsync("document.querySelector('#message').value = 'x'.repeat(1001)");
        await Booking.SubmitButton.ClickAsync();

        await Expect(Booking.Errors.First).ToContainTextAsync("under 1,000 characters");
    }

    // TC-F23 — A landlord cannot open the student booking form
    [Test]
    public async Task Booking_AsLandlord_IsForbidden()
    {
        await SignInAsLandlord();
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);

        var response = await GoTo($"book.php?property_id={propertyId}");

        Assert.That(response!.Status, Is.EqualTo(403));
    }

    // TC-F24 — Booking an unknown property returns 404
    [Test]
    public async Task Booking_WithUnknownProperty_Returns404()
    {
        await SignInAsStudent();

        var response = await GoTo("book.php?property_id=999999");

        Assert.That(response!.Status, Is.EqualTo(404));
    }

    // TC-F25 — Reject Malicious File Uploads
    [Test]
    [Category("Security")]
    public async Task NsfasUpload_WithDisguisedPhpFile_IsRejected()
    {
        await SignInAsStudent();
        await Dashboard.Open();

        // A PHP web shell renamed to .pdf. The app checks the real MIME type with
        // finfo, so the extension alone must not be enough to get it accepted.
        await Dashboard.NsfasFileInput.SetInputFilesAsync(new FilePayload
        {
            Name = "proof.pdf",
            MimeType = "application/pdf",
            Buffer = TestFiles.DisguisedPhpShell()
        });
        await Dashboard.UploadButton.ClickAsync();

        await Expect(Flash("error"))
            .ToContainTextAsync("Only PDF, JPG and PNG files are accepted.");

        // Nothing should have been recorded against the account.
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var latest = Database.LatestDocumentFor(studentId);
        if (latest is not null)
            Assert.That(latest["original_name"]?.ToString(), Is.Not.EqualTo("proof.pdf"),
                "A disguised PHP file was stored as an NSFAS document.");
    }

    // TC-F26 — A genuine PDF is accepted and queued for review
    [Test]
    public async Task NsfasUpload_WithValidPdf_IsAcceptedAsPending()
    {
        await SignInAsStudent();
        await Dashboard.Open();

        await Dashboard.NsfasFileInput.SetInputFilesAsync(new FilePayload
        {
            Name = "nsfas-proof.pdf",
            MimeType = "application/pdf",
            Buffer = TestFiles.MinimalPdf()
        });
        await Dashboard.UploadButton.ClickAsync();

        await Expect(Flash()).ToContainTextAsync("uploaded for administrator review");
        await Expect(Dashboard.DocumentRows.First).ToContainTextAsync("Pending");

        // Clean up so the admin document queue stays predictable for other tests.
        var studentId = Database.UserId(TestConfig.StudentEmail);
        var document = Database.LatestDocumentFor(studentId);
        if (document is not null)
            Database.Execute("DELETE FROM user_documents WHERE id = @id",
                ("@id", Convert.ToInt32(document["id"])));
    }

    // TC-F27 — An unknown dashboard action is rejected rather than silently ignored
    [Test]
    public async Task Dashboard_WithUnknownAction_ReturnsBadRequest()
    {
        await SignInAsStudent();
        await Dashboard.Open();

        var token = await Page.Locator("input[name='csrf_token']").First.GetAttributeAsync("value");
        var response = await Page.APIRequest.PostAsync(TestConfig.Url("dashboard.php"),
            new APIRequestContextOptions
            {
                Form = Page.APIRequest.CreateFormData()
                    .Set("csrf_token", token!)
                    .Set("action", "delete_everything")
            });

        Assert.That(response.Status, Is.EqualTo(400));
    }
}
