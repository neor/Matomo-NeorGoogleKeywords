# Google Cloud setup and JSON key download

This guide explains how to connect NeorGoogleKeywords to Google Search Console using a service account. The JSON file contains an authentication key; the plugin retrieves statistics separately through the Search Console API.

The examples use the placeholder domain `example.com` and Matomo website ID `1`. Replace them with your own values. Google menu labels are shown in English and may differ in other interface languages.

## 1. Select a Google Cloud project

Open [Google Cloud Console](https://console.cloud.google.com/). Use the project selector in the top bar to select an existing project or create one through **New project**, for example `Matomo Search Console`. Wait for it to be created, then select it.

The following steps require permission to enable APIs and create a service account and its key in the selected project.

## 2. Enable the Search Console API

In the selected project, open **APIs & Services → Library**, find **Google Search Console API** and click **Enable**. If the API is already enabled, no further action is needed.

The plugin requires the [Search Console API](https://developers.google.com/webmaster-tools/about), which provides access to search performance statistics.

## 3. Create a service account

Open **IAM & Admin → Service Accounts → Create service account**. Enter a name, such as `matomo-search-console`, and finish creating the account.

The plugin does not need the Google Cloud project Owner or Editor roles to read Search Console data: access to the Search Console property is granted separately in step 5. You can leave the optional project access fields empty when creating the account.

Copy the new account's email address. It will look like this:

```text
matomo-search-console@your-project-id.iam.gserviceaccount.com
```

See [Google's service account creation documentation](https://docs.cloud.google.com/iam/docs/service-accounts-create) for details.

## 4. Download the JSON key

Open the account you created under **Service Accounts**. On the **Keys** tab, select **Add key → Create new key**, choose **JSON** and confirm creation.

Your browser will download the key file. You can rename it to `google-search-console.json`. Keep the complete file without changing its contents. The `client_email` field contains the service account's email address.

You cannot download the private part of an existing key again. If you lose the file, create a new key and delete the unused one. If your organization's policy prevents key creation, contact your Google Cloud administrator.

See [Google's JSON key documentation](https://docs.cloud.google.com/iam/docs/keys-create-delete).

## 5. Grant access in Search Console

Sign in to [Google Search Console](https://search.google.com/search-console/) as an owner of the required property. Select the property and open **Settings → Users and permissions → Add user**. Enter the service account email address from `client_email`.

The API supports read access for retrieving statistics. You can start with **Restricted**; **Full** also grants access to the data. The service account does not need to be an owner to perform imports. Save the user.

Access applies to a specific property. The domain property `sc-domain:example.com` and the URL-prefix property `https://www.example.com/` are separate entries. The plugin configuration must match the selected property.

Permissions are described in [Search Console Help](https://support.google.com/webmasters/answer/7687615) and the [Search Console API overview](https://developers.google.com/webmaster-tools/about).

## 6. Store the key on the server and configure Matomo

Transfer the JSON file to the Matomo server over a secure channel. Store it **outside the Matomo directory and all public web directories**. Do not include it in Git, screenshots or release archives.

Example absolute paths:

```text
/etc/matomo-secrets/google-search-console.json
C:/private/google-search-console.json
```

The PHP user and the user running cron must be able to read the file and access its parent directories. Also check `open_basedir` restrictions if enabled.

Add the following settings to `config/config.ini.php`, preserving existing settings. If the section already exists, update it instead of creating a duplicate:

```ini
[NeorGoogleKeywords]
credentials_file = "/etc/matomo-secrets/google-search-console.json"
property_1 = "sc-domain:example.com"
```

On Windows, replace the path in `credentials_file` with your own, such as `C:/private/google-search-console.json`.

The number in `property_1` is the **Matomo website ID**, not the Google Cloud project ID. For a URL-prefix property, use its exact value, including the protocol, subdomain and trailing slash:

```ini
property_1 = "https://www.example.com/"
```

You can connect multiple websites in the same section by adding `property_2`, `property_10` and other Matomo IDs. The service account must have access to each configured property.

## 7. Test the connection and import

Sign in to Matomo as a SuperUser. Open **Google Search → Import**, select the connected website and click **Test connection**. Confirm that the configured property is available.

Import one day with known statistics that is available in the form. The latest two days are usually not yet available because of Google's data delay. After importing, check the reports and the **Log** section.

For the initial historical import and daily updates, follow the [Imports section in the README](../README.md#imports).

## Troubleshooting

| Problem | What to check |
| --- | --- |
| JSON key unavailable | Check the absolute path, file existence, PHP/cron permissions and `open_basedir` restrictions. |
| Key rejected because of its location | Move the file outside Matomo and the web server's document root. |
| Authorization error | Confirm that the JSON key belongs to the intended service account, that neither the key nor the account is disabled, and that the server clock is correct. |
| Access denied | Confirm that the Search Console API is enabled in the correct project and that the service account email has been added to the correct Search Console property. |
| Configured property not found | Confirm that `property_ID` exactly matches an available property; check `sc-domain:`, the protocol and the subdomain. |
| Connection works, but no data appears | Confirm that statistics exist for the selected property and date, that the date is available for import, and that the import log shows no errors. |

[Back to the README](../README.md#configuration).
