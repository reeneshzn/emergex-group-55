using EMERGEX.Tests.Support;
using EMERGEX.Tests.Support.Pages;

namespace EMERGEX.Tests.RegressionTests;

/// <summary>
/// Data integrity guards for money and inventory — the two things a booking
/// platform cannot afford to get wrong.
///
/// These tests are kept apart from the smoke pack because they mutate seeded
/// rows and must not run alongside tests that read them.
/// </summary>
[TestFixture]
[Category("Regression")]
[NonParallelizable]
public class DataIntegrityTests : BaseTest
{
    private readonly List<int> _bookingsToClean = [];

    [TearDown]
    public void RemoveBookingsCreatedByTheseTests()
    {
        foreach (var id in _bookingsToClean)
        {
            Database.Execute("DELETE FROM escrow_transactions WHERE booking_id = @id", ("@id", id));
            Database.Execute("DELETE FROM bookings WHERE id = @id", ("@id", id));
        }
        _bookingsToClean.Clear();
    }

    /// <summary>
    /// TC-30 — Prevent price tampering via request parameters.
    ///
    /// The booking form posts only a property id, a date and a message. This
    /// test injects extra monthly_rent and deposit_amount fields into the form
    /// before submitting, exactly as a user with developer tools would, and
    /// confirms the persisted amounts come from the properties table instead.
    ///
    /// Guards the behaviour in book.php where the INSERT binds
    /// $property['monthly_rent'] and $property['deposit_amount'], never $_POST.
    /// </summary>
    [Test]
    [Category("Security")]
    [Property("TestCase", "TC-30")]
    public async Task Booking_WithTamperedPriceFields_PersistsTheDatabaseValues()
    {
        var booking = new BookingPage(Page);
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNsfasProperty);

        var expectedRent = Database.Scalar<decimal>(
            "SELECT monthly_rent FROM properties WHERE id = @id", ("@id", propertyId));
        var expectedDeposit = Database.Scalar<decimal>(
            "SELECT deposit_amount FROM properties WHERE id = @id", ("@id", propertyId));

        await SignInAsStudent();
        await booking.Open(propertyId);

        // Inject the tampered fields into the real form, then submit it.
        await Page.EvaluateAsync(@"() => {
            const form = document.querySelector('form');
            for (const [name, value] of [['monthly_rent', '1.00'], ['deposit_amount', '1.00']]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }
        }");

        await booking.MoveInDate.FillAsync(DateTime.Today.AddDays(30).ToString("yyyy-MM-dd"));
        await booking.Message.FillAsync("Price tampering regression test.");
        await booking.SubmitButton.ClickAsync();
        await Expect(Page).ToHaveURLAsync(new Regex(@"dashboard\.php"));

        var studentId = Database.UserId(TestConfig.StudentEmail);
        var row = Database.LatestBookingFor(studentId)!;
        var bookingId = Convert.ToInt32(row["id"]);
        _bookingsToClean.Add(bookingId);

        var escrow = Database.EscrowForBooking(bookingId)!;

        Assert.Multiple(() =>
        {
            Assert.That(Convert.ToDecimal(row["monthly_rent"]), Is.EqualTo(expectedRent),
                "The submitted monthly_rent overrode the property's stored rent.");
            Assert.That(Convert.ToDecimal(row["deposit_amount"]), Is.EqualTo(expectedDeposit),
                "The submitted deposit_amount overrode the property's stored deposit.");
            Assert.That(Convert.ToDecimal(escrow["amount"]), Is.EqualTo(expectedDeposit),
                "The escrow amount was taken from the request rather than the database.");
            Assert.That(Convert.ToDecimal(escrow["amount"]), Is.Not.EqualTo(1.00m),
                "The tampered value of R1.00 reached the escrow record.");
        });
    }

    /// <summary>
    /// DEF-010 — Bookings are not checked against room availability.
    ///
    /// EXPECTED TO FAIL against the current build.
    ///
    /// available_rooms is written by seed.sql and displayed by property.php,
    /// landlord.php and reports.php, but it is never decremented and book.php
    /// never reads it. A property advertising zero rooms accepts unlimited
    /// bookings, and the landlord's own dashboard will keep showing zero while
    /// requests pile up.
    ///
    /// The column is SMALLINT UNSIGNED, so the database cannot store a negative
    /// number — but that is a storage constraint, not a business control.
    ///
    /// This test asserts the correct behaviour so that the defect is visible in
    /// every run until it is fixed. Delete it only when the fix lands.
    /// </summary>
    [Test]
    [Property("Defect", "DEF-010")]
    public async Task Booking_OnAPropertyWithNoRoomsAvailable_IsRefused()
    {
        var booking = new BookingPage(Page);
        var propertyId = Database.PropertyId(TestConfig.Seed.InstantNonNsfasProperty);

        var originalRooms = Database.Scalar<int>(
            "SELECT available_rooms FROM properties WHERE id = @id", ("@id", propertyId));

        Database.Execute("UPDATE properties SET available_rooms = 0 WHERE id = @id",
            ("@id", propertyId));

        try
        {
            await SignInAsStudent();
            await booking.Open(propertyId);
            await booking.Submit(30, "Availability regression test.");

            var studentId = Database.UserId(TestConfig.StudentEmail);
            var latest = Database.LatestBookingFor(studentId);

            // Clean up first, so the assertion failure does not leave a stray row.
            if (latest is not null &&
                Convert.ToInt32(latest["property_id"]) == propertyId)
            {
                _bookingsToClean.Add(Convert.ToInt32(latest["id"]));
            }

            var created = latest is not null &&
                          Convert.ToInt32(latest["property_id"]) == propertyId &&
                          Convert.ToDateTime(latest["created_at"]) > DateTime.Now.AddMinutes(-2);

            Assert.That(created, Is.False,
                "DEF-010: a booking was accepted on a property advertising zero " +
                "available rooms. book.php never reads available_rooms, and no " +
                "code path decrements it, so the platform will over-book any " +
                "listing without limit. Add an availability check inside the " +
                "existing transaction in book.php and decrement the column there.");
        }
        finally
        {
            Database.Execute("UPDATE properties SET available_rooms = @rooms WHERE id = @id",
                ("@rooms", originalRooms), ("@id", propertyId));
        }
    }
}
