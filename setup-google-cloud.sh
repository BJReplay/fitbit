#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  ./setup-google-cloud.sh --project-id PROJECT_ID --hostname HOSTNAME [options]

Required:
  --project-id ID         Globally unique Google Cloud project ID.
  --hostname HOST         Public application hostname, without scheme or path.

Options:
  --project-name NAME     Display name for the project (default: PROJECT_ID).
  --billing-account ID    Billing account to link to the project.
  --organisation ID       Create the project under this organisation.
  --folder ID             Create the project under this folder.
  --skip-project-create   Configure an existing project only.
  -h, --help              Show this help.
EOF
}

project_id=''
hostname=''
project_name=''
billing_account=''
organisation=''
folder=''
skip_project_create=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --project-id)
            project_id=${2:-}
            shift 2
            ;;
        --hostname)
            hostname=${2:-}
            shift 2
            ;;
        --project-name)
            project_name=${2:-}
            shift 2
            ;;
        --billing-account)
            billing_account=${2:-}
            shift 2
            ;;
        --organisation)
            organisation=${2:-}
            shift 2
            ;;
        --folder)
            folder=${2:-}
            shift 2
            ;;
        --skip-project-create)
            skip_project_create=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ -z "$project_id" || -z "$hostname" ]]; then
    usage >&2
    exit 2
fi

if [[ -n "$organisation" && -n "$folder" ]]; then
    printf 'Use only one of --organisation or --folder.\n' >&2
    exit 2
fi

if [[ ! "$project_id" =~ ^[a-z][a-z0-9-]{4,28}[a-z0-9]$ ]]; then
    printf 'Invalid project ID. Use 6-30 lowercase letters, digits or hyphens; start with a letter and do not end with a hyphen.\n' >&2
    exit 2
fi

hostname=${hostname#<scrubbed-url-domain>}
hostname=${hostname#<scrubbed-url-domain>}
hostname=${hostname%/}

if [[ "$hostname" == */* || "$hostname" == *:* || "$hostname" == *' '* ]]; then
    printf 'HOSTNAME must be a hostname only, without scheme, path, port or spaces.\n' >&2
    exit 2
fi

if [[ -z "$project_name" ]]; then
    project_name=$project_id
fi

if ! command -v gcloud >/dev/null 2>&1; then
    printf 'Google Cloud CLI (gcloud) is required. See setup.md.\n' >&2
    exit 1
fi

active_account=$(gcloud auth list --filter=status:ACTIVE --format='value(account)' | head -n 1)
if [[ -z "$active_account" ]]; then
    printf 'No active gcloud account. Run: gcloud auth login\n' >&2
    exit 1
fi

if [[ "$skip_project_create" == false ]]; then
    create_args=(projects create "$project_id" --name="$project_name")

    if [[ -n "$organisation" ]]; then
        create_args+=(--organization="$organisation")
    elif [[ -n "$folder" ]]; then
        create_args+=(--folder="$folder")
    fi

    if gcloud projects describe "$project_id" >/dev/null 2>&1; then
        printf 'Project %s already exists; continuing.\n' "$project_id"
    else
        gcloud "${create_args[@]}"
    fi
else
    gcloud projects describe "$project_id" >/dev/null
fi

gcloud config set project "$project_id"

if [[ -n "$billing_account" ]]; then
    gcloud billing projects link "$project_id" \
        --billing-account="$billing_account"
fi

gcloud services enable health.googleapis.com --project="$project_id"

origin="<scrubbed-url-domain>}"
redirect_uri="${origin}/token.php"
console_base="https://<scrubbed-url-domain>"

cat <<EOF

Automated Google Cloud setup is complete.

Project:        ${project_id}
Active account: ${active_account}
Enabled API:    health.googleapis.com

Complete the Google Auth Platform configuration manually as described in setup.md.
Use these exact OAuth client values:

Authorised JavaScript origin: ${origin}
Authorised redirect URI:      ${redirect_uri}

Direct console pages:
Branding:    ${console_base}/auth/branding?project=${project_id}
Audience:    ${console_base}/auth/audience?project=${project_id}
Data access: ${console_base}/auth/scopes?project=${project_id}
Clients:     ${console_base}/auth/clients?project=${project_id}
API:         ${console_base}/apis/library/health.googleapis.com?project=${project_id}

After creating the OAuth client, put its client ID and secret in the
GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET environment variables.
Create an encryption key with:

  openssl rand -base64 32

Store that value as GOOGLE_OAUTH_ENCRYPTION_KEY. Do not commit any secret.
EOF
