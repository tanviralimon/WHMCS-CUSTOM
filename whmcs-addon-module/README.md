# Orcus API — WHMCS Addon Module

Custom WHMCS addon module that exposes additional API actions for the Orcus client portal (orcus.one).

## What It Does

Registers **6 custom API actions** through the standard WHMCS API endpoint (`/includes/api.php`):

| Action | Description |
|---|---|
| `OrcusGetDNS` | Fetch DNS records via registrar module |
| `OrcusSaveDNS` | Save DNS records via registrar module |
| `OrcusGetStats` | Aggregated dashboard stats in one call |
| `OrcusGetServiceInfo` | Extended service info (server, config options, custom fields) |
| `OrcusGetEmailForwarding` | Fetch email forwarding rules via registrar |
| `OrcusSaveEmailForwarding` | Save email forwarding rules via registrar |

## Installation

### 1. Upload to WHMCS server

```bash
# SSH into the WHMCS server
ssh root@95.217.142.144

# Copy the module folder to the WHMCS addons directory
# (adjust the WHMCS path if different)
cp -r modules/addons/orcusapi /path/to/whmcs/modules/addons/orcusapi
```

The addon folder structure on the WHMCS server should be:
```
/path/to/whmcs/
  modules/
    addons/
      orcusapi/
        orcusapi.php    ← Module config & admin panel
        hooks.php       ← Custom API action handlers
```

### 2. Activate in WHMCS Admin

1. Login to WHMCS admin panel: `https://dash.orcustech.com/admin`
2. Go to **Setup → Addon Modules**
3. Find **Orcus API Bridge** and click **Activate**
4. Configure access control (grant to **Full Administrator**)
5. Click **Save Changes**

### 3. Verify It Works

Test with curl:

```bash
curl -X POST https://dash.orcustech.com/includes/api.php \
  -d "action=OrcusGetDNS" \
  -d "identifier=YOUR_API_IDENTIFIER" \
  -d "secret=YOUR_API_SECRET" \
  -d "domainid=123" \
  -d "responsetype=json"
```

Expected response:
```json
{
  "result": "success",
  "records": [
    {"hostname": "@", "type": "A", "address": "1.2.3.4", "priority": ""}
  ],
  "domain": "example.com",
  "registrar": "resellerclub"
}
```

## How It Works

The module uses WHMCS's `CustomApi` hook to register custom API actions. When the Laravel portal calls the standard WHMCS API endpoint with `action=OrcusGetDNS`, WHMCS routes it to our hook handler.

For DNS operations, the handler:
1. Loads the domain from `tbldomains`
2. Identifies the registrar module (e.g., resellerclub, enom)
3. Loads the registrar module PHP file
4. Calls the registrar's `GetDNS()` or `SaveDNS()` function directly
5. Returns the result as a standard API response

This is the same mechanism WHMCS itself uses when a client manages DNS through the client area.

## File Permissions

```bash
chmod 644 /path/to/whmcs/modules/addons/orcusapi/orcusapi.php
chmod 644 /path/to/whmcs/modules/addons/orcusapi/hooks.php
```

## Troubleshooting

- **"Invalid or Missing Module"**: Module folder must be named exactly `orcusapi` and contain `orcusapi.php`
- **Custom actions return nothing**: Module must be **Activated** in WHMCS Admin → Addon Modules
- **DNS returns "Registrar does not support DNS"**: The registrar module doesn't implement `GetDNS()` function
- **Permission errors**: Ensure web server (www-data) can read the module files
