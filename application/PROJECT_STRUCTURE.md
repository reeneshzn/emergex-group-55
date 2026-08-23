# Project structure

```text
application/
|-- app/
|   |-- config/            Database, URL and upload settings
|   `-- includes/          Bootstrap, security helpers and shared page layout
|-- database/
|   |-- install.php        Command-line installer and demo accounts
|   |-- schema.sql         Normalized MySQL schema, constraints and indexes
|   `-- seed.sql           Demonstration properties and booking
|-- public/
|   |-- assets/            Responsive theme, JavaScript and local illustrations
|   |-- uploads/           Protected uploaded demo documents
|   |-- index.php          Landing page
|   |-- properties.php     Search and filtering
|   |-- property.php       Verified listing details
|   |-- book.php           Booking creation
|   |-- dashboard.php      Student workflow
|   |-- landlord.php       Landlord workflow
|   `-- admin.php          Verification and audit workflow
|-- tests/
|   `-- security_checks.php
`-- README.md
```

The web server should expose only the `public` directory in a production deployment. The XAMPP classroom setup uses `/emergex/public/`, while `.htaccess` rules protect sensitive file types and uploaded executable extensions.
