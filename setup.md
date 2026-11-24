# Google Cloud and OAuth setup

This guide creates a dedicated Google Cloud project for the application, enables the Google Health API, and configures a web OAuth 2.0 client. The supplied script automates the Cloud CLI operations. Google Auth Platform branding, audience, scopes, test users and OAuth web-client settings must still be completed in the Cloud console.

## What the script automates

`setup-google-cloud.sh` can:

- create or select a Google Cloud project;
- optionally place it under an organisation or folder;
- optionally link a billing account;
- select the project in `gcloud`;
- enable `health.googleapis.com`;
- calculate and display the exact authorised origin and callback URI;
- print direct links to the remaining Google Auth Platform pages.

The OAuth consent configuration and OAuth client are intentionally manual. Google presents policy acknowledgements and security-sensitive settings in the console, and the client secret must be copied directly into the deployment's secret storage.

## Prerequisites

Before running the script, have:

1. A Google account permitted to create a Google Cloud project, or access to an existing project that you can administer.
2. The [Google Cloud CLI installed](https://cloud.google.com/sdk/docs/install).
3. An authenticated CLI session:

   ```bash
   gcloud auth login
   ```

4. Permission to create projects and enable services. If using a billing account, you also need permission to link the project to that account.
5. A globally unique project ID. Google project IDs are permanent after creation.
6. The public HTTPS hostname for this application, without a scheme, path or port, for example `heartrate.example.com`.
7. The deployed application reachable over HTTPS before completing any verification that requires Google to inspect its public pages.

## Run the automated setup

Make the script executable:

```bash
chmod +x setup-google-cloud.sh
```

Create a standalone project and enable the API:

```bash
./setup-google-cloud.sh \
  --project-id my-google-health-project \
  --project-name "Google Health Heart Rate" \
  --hostname heartrate.example.com
```

To link a billing account at the same time:

```bash
./setup-google-cloud.sh \
  --project-id my-google-health-project \
  --project-name "Google Health Heart Rate" \
  --hostname heartrate.example.com \
  --billing-account 000000-000000-000000
```

For an existing project:

```bash
./setup-google-cloud.sh \
  --project-id existing-project-id \
  --hostname heartrate.example.com \
  --skip-project-create
```

Use either `--organisation ORGANISATION_ID` or `--folder FOLDER_ID` when the new project must have an explicit parent. Run `./setup-google-cloud.sh --help` for all options.

The script is safe to rerun: it reuses an existing accessible project and enabling an already enabled service is harmless.

## Complete Google Auth Platform setup

The script prints project-specific links for the following pages. Confirm that the intended project is selected in the console before changing anything.

### 1. Branding

Open **Google Auth Platform > Branding** and provide:

- **App name:** `Google Health Heart Rate Intraday Data`
- **User support email:** an address that is monitored for sign-in and authorisation problems
- **App logo:** optional for private testing; if supplied, use an image you control
- **Application home page:** the application's HTTPS origin
- **Privacy policy:** a public HTTPS page accurately describing collection, local storage, use, retention and deletion of Google Health data
- **Terms of service:** a public HTTPS page if the console or intended publication requires it
- **Authorised domains:** add the registrable domain used by the home, privacy, terms and application URLs
- **Developer contact email:** an address monitored for Google notices

Keep the branding consistent with the deployed application. Do not imply that Google publishes or endorses the application.

### 2. Audience, publishing status and test users

Open **Google Auth Platform > Audience**.

1. Select the audience appropriate to the deployment. For normal Google accounts outside one Google Workspace organisation, use **External**.
2. Keep the publishing status as **Testing** while configuring and testing the application.
3. Add every Google account that will test the application under **Test users**. A user must use that exact Google account during both application sign-in and Google Health authorisation.

Add test users deliberately. Access to this application exposes sensitive health information in both Google Health and the local database.

### 3. Data access and scopes

Open **Google Auth Platform > Data access**, choose **Add or remove scopes**, and add only the scopes the application requests:

```text
openid
email
profile
https://www.googleapis.com/auth/googlehealth.health_metrics_and_measurements.readonly
https://www.googleapis.com/auth/googlehealth.activity_and_fitness.readonly
```

The two Google Health scopes match `urls.php`; the OpenID Connect scopes match `google-oauth.php`. If the Google Health scopes are not listed, confirm that **Google Health API** is enabled, then use the manual scope entry field if it is available.

Do not add broader read/write scopes. If the application later requests another sensitive or restricted scope, update this page and complete any additional justification or verification Google requires before deploying that change.

### 4. Create the OAuth 2.0 client

Open **Google Auth Platform > Clients**, click **Create client**, and select **Web application**.

Use a recognisable name such as `Google Health Heart Rate web client`, then enter the exact values printed by the script:

- **Authorised JavaScript origin:** `https://YOUR_HOSTNAME`
- **Authorised redirect URI:** `https://YOUR_HOSTNAME/token.php`

The application performs a server-side OAuth flow. The origin is included to make the client configuration explicit and to support any future same-origin browser integration; the callback URI is the value required by the current PHP application.

Create the client, then copy the client ID and client secret immediately into secure deployment configuration:

```text
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
```

Do not commit either the real environment file or the client secret. If the client secret is exposed, rotate it in Google Auth Platform and update the deployment.

### 5. Generate the local OAuth encryption key

Generate a Base64-encoded 32-byte key:

```bash
openssl rand -base64 32
```

Store it as:

```text
GOOGLE_OAUTH_ENCRYPTION_KEY=...
```

This key encrypts stored OAuth token bundles. Back it up securely before using the application. Losing it makes existing encrypted OAuth credentials unreadable; changing it requires users to reconnect Google Health.

### 6. Verify the deployment configuration

Set `WEBSITE_HOSTNAME` to the same hostname used above, without `https://` or a path:

```text
WEBSITE_HOSTNAME=heartrate.example.com
```

Check that:

- the application is reachable at `https://YOUR_HOSTNAME/`;
- `https://YOUR_HOSTNAME/token.php` reaches the deployed application rather than a proxy error or unrelated site;
- the OAuth client contains an exact redirect-URI match, including HTTPS and `/token.php`;
- the client ID, client secret and encryption key are present in the PHP runtime environment;
- the database tables described in `README.md` have been created.

## Test the complete flow

While the app remains in **Testing**:

1. Open the application in a fresh browser session.
2. Select **Sign in with Google** and use one of the configured test-user accounts.
3. Review the requested scopes and grant access.
4. Confirm that the browser returns to `/token.php` and then to the application.
5. Fetch one day of heart-rate data and confirm that it is stored and displayed.
6. Test **Log out from this browser**, then sign in again.
7. Test **Disconnect Google Health**, then reconnect it.
8. Repeat with a second test user and confirm that each user sees only their own Google Health identity and locally stored data.

## Verification and production settings

Testing and OAuth verification are separate concerns. Keep the app in **Testing** until its branding, URLs, scopes and user flow are correct.

Before changing the publishing status or making the app available beyond the named test users:

1. Review the scope classifications shown on **Data access**.
2. Complete every verification item shown by Google Auth Platform. Requirements can vary with audience, scope classification and distribution.
3. Ensure the application home page, privacy policy and terms pages are public, accurate, on authorised domains and consistent with the branding screen.
4. Verify domain ownership when prompted.
5. Prepare a clear explanation of why each Google Health scope is necessary and why a narrower scope is insufficient.
6. If requested, provide a demonstration video showing the complete OAuth flow and how the application uses data from each requested scope.
7. Submit the app for verification only after the production deployment and policy pages are stable.

Sensitive or restricted scopes can trigger additional review or security requirements. Follow the requirements displayed for this specific project rather than assuming that another project's approval applies.

For a private personal deployment, Google Auth Platform may offer **Personal** mode. If used, follow the console's Personal-mode workflow and its displayed limitations instead of the External/Testing steps above.

## Troubleshooting

### `PERMISSION_DENIED` when creating the project

The active account lacks project-creation permission or an organisation policy blocks project creation. Ask an administrator to create the project, then rerun the script with `--skip-project-create`.

### API enablement fails

Confirm the selected account has permission to enable services and that organisation policy permits `health.googleapis.com`:

```bash
gcloud config get-value project
gcloud services list --enabled --project=PROJECT_ID
```

### `redirect_uri_mismatch`

Compare the URI in the error with the client configuration character for character. For this application it must be:

```text
https://YOUR_HOSTNAME/token.php
```

### The user cannot authorise the app

Confirm that the app is in **Testing** and the exact Google account is listed under **Test users**. Also confirm that the required scopes are present under **Data access**.

### Google Health returns `403` after sign-in

Confirm that `health.googleapis.com` is enabled in the same project that owns the OAuth client and that both Google Health read-only scopes were granted. Disconnect and reconnect Google Health after correcting scopes so the user can grant the updated consent.

## Official references

- [Create and manage Google Cloud projects](https://cloud.google.com/resource-manager/docs/creating-managing-projects)
- [Google Health API in the API Library](https://console.cloud.google.com/apis/library/health.googleapis.com)
- [Manage OAuth app branding](https://support.google.com/cloud/answer/15549049?hl=en-GB)
- [Manage OAuth clients](https://support.google.com/cloud/answer/15549257?hl=en-GB)
- [Manage app data access and scopes](https://support.google.com/cloud/answer/15549135?hl=en-GB)
- [Submit an OAuth app for verification](https://support.google.com/cloud/answer/13461325?hl=en-GB)
