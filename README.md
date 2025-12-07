# Service Coat Generator

Service Coat Generator is a small PHP API that renders a WWII-style service coat PNG for a given soldier. It validates incoming requests and draws the coat with GD using the image assets under `lib/coat-resources`.

It was originally written by 1Lt. Theel for personnel-v1, then ported to personnel-v2, before being extracted into this microservice, which is currently used by personnel-v3.

## Prerequisites
- Docker 20+ (recommended for a consistent PHP/GD setup)
- An API key value you supply via the `SERVICE_COAT_API_KEY` environment variable

## Run Locally (Docker)
1. Build the image:
   ```bash
   docker build -t service-coat-generator .
   ```
2. Start the container (replace the API key as needed):
   ```bash
   docker run --rm -p 8080:80 -e SERVICE_COAT_API_KEY=dev-secret service-coat-generator
   ```
3. The API listens on `http://localhost:8080/` and only accepts `POST` requests.

## Usage
Send a `POST` request to `/` with a bearer token header and JSON body:
```bash
curl http://localhost:8080/ \
  -H "Authorization: Bearer dev-secret" \
  -H "Content-Type: application/json" \
  --data '{
    "last_name": "Smith",
    "rank_abbr": "Sgt",
    "unit_key": "29th",
    "awards_abbr": ["eib", "m:rifle:dod"],
    "balance": 200
  }' \
  --output service-coat.png
```

- Required fields: `last_name`, `rank_abbr`, `unit_key`
- Optional fields:
  - `awards_abbr`: array of award codes (must be strings)
  - `balance`: integer used by the generator for certain decorations

The response body is the final PNG. Save it to a file (as in the `--output` example) and open it with any image viewer. 

## Notes
- Requests without a bearer token that matches `SERVICE_COAT_API_KEY` return `401 Unauthorized`.
- A missing or misconfigured API key causes a `500` response so make sure the environment variable is set before starting the server.
- All errors are returned as JSON with an `error` message in the body.
- The API also emits an `X-Service-Coat-Signature-Crop` header (`y=<int>;height=<int>`) when a signature crop is available; the intention was for personnel-v3 to use that to slice the returned image, but we ended up not needing this feature in personnel-v3.
