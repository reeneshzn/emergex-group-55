using MySqlConnector;

namespace EMERGEX.Tests.Support;

/// <summary>
/// Thin data-access helper for the integration suite. UI tests should never use
/// this — the point of an integration test is to drive the UI and then confirm
/// the PHP layer actually persisted the right thing in MySQL.
/// </summary>
public static class Database
{
    private static MySqlConnection Open()
    {
        var connection = new MySqlConnection(TestConfig.ConnectionString);
        connection.Open();
        return connection;
    }

    public static T? Scalar<T>(string sql, params (string Name, object? Value)[] parameters)
    {
        using var connection = Open();
        using var command = new MySqlCommand(sql, connection);
        foreach (var (name, value) in parameters)
            command.Parameters.AddWithValue(name, value ?? DBNull.Value);

        var result = command.ExecuteScalar();
        if (result is null || result is DBNull) return default;
        return (T) Convert.ChangeType(result, typeof(T));
    }

    public static List<Dictionary<string, object?>> Query(
        string sql, params (string Name, object? Value)[] parameters)
    {
        using var connection = Open();
        using var command = new MySqlCommand(sql, connection);
        foreach (var (name, value) in parameters)
            command.Parameters.AddWithValue(name, value ?? DBNull.Value);

        using var reader = command.ExecuteReader();
        var rows = new List<Dictionary<string, object?>>();
        while (reader.Read())
        {
            var row = new Dictionary<string, object?>(StringComparer.OrdinalIgnoreCase);
            for (var i = 0; i < reader.FieldCount; i++)
                row[reader.GetName(i)] = reader.IsDBNull(i) ? null : reader.GetValue(i);
            rows.Add(row);
        }
        return rows;
    }

    public static int Execute(string sql, params (string Name, object? Value)[] parameters)
    {
        using var connection = Open();
        using var command = new MySqlCommand(sql, connection);
        foreach (var (name, value) in parameters)
            command.Parameters.AddWithValue(name, value ?? DBNull.Value);
        return command.ExecuteNonQuery();
    }

    // --- Convenience lookups used across the integration tests ---

    public static int PropertyId(string title) =>
        Scalar<int>("SELECT id FROM properties WHERE title = @title LIMIT 1", ("@title", title));

    public static int UserId(string email) =>
        Scalar<int>("SELECT id FROM users WHERE email = @email LIMIT 1", ("@email", email));

    public static bool UserExists(string email) =>
        Scalar<long>("SELECT COUNT(*) FROM users WHERE email = @email", ("@email", email)) > 0;

    public static Dictionary<string, object?>? LatestBookingFor(int studentId) =>
        Query("SELECT * FROM bookings WHERE student_id = @id ORDER BY id DESC LIMIT 1",
              ("@id", studentId)).FirstOrDefault();

    public static Dictionary<string, object?>? EscrowForBooking(int bookingId) =>
        Query("SELECT * FROM escrow_transactions WHERE booking_id = @id LIMIT 1",
              ("@id", bookingId)).FirstOrDefault();

    public static Dictionary<string, object?>? LatestDocumentFor(int userId) =>
        Query("SELECT * FROM user_documents WHERE user_id = @id ORDER BY id DESC LIMIT 1",
              ("@id", userId)).FirstOrDefault();

    public static long AuditCount(string action) =>
        Scalar<long>("SELECT COUNT(*) FROM audit_logs WHERE action = @action", ("@action", action));

    /// <summary>Removes rows created by a test run so the suite stays repeatable.</summary>
    public static void DeleteUserByEmail(string email)
    {
        var id = Scalar<int>("SELECT id FROM users WHERE email = @email LIMIT 1", ("@email", email));
        if (id == 0) return;
        Execute("DELETE e FROM escrow_transactions e JOIN bookings b ON b.id = e.booking_id WHERE b.student_id = @id", ("@id", id));
        Execute("DELETE FROM bookings WHERE student_id = @id", ("@id", id));
        Execute("DELETE FROM user_documents WHERE user_id = @id", ("@id", id));
        Execute("DELETE FROM audit_logs WHERE user_id = @id", ("@id", id));
        Execute("DELETE FROM users WHERE id = @id", ("@id", id));
    }

    /// <summary>Resets a booking (and its escrow row) back to pending for repeatable tests.</summary>
    public static void ResetBooking(int bookingId)
    {
        Execute("UPDATE bookings SET status = 'pending', responded_at = NULL, checked_in_at = NULL WHERE id = @id", ("@id", bookingId));
        Execute("UPDATE escrow_transactions SET status = 'not_paid', paid_at = NULL, released_at = NULL, reference_code = NULL WHERE booking_id = @id", ("@id", bookingId));
    }
}
