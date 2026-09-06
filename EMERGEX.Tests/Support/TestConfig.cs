namespace EMERGEX.Tests.Support;

/// <summary>
/// Single source of truth for environment settings.
/// Every value can be overridden with an environment variable so the same
/// suite runs on a marker's machine without editing code.
/// </summary>
public static class TestConfig
{
    /// <summary>
    /// Root URL of the deployed application (the folder containing index.php).
    /// IMPORTANT: this must match 'base_url' in application/app/config/config.php,
    /// otherwise the links the app renders will point somewhere else and every
    /// navigation test will 404.
    /// </summary>
    public static string BaseUrl =>
        (Environment.GetEnvironmentVariable("EMERGEX_BASE_URL")
         ?? "http://localhost/emergex-group-55/application/public").TrimEnd('/');

    // --- Seeded demo accounts (created by application/database/install.php) ---

    public const string StudentEmail = "student@emergex.test";
    public const string StudentPassword = "Student@55";

    public const string LandlordEmail = "landlord@emergex.test";
    public const string LandlordPassword = "Landlord@55";

    public const string AdminEmail = "admin@emergex.test";
    public const string AdminPassword = "Admin@55Secure";

    // --- Database (used by the integration suite only) ---

    public static string ConnectionString =>
        Environment.GetEnvironmentVariable("EMERGEX_DB_CONNECTION")
        ?? "server=127.0.0.1;port=3306;database=emergex;user=root;password=;";

    /// <summary>Builds an absolute URL for a page, e.g. Url("login.php").</summary>
    public static string Url(string path) => $"{BaseUrl}/{path.TrimStart('/')}";

    // --- Seeded property fixtures ---
    // These come from application/database/seed.sql. Tests assert against them,
    // so if the seed changes, change them here in one place.

    public static class Seed
    {
        public const string InstantNsfasProperty = "Emerald House Hatfield";      // instant, NSFAS, R5 200
        public const string RequestNsfasProperty = "Braamfontein Studio Living";  // request flow, NSFAS, R6 100
        public const string InstantNonNsfasProperty = "Durban North Student Lofts"; // instant, no NSFAS, R4 550
        public const string RequestNsfasCheapProperty = "Mowbray Campus Rooms";   // request flow, NSFAS, R4 800

        public const int TotalPublished = 4;
        public const int NsfasAccredited = 3;
        public const int InstantBook = 2;
        public const int UnderFiveThousand = 2; // Durban North (4 550) + Mowbray (4 800)
    }
}
