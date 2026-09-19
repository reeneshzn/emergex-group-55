namespace EMERGEX.Tests.Support;

/// <summary>
/// In-memory upload fixtures.
///
/// Building these as byte arrays rather than checking sample files into the
/// repository keeps the suite self-contained: there is nothing to lose, nothing
/// to gitignore by accident, and no absolute paths to break on another machine.
/// </summary>
public static class TestFiles
{
    /// <summary>
    /// The smallest structurally valid PDF — a single blank page. Used as the
    /// "good" upload in NSFAS document tests.
    /// </summary>
    public static byte[] MinimalPdf()
    {
        const string pdf =
            "%PDF-1.4\n" +
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n" +
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n" +
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n" +
            "trailer<</Root 1 0 R>>\n" +
            "%%EOF\n";

        return System.Text.Encoding.ASCII.GetBytes(pdf);
    }

    /// <summary>
    /// A valid 1x1 transparent PNG, complete with the real PNG magic bytes, so
    /// a server that sniffs content type will accept it.
    /// </summary>
    public static byte[] TinyPng() => Convert.FromBase64String(
        "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk" +
        "YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==");

    /// <summary>
    /// A PHP web shell wearing a .png file name.
    ///
    /// This is the classic upload bypass: the extension and the declared MIME
    /// type both claim "image", but the bytes are executable server-side code.
    /// A safe application must reject it on content, not on the file name.
    ///
    /// The payload is deliberately inert — it prints a marker string and does
    /// nothing else — so a false pass leaves evidence without doing damage.
    /// </summary>
    public static byte[] DisguisedPhpShell() => System.Text.Encoding.ASCII.GetBytes(
        "<?php echo 'EMERGEX_UPLOAD_BYPASS_MARKER'; ?>\n");
}
